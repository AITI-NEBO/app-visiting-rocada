<?php
/**
 * Контроллер завершения визита
 * rocada.visits / lib/api/visits_result.php
 *
 * POST /api/visits/{id}/result
 * multipart/form-data:
 *   status_id   — ID статуса из result_statuses направления
 *   comment     — комментарий
 *   direction   — ID направления
 *   lat, lng    — местоположение телефона в момент сохранения итога
 *   accuracy    — точность местоположения, м
 *   geo_error   — почему местоположение не получено (если lat/lng пустые)
 *   photo       — фото входной группы клиники (обязательность — в настройках направления)
 *   photo_meta  — JSON: { source: camera|gallery, picked: camera|gallery, taken_at: ISO 8601, lat, lng, ... }
 *
 * Поля сделки:
 *   lat_field / lng_field / checkin_field — местоположение и время сохранения итога;
 *   photo_lat_field / photo_lng_field / photo_date_field — место и время съёмки фото.
 */

use Bitrix\Crm\DealTable;
use Bitrix\Crm\Timeline\CommentEntry;
use Bitrix\Main\Config\Option;

function handleVisitsResult(array $params): void
{
    if ($params['method'] !== 'POST') {
        pwaSendError('Method Not Allowed', 405);
    }

    $userId  = requireAuth();
    $dealId  = $params['id'];
    $body    = $params['body'];
    $mid     = $params['moduleId'];

    if (!$dealId) {
        pwaSendError('ID визита обязателен', 422);
    }

    $statusId   = trim($body['status_id']   ?? '');
    $comment    = trim($body['comment']     ?? '');
    $forceStage = trim($body['new_stage_id'] ?? '');
    $dirId      = trim($body['direction'] ?? ($params['get']['direction'] ?? ''));

    // Местоположение в момент сохранения итога
    $saveLat = is_numeric($body['lat'] ?? null) ? (float)$body['lat'] : null;
    $saveLng = is_numeric($body['lng'] ?? null) ? (float)$body['lng'] : null;
    if ($saveLat === null || $saveLng === null || abs($saveLat) > 90 || abs($saveLng) > 180
        || ($saveLat == 0 && $saveLng == 0)) {
        $saveLat = $saveLng = null;
    }
    $saveAccuracy = is_numeric($body['accuracy'] ?? null) ? (int)round((float)$body['accuracy']) : null;
    $saveGeoError = mb_substr(trim((string)($body['geo_error'] ?? '')), 0, 150);
    $savedAt      = new \DateTime('now');

    if (empty($statusId) && empty($forceStage)) {
        pwaSendError('status_id или new_stage_id обязательны', 422);
    }

    $dirCfg       = getDirectionConfig($dirId, $mid);
    $checkinField = $dirCfg['checkin_field'] ?? '';

    // Проверяем доступ к сделке
    $photoField = $dirCfg['photo_field'] ?? '';
    $select = ['ID', 'TITLE', 'UF_UNLOAD_DOCS'];
    if (!empty($photoField)) {
        $select[] = $photoField;
    }
    $visitDateField = $dirCfg['visit_date_field'] ?? '';
    if (!empty($visitDateField)) {
        $select[] = $visitDateField;
    }
    $deal = DealTable::getList([
        'filter' => ['=ID' => $dealId, '=ASSIGNED_BY_ID' => $userId],
        'select' => $select,
    ])->fetch();

    if (!$deal) {
        pwaSendError('Визит не найден или нет доступа', 404);
    }

    // Фото входной группы: обязательность задаётся в выбранном итоге;
    // если у направления нет итогов или итог не выбран — общей галочкой направления
    $photoRequired = pwaVisitPhotoRequired($dirCfg, $statusId);
    $photo     = $params['files']['photo'] ?? null;
    $hasPhoto  = !empty($photo) && ($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $photoMeta = json_decode((string)($body['photo_meta'] ?? ''), true);
    $photoMeta = is_array($photoMeta) ? $photoMeta : [];

    if ($photoRequired || $hasPhoto) {
        if ($photoError = pwaValidateVisitPhoto($photo)) {
            pwaSendError($photoError, 422);
        }
    }
    if ($hasPhoto) {
        $picked = ($photoMeta['picked'] ?? '') === 'camera' ? 'camera' : 'gallery';
        if ($picked === 'gallery' && ($dirCfg['photo_allow_gallery'] ?? true) === false) {
            pwaSendError('Загрузка фото из галереи отключена. Сделайте фото камерой', 422);
        }
        if ($picked === 'camera' && ($dirCfg['photo_allow_camera'] ?? true) === false) {
            pwaSendError('Съёмка камерой отключена. Выберите фото из галереи', 422);
        }
    }

    $point    = pwaGetUnloadPointCoords((int)($deal['UF_UNLOAD_DOCS'] ?? 0));
    $maxRange = (int)($dirCfg['photo_max_distance'] ?? 0) ?: PWA_VISIT_PHOTO_DEFAULT_RANGE;

    $photoCheck    = null;
    $photoRestFile = null;
    if ($hasPhoto) {
        $photoCheck = pwaCheckVisitPhoto(
            $photo['tmp_name'],
            $photoMeta,
            $point,
            $maxRange,
            pwaVisitPlannedAt($visitDateField ? ($deal[$visitDateField] ?? null) : null),
            max(0, (int)($dirCfg['photo_max_time_diff'] ?? PWA_VISIT_PHOTO_DEFAULT_TIME_DIFF))
        );
        $photoRestFile = pwaVisitPhotoRestFile($photo, (int)$dealId);
    }

    // Расстояние от места сохранения итога до точки визита
    $distanceM = ($saveLat !== null && $point)
        ? pwaDistanceMeters($saveLat, $saveLng, $point['lat'], $point['lng'])
        : null;

    // Определяем целевой статус и стадию из конфига направления
    $targetStage = $forceStage;
    $statusLabel = '';

    if (!empty($statusId)) {
        $statuses = $dirCfg['result_statuses'] ?? [];

        foreach ($statuses as $st) {
            if (($st['id'] ?? '') === $statusId) {
                $targetStage = !empty($targetStage) ? $targetStage : ($st['stage'] ?? '');
                $statusLabel = $st['name'] ?? $statusId;
                break;
            }
        }

        // Backward compatibility: если result_statuses нет — ищем в result_stages (старый формат)
        if (empty($statusLabel) && !empty($dirCfg['result_stages'])) {
            $rsMap = $dirCfg['result_stages'];
            if (isset($rsMap[$statusId])) {
                $targetStage = $targetStage ?: $rsMap[$statusId];
                // Старые лейблы
                $legacyLabels = [
                    'order'     => 'Заказ оформлен',
                    'refuse'    => 'Отказ',
                    'callback'  => 'Перезвонить',
                    'completed' => 'Визит завершён',
                ];
                $statusLabel = $legacyLabels[$statusId] ?? $statusId;
            }
        }

        // Fallback к глобальным Option (совместимость с очень старыми настройками)
        if (empty($targetStage)) {
            $legacyOpts = [
                'order'     => 'deal_stage_result_order',
                'refuse'    => 'deal_stage_result_refuse',
                'callback'  => 'deal_stage_result_callback',
                'completed' => 'deal_stage_result_completed',
            ];
            if (isset($legacyOpts[$statusId])) {
                $targetStage = Option::get($mid, $legacyOpts[$statusId], '');
            }
        }
    }

    // Флаг «Завершено из приложения» (поле Да/Нет, по умолчанию UF_DEVICE).
    // Пишем тем же запросом, что и стадию, чтобы роботы на смене стадии уже видели флаг.
    $deviceField  = Option::get($mid, 'device_deal_field', 'UF_DEVICE');
    $updateFields = [];
    if (!empty($deviceField)) {
        $updateFields[$deviceField] = 1;
    }
    // «Неверная геолокация» (Да/Нет): Нет — дата и место фото подтверждены, Да — не подтверждены.
    // Фото не приложено (значит, для этого итога оно не обязательно) — поле не трогаем
    $wrongGeoField = $dirCfg['photo_wrong_geo_field'] ?? '';
    if (!empty($wrongGeoField) && $photoCheck) {
        $updateFields[$wrongGeoField] = $photoCheck['ok'] ? 0 : 1;
    }

    // Местоположение и время сохранения итога — в поля широты, долготы и чекина
    if ($saveLat !== null) {
        if (!empty($dirCfg['lat_field'])) {
            $updateFields[$dirCfg['lat_field']] = pwaDealCoordValue($dirCfg['lat_field'], $saveLat);
        }
        if (!empty($dirCfg['lng_field'])) {
            $updateFields[$dirCfg['lng_field']] = pwaDealCoordValue($dirCfg['lng_field'], $saveLng);
        }
    }
    if (!empty($checkinField)) {
        $updateFields[$checkinField] = pwaDealDateValue($checkinField, $savedAt);
    }

    if ($photoCheck) {
        // Фото входной группы — в файловое поле сделки
        if (!empty($photoField)) {
            $updateFields[$photoField] = pwaDealFileFieldValue($photoField, $photoRestFile, (array)($deal[$photoField] ?? []));
        }
        // Место и время съёмки фото — в отдельные поля
        if ($photoCheck['lat'] !== null) {
            if (!empty($dirCfg['photo_lat_field'])) {
                $updateFields[$dirCfg['photo_lat_field']] = pwaDealCoordValue($dirCfg['photo_lat_field'], $photoCheck['lat']);
            }
            if (!empty($dirCfg['photo_lng_field'])) {
                $updateFields[$dirCfg['photo_lng_field']] = pwaDealCoordValue($dirCfg['photo_lng_field'], $photoCheck['lng']);
            }
        }
        if ($photoCheck['taken_at'] && !empty($dirCfg['photo_date_field'])) {
            $updateFields[$dirCfg['photo_date_field']] = pwaDealDateValue($dirCfg['photo_date_field'], $photoCheck['taken_at']);
        }
    }
    if (!empty($targetStage)) {
        $updateFields['STAGE_ID'] = $targetStage;
    }

    // Обновляем через REST API от имени пользователя,
    // чтобы в истории Битрикс24 отображалось что стадию сменил конкретный пользователь
    if (!empty($updateFields)) {
        try {
            pwaUpdateDealViaRest((int)$dealId, $updateFields);
        } catch (\RuntimeException $e) {
            pwaSendError('Ошибка обновления сделки: ' . $e->getMessage(), 500);
        }
    }

    // Timeline-комментарий
    $tlText = $statusLabel ? "Результат: $statusLabel" : 'Визит завершён';
    if ($comment) {
        $tlText .= "\n\n$comment";
    }

    // Местоположение в момент сохранения итога
    $accText = $saveAccuracy ? " (точность ±{$saveAccuracy} м)" : '';
    if ($saveLat === null) {
        $tlText .= "\n\n📍 Местоположение при сохранении не получено" . ($saveGeoError !== '' ? ": $saveGeoError" : '');
    } elseif ($distanceM !== null) {
        $tlText .= "\n\n📍 Сохранено в {$distanceM} м от точки визита{$accText}";
    } else {
        $tlText .= "\n\n📍 Местоположение при сохранении получено{$accText}, но у точки визита не заполнены координаты";
    }

    // Фото входной группы: результат проверки
    $tlText .= "\n\n" . ($photoCheck
        ? pwaVisitPhotoCheckText($photoCheck)
        : '📷 Фото входной группы не приложено (для этого итога не обязательно)');

    // Сделка уже обновлена — сразу отвечаем приложению, а комментарий с фото
    // добавляем после ответа: загрузка файла в таймлайн небыстрая,
    // и приложение не должно ждать её (иначе прокси обрывает запрос по таймауту)
    pwaFinishResponse([
        'deal_id'     => $dealId,
        'status_id'   => $statusId,
        'status_name' => $statusLabel,
        'new_stage'   => $targetStage ?: null,
        'photo_ok'    => (bool)($photoCheck['ok'] ?? false),
        'message'     => 'Результат сохранён',
    ]);

    // Комментарий с фото во вложении — через REST от имени пользователя,
    // так фото показывается в таймлайне превью, а не ссылкой
    $tlFields = [
        'ENTITY_ID'   => (int)$dealId,
        'ENTITY_TYPE' => 'deal',
        'COMMENT'     => $tlText,
    ];
    if ($photoRestFile) {
        $tlFields['FILES'] = [$photoRestFile];
    }
    try {
        pwaCallRestAsUser('crm.timeline.comment.add', ['fields' => $tlFields]);
    } catch (\RuntimeException $e) {
        // Визит уже завершён — не роняем ответ, пишем комментарий без вложения
        CommentEntry::create([
            'TEXT'      => $tlText . ($photoRestFile && !empty($photoField) ? "\n\nФото сохранено в карточке сделки." : ''),
            'BINDINGS'  => [['ENTITY_TYPE_ID' => \CCrmOwnerType::Deal, 'ENTITY_ID' => $dealId]],
            'AUTHOR_ID' => $userId,
        ]);
    }

    exit;
}

/**
 * Обязательно ли фото входной группы.
 * Есть итоги и итог выбран — галочка в этом итоге (по умолчанию нет);
 * итогов нет или итог не выбран — общая галочка направления.
 */
function pwaVisitPhotoRequired(array $dirCfg, string $statusId): bool
{
    $statuses = $dirCfg['result_statuses'] ?? [];
    if (!empty($statuses) && $statusId !== '') {
        foreach ($statuses as $st) {
            if (($st['id'] ?? '') === $statusId) {
                return !empty($st['photo_required']);
            }
        }
    }
    return ($dirCfg['photo_required'] ?? true) !== false;
}

/**
 * Отправляет JSON-ответ и закрывает соединение с клиентом, не завершая скрипт.
 * Если сервер не умеет закрывать соединение раньше (не PHP-FPM) —
 * ответ просто уйдёт вместе с окончанием скрипта.
 */
function pwaFinishResponse(array $data): void
{
    ignore_user_abort(true);
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}
