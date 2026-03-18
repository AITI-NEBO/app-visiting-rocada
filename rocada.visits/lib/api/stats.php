<?php
/**
 * Контроллер статистики
 * rocada.visits / lib/api/stats.php
 *
 * GET /api/stats?direction=sales&period=day|week|month
 */

use Bitrix\Crm\DealTable;
use Bitrix\Main\Entity\ExpressionField;

function handleStats(array $params): void
{
    if ($params['method'] !== 'GET') {
        pwaSendError('Method Not Allowed', 405);
    }

    $userId  = requireAuth();
    $q       = $params['get'];
    $mid     = $params['moduleId'];
    $dirId   = $q['direction'] ?? 'sales';
    $period  = $q['period']    ?? 'day';

    $dirCfg  = getDirectionConfig($dirId, $mid);
    $latF    = $dirCfg['lat_field'] ?? '';
    $stages  = array_merge($dirCfg['stages_today'] ?? [], $dirCfg['stages_tomorrow'] ?? []);

    [$from, $to] = periodDates($period);

    $filter  = [
        'ASSIGNED_BY_ID' => $userId,
        '>=BEGINDATE'    => $from,
        '<=BEGINDATE'    => $to,
    ];
    if (!empty($stages)) {
        $filter['STAGE_ID'] = $stages;
    }

    $total     = DealTable::getCount($filter);

    $completedFilter = $filter;
    if ($latF) {
        $completedFilter['!=' . $latF] = false;
    }
    $completed = $latF ? DealTable::getCount($completedFilter) : 0;

    // По стадиям
    $byStage = DealTable::getList([
        'filter'  => $filter,
        'select'  => ['STAGE_ID', 'CNT'],
        'group'   => ['STAGE_ID'],
        'runtime' => [new ExpressionField('CNT', 'COUNT(%s)', 'ID')],
    ])->fetchAll();

    pwaSendJson([
        'period'    => $period,
        'date_from' => $from,
        'date_to'   => $to,
        'total'     => $total,
        'completed' => $completed,
        'planned'   => $total - $completed,
        'by_stage'  => array_map(fn($r) => [
            'stage_id'   => $r['STAGE_ID'],
            'stage_name' => '',
            'count'      => (int)$r['CNT'],
        ], $byStage),
    ]);
}

function periodDates(string $period): array
{
    $tz  = new \DateTimeZone('Europe/Moscow');
    $now = new \DateTime('now', $tz);

    [$start, $end] = match ($period) {
        'week'  => [
            (clone $now)->modify('monday this week')->setTime(0, 0, 0),
            (clone $now)->modify('sunday this week')->setTime(23, 59, 59),
        ],
        'month' => [
            (clone $now)->modify('first day of this month')->setTime(0, 0, 0),
            (clone $now)->modify('last day of this month')->setTime(23, 59, 59),
        ],
        default => [
            (clone $now)->setTime(0, 0, 0),
            (clone $now)->setTime(23, 59, 59),
        ],
    };

    return [$start->format('d.m.Y H:i:s'), $end->format('d.m.Y H:i:s')];
}
