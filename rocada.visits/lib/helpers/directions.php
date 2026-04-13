<?php
/**
 * Хелпер для работы с конфигурацией направлений
 * rocada.visits / lib/helpers/directions.php
 */

use Bitrix\Main\Config\Option;

/**
 * Возвращает все направления из Option, с fallback на старый формат
 */
function getAllDirections(string $moduleId = 'rocada.visits'): array
{
    $raw  = Option::get($moduleId, 'pwa_directions', '');
    $dirs = [];

    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && !empty($decoded)) {
            $dirs = $decoded;
        }
    }

    // Fallback к старым настройкам rocada.telegram
    if (empty($dirs)) {
        $dirs = [[
            'id'               => 'sales',
            'name'             => 'Продажи',
            'icon'             => 'briefcase',
            'pipelines'        => [],
            'map_deals_scope'  => 'all',
            'stages_today'     => array_values(array_filter([Option::get($moduleId, 'deal_stage_filter', '')])),
            'stages_tomorrow'  => array_values(array_filter([Option::get($moduleId, 'deal_stage_filter_tomorrow', '')])),
            'stages_planned'   => [],
            'lat_field'        => Option::get($moduleId, 'lat_field', Option::get($moduleId, 'latitude_field', '')),
            'lng_field'        => Option::get($moduleId, 'lng_field', Option::get($moduleId, 'longitude_field', '')),
            'comment_field'    => Option::get($moduleId, 'comment_field', ''),
            'visit_date_field' => '',
            'deal_fields'      => [],
            'allowed_users'    => [],
            'result_stages'    => [
                'order'     => Option::get($moduleId, 'deal_stage_result_order',     ''),
                'refuse'    => Option::get($moduleId, 'deal_stage_result_refuse',    ''),
                'callback'  => Option::get($moduleId, 'deal_stage_result_callback',  ''),
                'completed' => Option::get($moduleId, 'deal_stage_result_completed', ''),
            ],
        ]];
    }

    return $dirs;
}

/**
 * Возвращает конфиг одного направления по id
 */
function getDirectionConfig(string $directionId, string $moduleId = 'rocada.visits'): array
{
    foreach (getAllDirections($moduleId) as $dir) {
        if ($dir['id'] === $directionId) {
            return $dir;
        }
    }

    // Если id не найден — вернуть первое направление (безопасный fallback)
    $all = getAllDirections($moduleId);
    return $all[0] ?? [
        'id'               => 'sales',
        'name'             => 'Продажи',
        'icon'             => 'briefcase',
        'map_deals_scope'  => 'all',
        'pipelines'        => [],
        'stages_today'     => [],
        'stages_tomorrow'  => [],
        'stages_planned'   => [],
        'lat_field'        => '',
        'lng_field'        => '',
        'comment_field'    => '',
        'visit_date_field' => '',
        'deal_fields'      => [],
        'allowed_users'    => [],
        'result_stages'    => ['order' => '', 'refuse' => '', 'callback' => '', 'completed' => ''],
    ];
}
