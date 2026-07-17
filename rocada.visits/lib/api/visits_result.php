<?php
/**
 * Контроллер завершения визита
 * rocada.visits / lib/api/visits_result.php
 *
 * POST /api/visits/{id}/result
 * Body: {
 *   "status_id":  "rs_xxx",      // ID статуса из result_statuses направления
 *   "comment":    "...",
 *   "direction":  "dir_xxx",
 *   "distance_m": 123,           // расстояние в метрах от точки (опционально)
 * }
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
    $select = ['ID', 'TITLE'];
    if (!empty($checkinField)) {
        $select[] = $checkinField;
    }
    $deal = DealTable::getList([
        'filter' => ['=ID' => $dealId, '=ASSIGNED_BY_ID' => $userId],
        'select' => $select,
    ])->fetch();

    if (!$deal) {
        pwaSendError('Визит не найден или нет доступа', 404);
    }

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

    // Меняем стадию если указана — через REST API от имени пользователя,
    // чтобы в истории Битрикс24 отображалось что стадию сменил конкретный пользователь
    if (!empty($targetStage)) {
        try {
            pwaUpdateDealViaRest((int)$dealId, ['STAGE_ID' => $targetStage]);
        } catch (\RuntimeException $e) {
            pwaSendError('Ошибка смены стадии: ' . $e->getMessage(), 500);
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

    CommentEntry::create([
        'TEXT'     => $tlText,
        'BINDINGS' => [['ENTITY_TYPE_ID' => \CCrmOwnerType::Deal, 'ENTITY_ID' => $dealId]],
        'AUTHOR_ID' => $userId,
    ]);

    pwaSendJson([
        'deal_id'     => $dealId,
        'status_id'   => $statusId,
        'status_name' => $statusLabel,
        'new_stage'   => $targetStage ?: null,
        'message'     => 'Результат сохранён',
    ]);
}
