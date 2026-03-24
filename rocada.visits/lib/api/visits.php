<?php
/**
 * Контроллер визитов (сделок)
 * rocada.visits / lib/api/visits.php
 *
 * GET /api/visits?direction=sales&period=today|tomorrow  — список с пагинацией
 * GET /api/visits/{id}                                   — детали сделки
 */

use Bitrix\Crm\DealTable;

function handleVisits(array $params): void
{
    if ($params['method'] !== 'GET') {
        pwaSendError('Method Not Allowed', 405);
    }
    $userId = requireAuth();
    $id     = $params['id'];
    $id !== null ? visitDetail($userId, $id, $params) : visitsList($userId, $params);
}

// ── Список визитов ────────────────────────────────────────────────────────────
function visitsList(int $userId, array $params): void
{
    $q       = $params['get'];
    $mid     = $params['moduleId'];
    $dirId   = $q['direction'] ?? 'sales';
    $period  = $q['period']    ?? 'today';
    $page    = max(1, (int)($q['page']     ?? 1));
    $perPage = min(50, max(1, (int)($q['per_page'] ?? 20)));

    $dirCfg  = getDirectionConfig($dirId, $mid);

    // Проверка доступа к направлению
    $allowedUsers = array_map('intval', $dirCfg['allowed_users'] ?? []);
    if (!empty($allowedUsers) && !in_array($userId, $allowedUsers, true)) {
        pwaSendError('Доступ к данному направлению запрещён', 403);
    }

    $stages  = $period === 'tomorrow'
        ? ($dirCfg['stages_tomorrow'] ?? [])
        : ($dirCfg['stages_today']    ?? []);

    if (empty($stages)) {
        pwaSendError('Стадии не настроены для выбранного периода', 422);
    }

    $latF    = $dirCfg['lat_field'] ?? '';
    $lngF    = $dirCfg['lng_field'] ?? '';
    $vdF     = $dirCfg['visit_date_field'] ?? '';

    $filter  = ['STAGE_ID' => $stages, 'ASSIGNED_BY_ID' => $userId];

    // Фильтр по воронкам (CATEGORY_ID) если указаны в настройках направления
    $pipelines = array_map('intval', $dirCfg['pipelines'] ?? []);
    if (!empty($pipelines)) {
        $filter['CATEGORY_ID'] = $pipelines;
    }

    $dealFields = array_filter($dirCfg['deal_fields'] ?? []);

    $select  = array_unique(array_filter(array_merge(
        [
            'ID', 'TITLE', 'STAGE_ID', 'CATEGORY_ID',
            'COMPANY_ID', 'CONTACT_ID', 'ASSIGNED_BY_ID',
            'BEGINDATE', 'CLOSEDATE', 'DATE_MODIFY', 'DATE_CREATE',
            'OPPORTUNITY', 'CURRENCY_ID', 'COMMENTS',
            $latF, $lngF, $vdF,
        ],
        $dealFields   // доп. UF-поля направления
    )));

    $rows  = DealTable::getList([
        'filter' => $filter,
        'select' => $select,
        'order'  => ['BEGINDATE' => 'ASC', 'ID' => 'ASC'],
        'limit'  => $perPage,
        'offset' => ($page - 1) * $perPage,
    ])->fetchAll();

    $total = DealTable::getCount($filter);

    // Подсчёт успешных/провальных визитов за текущий период
    $stagesSuccess = json_decode(\Bitrix\Main\Config\Option::get($mid, 'stages_success', '[]'), true) ?? [];
    $stagesFail    = json_decode(\Bitrix\Main\Config\Option::get($mid, 'stages_fail',    '[]'), true) ?? [];

    $successCount = 0;
    $failCount    = 0;

    // Фильтр по дате визита (сегодня или завтра)
    if ($vdF && (!empty($stagesSuccess) || !empty($stagesFail))) {
        $dayOffset = ($period === 'tomorrow') ? 1 : 0;
        $dayStartTs  = mktime(0, 0, 0, date('n'), date('j') + $dayOffset, date('Y'));
        $dayEndTs    = mktime(23, 59, 59, date('n'), date('j') + $dayOffset, date('Y'));
        // UF-поля фильтруются через строку в формате сайта
        $dayStartStr = ConvertTimeStamp($dayStartTs, 'FULL');
        $dayEndStr   = ConvertTimeStamp($dayEndTs,   'FULL');

        if (!empty($stagesSuccess)) {
            $successCount = DealTable::getCount([
                'STAGE_ID'       => $stagesSuccess,
                'ASSIGNED_BY_ID' => $userId,
                '>=' . $vdF      => $dayStartStr,
                '<=' . $vdF      => $dayEndStr,
            ]);
        }
        if (!empty($stagesFail)) {
            $failCount = DealTable::getCount([
                'STAGE_ID'       => $stagesFail,
                'ASSIGNED_BY_ID' => $userId,
                '>=' . $vdF      => $dayStartStr,
                '<=' . $vdF      => $dayEndStr,
            ]);
        }
    }

    pwaSendJson([
        'items'         => array_map(fn($d) => formatDeal($d, $dirCfg), $rows),
        'total'         => $total,
        'page'          => $page,
        'per_page'      => $perPage,
        'pages'         => (int)ceil($total / $perPage),
        'success_count' => $successCount,
        'fail_count'    => $failCount,
    ]);
}

// ── Детали визита ─────────────────────────────────────────────────────────────
function visitDetail(int $userId, int $dealId, array $params): void
{
    $mid    = $params['moduleId'];
    $dirCfg = getDirectionConfig($params['get']['direction'] ?? 'sales', $mid);

    // Проверка доступа к направлению
    $allowedUsers = array_map('intval', $dirCfg['allowed_users'] ?? []);
    if (!empty($allowedUsers) && !in_array($userId, $allowedUsers, true)) {
        pwaSendError('Доступ к данному направлению запрещён', 403);
    }

    $deal = DealTable::getList([
        'filter' => ['=ID' => $dealId, '=ASSIGNED_BY_ID' => $userId],
        'select' => ['*', 'UF_*'],
    ])->fetch();

    if (!$deal) {
        pwaSendError('Визит не найден или нет доступа', 404);
    }

    pwaSendJson([
        'deal'     => formatDeal($deal, $dirCfg),
        'company'  => !empty($deal['COMPANY_ID']) ? getCompanyData((int)$deal['COMPANY_ID']) : null,
        'contact'  => !empty($deal['CONTACT_ID']) ? getContactData((int)$deal['CONTACT_ID']) : null,
        'products' => getDealProducts($dealId),
    ]);
}

// ── Резолв id стадии → человеко-читаемое имя ─────────────────────────────────
function pwaResolveStageName(string $stageId, int $categoryId = 0): string
{
    if (empty($stageId)) return '';
    $statusType = $categoryId > 0 ? 'DEAL_STAGE_' . $categoryId : 'DEAL_STAGE';
    $list = \CCrmStatus::GetStatusList($statusType);
    return $list[$stageId] ?? $stageId;
}

// ── Форматирование ────────────────────────────────────────────────────────────
function formatDeal(array $d, array $cfg): array
{
    $lf   = $cfg['lat_field'] ?? '';
    $rf   = $cfg['lng_field'] ?? '';
    $vdf  = $cfg['visit_date_field'] ?? '';
    $catId = (int)($d['CATEGORY_ID'] ?? 0);

    // Доп. поля направления: собираем значения UF-полей с метками
    $dealFieldCodes = array_values(array_filter($cfg['deal_fields'] ?? []));
    $extraFields = [];
    if (!empty($dealFieldCodes)) {
        // Получаем метки UF-полей сделки (один раз из Bitrix)
        static $ufLabels = null;
        if ($ufLabels === null) {
            $ufLabels = [];
            $ufList = \CUserTypeEntity::GetList([], ['ENTITY_ID' => 'CRM_DEAL']);
            while ($uf = $ufList->Fetch()) {
                $label = $uf['EDIT_FORM_LABEL'][LANGUAGE_ID]
                    ?? $uf['LIST_COLUMN_LABEL'][LANGUAGE_ID]
                    ?? $uf['FIELD_NAME'];
                $ufLabels[$uf['FIELD_NAME']] = $label;
            }
        }
        foreach ($dealFieldCodes as $code) {
            if (array_key_exists($code, $d)) {
                $extraFields[] = [
                    'code'  => $code,
                    'label' => $ufLabels[$code] ?? $code,
                    'value' => is_array($d[$code]) ? implode(', ', $d[$code]) : (string)($d[$code] ?? ''),
                ];
            }
        }
    }

    return [
        'id'          => (int)$d['ID'],
        'title'       => $d['TITLE']      ?? '',
        'stage_id'    => $d['STAGE_ID']   ?? '',
        'stage_name'  => pwaResolveStageName($d['STAGE_ID'] ?? '', $catId),
        'category_id' => $catId,
        'company_id'  => (int)($d['COMPANY_ID'] ?? 0) ?: null,
        'contact_id'  => (int)($d['CONTACT_ID'] ?? 0) ?: null,
        'assigned_id' => (int)($d['ASSIGNED_BY_ID'] ?? 0) ?: null,
        'date'        => pwaFormatDate($d['BEGINDATE']    ?? null),
        'close_date'  => pwaFormatDate($d['CLOSEDATE']    ?? null),
        'date_create' => pwaFormatDate($d['DATE_CREATE']  ?? null),
        'date_modify' => pwaFormatDate($d['DATE_MODIFY']  ?? null),
        'visit_date'  => $vdf && !empty($d[$vdf]) ? pwaFormatDateTime($d[$vdf]) : null,
        'opportunity' => (float)($d['OPPORTUNITY']  ?? 0),
        'currency'    => $d['CURRENCY_ID'] ?? 'RUB',
        'comments'    => $d['COMMENTS']    ?? '',
        'lat'         => $lf && !empty($d[$lf]) ? (float)$d[$lf] : null,
        'lng'         => $rf && !empty($d[$rf]) ? (float)$d[$rf] : null,
        'geo_set'     => $lf && !empty($d[$lf]),
        'fields'      => $extraFields,   // доп. поля направления
        'field_codes' => array_values($dealFieldCodes), // список кодов для фронта
    ];
}


function getCompanyData(int $id): ?array
{
    $r = \CCrmCompany::GetByID($id);
    if (!$r) return null;
    return [
        'id'      => (int)$r['ID'],
        'title'   => $r['TITLE']   ?? '',
        'phone'   => pwaExtractFirstPhone($r['PHONE'] ?? []),
        'address' => $r['ADDRESS'] ?? '',
        'web'     => $r['WEB']     ?? '',
    ];
}

function getContactData(int $id): ?array
{
    $r = \CCrmContact::GetByID($id);
    if (!$r) return null;
    $name = trim(implode(' ', array_filter([
        $r['LAST_NAME'] ?? '', $r['NAME'] ?? '', $r['SECOND_NAME'] ?? ''
    ])));
    return [
        'id'       => (int)$r['ID'],
        'fullName' => $name,
        'position' => $r['POST']  ?? '',
        'phone'    => pwaExtractFirstPhone($r['PHONE'] ?? []),
        'email'    => pwaExtractFirstPhone($r['EMAIL'] ?? []),
    ];
}

function getDealProducts(int $dealId): array
{
    $rows = \CCrmProductRow::GetList(
        [],
        ['OWNER_TYPE' => 'D', 'OWNER_ID' => $dealId],
        false,
        false,
        ['ID', 'PRODUCT_NAME', 'PRICE', 'QUANTITY']
    );
    $result = [];
    while ($p = $rows->Fetch()) {
        $result[] = [
            'id'       => (int)$p['ID'],
            'name'     => $p['PRODUCT_NAME'] ?? '',
            'price'    => (float)($p['PRICE']    ?? 0),
            'quantity' => (float)($p['QUANTITY'] ?? 0),
            'sum'      => (float)(($p['PRICE'] ?? 0) * ($p['QUANTITY'] ?? 0)),
        ];
    }
    return $result;
}
