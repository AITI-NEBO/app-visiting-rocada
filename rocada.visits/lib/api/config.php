<?php
/**
 * Контроллер конфигурации
 * rocada.visits / lib/api/config.php
 *
 * GET /api/config — полная конфигурация модуля для PWA
 */

use Bitrix\Main\Config\Option;

function handleConfig(array $params): void
{
    if ($params['method'] !== 'GET') {
        pwaSendError('Method Not Allowed', 405);
    }

    $userId = (int) requireAuth();
    $mid = $params['moduleId'];

    // Направления
    $raw  = Option::get($mid, 'pwa_directions', '');
    $dirs = [];
    if (!empty($raw)) {
        $dirs = json_decode($raw, true) ?? [];
    }
    if (empty($dirs)) {
        $dirs = [getDirectionConfig('sales', $mid)];
    }

    // Фильтруем направления по allowed_users: пустой список = доступ для всех
    $dirs = array_values(array_filter($dirs, function($dir) use ($userId) {
        $allowed = array_map('intval', $dir['allowed_users'] ?? []);
        return empty($allowed) || in_array($userId, $allowed, true);
    }));

    // Поля карточек
    $dealFields    = json_decode(Option::get($mid, 'pwa_deal_fields',    '[]'), true) ?? [];
    $companyFields = json_decode(Option::get($mid, 'pwa_company_fields', '[]'), true) ?? [];
    $contactFields = json_decode(Option::get($mid, 'pwa_contact_fields', '[]'), true) ?? [];

    // Стадии CRM (все категории — для UI)
    $stages = [];
    $categories = \Bitrix\Crm\Category\DealCategory::getAll(true);
    foreach ($categories as $cat) {
        $catId   = (int)$cat['ID'];
        $catName = $cat['NAME'] ?? "Категория $catId";
        $stType  = $catId === 0 ? 'DEAL_STAGE' : 'DEAL_STAGE_' . $catId;
        foreach (\CCrmStatus::GetStatusList($stType) as $id => $name) {
            $stages[] = ['id' => $id, 'name' => $name, 'category' => $catName, 'category_id' => $catId];
        }
    }

    pwaSendJson([
        'module_id'     => $mid,
        'pwa_url'       => Option::get($mid, 'pwa_url', ''),
        'directions'    => $dirs,
        'fields'        => [
            'deal'    => $dealFields,
            'company' => $companyFields,
            'contact' => $contactFields,
        ],
        'crm_stages'    => $stages,
        'result_stages' => [
            'success' => json_decode(Option::get($mid, 'stages_success', '[]'), true) ?? [],
            'fail'    => json_decode(Option::get($mid, 'stages_fail',    '[]'), true) ?? [],
        ],
    ]);
}
