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
 *   distance_m  — расстояние в метрах от точки (опционально)
 *   photo       — фото входной группы клиники (обязательно)
 *   photo_meta  — JSON: { source: camera|gallery, taken_at: ISO 8601, lat, lng }
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
    $distanceM  = isset($body['distance_m']) && is_numeric($body['distance_m'])
        ? (int)round((float)$body['distance_m'])
        : null;

    if (empty($statusId) && empty($forceStage)) {
        pwaSendError('status_id или new_stage_id обязательны', 422);
    }

    $dirCfg       = getDirectionConfig($dirId, $mid);
    $checkinField = $dirCfg['checkin_field'] ?? '';

    // Проверяем доступ к сделке (+ читаем поле чекина, если настроено)
    $photoField = $dirCfg['photo_field'] ?? '';
    $select = ['ID', 'TITLE', 'UF_UNLOAD_DOCS'];
    if (!empty($checkinField)) {
        $select[] = $checkinField;
    }
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

    // Фото входной группы обязательно — без него визит не завершается
    $photo = $params['files']['photo'] ?? null;
    if ($photoError = pwaValidateVisitPhoto($photo)) {
        pwaSendError($photoError, 422);
    }

    $photoMeta = json_decode((string)($body['photo_meta'] ?? ''), true);
    $maxRange  = (int)($dirCfg['photo_max_distance'] ?? 0) ?: PWA_VISIT_PHOTO_DEFAULT_RANGE;
    $photoCheck = pwaCheckVisitPhoto(
        $photo['tmp_name'],
        is_array($photoMeta) ? $photoMeta : [],
        pwaGetUnloadPointCoords((int)($deal['UF_UNLOAD_DOCS'] ?? 0)),
        $maxRange,
        pwaVisitPlannedAt($visitDateField ? ($deal[$visitDateField] ?? null) : null),
        max(0, (int)($dirCfg['photo_max_time_diff'] ?? PWA_VISIT_PHOTO_DEFAULT_TIME_DIFF))
    );

    $photoRestFile = pwaVisitPhotoRestFile($photo, (int)$dealId);

    // Подстраховка: если гео было пропущено и чекин ещё не зафиксирован —
    // фиксируем дату/время чекина в момент сохранения результата
    if (!empty($checkinField) && empty($deal[$checkinField])) {
        DealTable::update($dealId, [$checkinField => date('d.m.Y H:i:s')]);
    }

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
    // «Неверная геолокация» (Да/Нет): Нет — дата и место фото подтверждены, Да — нет
    $wrongGeoField = $dirCfg['photo_wrong_geo_field'] ?? '';
    if (!empty($wrongGeoField)) {
        $updateFields[$wrongGeoField] = $photoCheck['ok'] ? 0 : 1;
    }
    // Фото входной группы — в файловое поле сделки
    if (!empty($photoField)) {
        $updateFields[$photoField] = pwaDealFileFieldValue($photoField, $photoRestFile, (array)($deal[$photoField] ?? []));
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

    // Дистанция от точки (если передана)
    if ($distanceM !== null && $distanceM >= 0) {
        $tlText .= "\n\n📍 Отметился в {$distanceM} м от точки визита";
    }

    // Фото входной группы: результат проверки
    $tlText .= "\n\n" . pwaVisitPhotoCheckText($photoCheck);

    // Сделка уже обновлена — сразу отвечаем приложению, а комментарий с фото
    // добавляем после ответа: загрузка файла в таймлайн небыстрая,
    // и приложение не должно ждать её (иначе прокси обрывает запрос по таймауту)
    pwaFinishResponse([
        'deal_id'     => $dealId,
        'status_id'   => $statusId,
        'status_name' => $statusLabel,
        'new_stage'   => $targetStage ?: null,
        'photo_ok'    => $photoCheck['ok'],
        'message'     => 'Результат сохранён',
    ]);

    // Комментарий с фото во вложении — через REST от имени пользователя,
    // так фото показывается в таймлайне превью, а не ссылкой
    try {
        pwaCallRestAsUser('crm.timeline.comment.add', [
            'fields' => [
                'ENTITY_ID'   => (int)$dealId,
                'ENTITY_TYPE' => 'deal',
                'COMMENT'     => $tlText,
                'FILES'       => [$photoRestFile],
            ],
        ]);
    } catch (\RuntimeException $e) {
        // Визит уже завершён — не роняем ответ, пишем комментарий без вложения
        CommentEntry::create([
            'TEXT'      => $tlText . (!empty($photoField) ? "\n\nФото сохранено в карточке сделки." : ''),
            'BINDINGS'  => [['ENTITY_TYPE_ID' => \CCrmOwnerType::Deal, 'ENTITY_ID' => $dealId]],
            'AUTHOR_ID' => $userId,
        ]);
    }

    exit;
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
