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
    $userId = requireAuth();
    $id     = $params['id'] ?? null;

    if ($params['method'] === 'POST') {
        if ($id !== null) {
            pwaSendError('Method Not Allowed', 405);
        }
        createVisit($userId, $params);
        return;
    }

    if ($params['method'] !== 'GET') {
        pwaSendError('Method Not Allowed', 405);
    }

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

    $stagesSuccess = json_decode(\Bitrix\Main\Config\Option::get($mid, 'stages_success', '[]'), true) ?? [];
    $stagesFail    = json_decode(\Bitrix\Main\Config\Option::get($mid, 'stages_fail', '[]'), true) ?? [];

    $stages = [];
    if ($period === 'tomorrow') {
        $stages = $dirCfg['stages_tomorrow'] ?? [];
    } elseif ($period === 'today') {
        $stages = $dirCfg['stages_today']    ?? [];
    } elseif ($period === 'completed') {
        $resultStagesList = [];
        foreach (($dirCfg['result_statuses'] ?? []) as $rs) {
            if (!empty($rs['stage'])) {
                $resultStagesList[] = trim($rs['stage']);
            }
        }
        $stages = array_unique(array_filter(array_merge($resultStagesList, $stagesSuccess, $stagesFail)));
    } elseif ($period === 'all') {
        // Для period=all используем дефолтный фильтр по неудаленным/незакрытым. 
        // Если нужны конкретные стадии, берем все, что есть в 'stages_today' + 'stages_tomorrow' + начальные
        $stages = array_unique(array_filter(array_merge($dirCfg['stages_today'] ?? [], $dirCfg['stages_tomorrow'] ?? [])));
    }

    if (empty($stages) && $period !== 'all') {
        pwaSendError($period === 'completed' ? 'Стадии завершения не настроены' : 'Стадии не настроены для выбранного периода', 422);
    }

    $latF    = $dirCfg['lat_field'] ?? '';
    $lngF    = $dirCfg['lng_field'] ?? '';
    $vdF     = $dirCfg['visit_date_field'] ?? '';

    $filter  = ['ASSIGNED_BY_ID' => $userId];
    if (!empty($stages)) {
        $filter['STAGE_ID'] = $stages;
    }
    if ($period === 'all') {
        $filter['!CLOSED'] = 'Y';
    }

    if (!empty($q['company_id'])) {
        $filter['COMPANY_ID'] = (int)$q['company_id'];
    }

    // Фильтр по воронкам (CATEGORY_ID) если указаны в настройках направления
    $pipelines = array_map('intval', $dirCfg['pipelines'] ?? []);
    if (!empty($pipelines)) {
        $filter['CATEGORY_ID'] = $pipelines;
    }

    // Фильтр по дате (только для "completed")
    if ($period === 'completed') {
        $dayStartTs  = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
        $dayEndTs    = mktime(23, 59, 59, date('n'), date('j'), date('Y'));
        if ($vdF) {
            $filter['>=' . $vdF] = ConvertTimeStamp($dayStartTs, 'FULL');
            $filter['<=' . $vdF] = ConvertTimeStamp($dayEndTs, 'FULL');
        } else {
            $filter['>=DATE_MODIFY'] = ConvertTimeStamp($dayStartTs, 'FULL');
            $filter['<=DATE_MODIFY'] = ConvertTimeStamp($dayEndTs, 'FULL');
        }
    }

    $dealFields = array_filter($dirCfg['deal_fields'] ?? []);

    $select  = array_unique(array_filter(array_merge(
        [
            'ID', 'TITLE', 'STAGE_ID', 'CATEGORY_ID',
            'COMPANY_ID', 'CONTACT_ID', 'ASSIGNED_BY_ID',
            'BEGINDATE', 'CLOSEDATE', 'DATE_MODIFY', 'DATE_CREATE',
            'OPPORTUNITY', 'CURRENCY_ID', 'COMMENTS',
            $latF, $lngF, $vdF, 'UF_UNLOAD_DOCS', 'UF_*'
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

    // Подгрузка координат и адресов из IBLOCK 206 (Пункты разгрузки)
    $unloadData = [];
    $unloadDocsIds = array_filter(array_column($rows, 'UF_UNLOAD_DOCS'));
    if (!empty($unloadDocsIds) && \Bitrix\Main\Loader::includeModule('iblock')) {
        $elements = \CIBlockElement::GetList(
            [],
            ['ID' => array_unique($unloadDocsIds), 'IBLOCK_ID' => 206],
            false,
            false,
            ['ID', 'NAME', 'PROPERTY_LATITUDE', 'PROPERTY_LONGITUDE']
        );
        while ($el = $elements->Fetch()) {
            $latVal = trim((string)($el['PROPERTY_LATITUDE_VALUE'] ?? ''));
            $lngVal = trim((string)($el['PROPERTY_LONGITUDE_VALUE'] ?? ''));
            $entry = ['name' => trim((string)($el['NAME'] ?? ''))];
            if ($latVal !== '' && $lngVal !== '') {
                $entry['lat'] = (float)$latVal;
                $entry['lng'] = (float)$lngVal;
            }
            $unloadData[(int)$el['ID']] = $entry;
        }
    }

    foreach ($rows as &$r) {
        $udid = (int)($r['UF_UNLOAD_DOCS'] ?? 0);
        if ($udid && isset($unloadData[$udid])) {
            if (isset($unloadData[$udid]['lat'])) {
                $r['_UNLOAD_LAT'] = $unloadData[$udid]['lat'];
                $r['_UNLOAD_LNG'] = $unloadData[$udid]['lng'];
            }
            $r['_COMPANY_ADDRESS'] = $unloadData[$udid]['name'];
        }
    }
    unset($r);

    $total = DealTable::getCount($filter);

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

// ── Создание визита ───────────────────────────────────────────────────────────
function createVisit(int $userId, array $params): void
{
    $mid    = $params['moduleId'];
    $body   = $params['body'] ?? [];
    $dirId  = $body['direction'] ?? 'sales';
    $dirCfg = getDirectionConfig($dirId, $mid);

    $companyId = (int)($body['company_id'] ?? 0);
    $pointId   = (int)($body['point_id'] ?? 0);
    $visitDate = trim($body['visit_date'] ?? '');
    $visitTime = trim($body['visit_time'] ?? '10:00');

    if ($companyId <= 0) {
        pwaSendError('Не указана компания', 400);
    }

    $comp = \CCrmCompany::GetByID($companyId);
    $title = $comp ? 'Визит: ' . $comp['TITLE'] : 'Новый визит';

    $fields = [
        'TITLE' => $title,
        'COMPANY_ID' => $companyId,
        'ASSIGNED_BY_ID' => $userId,
        // Определяем воронку и первую стадию из неё
        'CATEGORY_ID' => !empty($dirCfg['pipelines']) ? (int)reset($dirCfg['pipelines']) : 0,
    ];

    $vdf = $dirCfg['visit_date_field'] ?? '';
    if ($vdf && $visitDate) {
        $dtObj = new \Bitrix\Main\Type\DateTime($visitDate . ' ' . $visitTime . ':00', 'Y-m-d H:i:s');
        $fields[$vdf] = $dtObj;
    }

    if ($pointId > 0) {
        $fields['UF_UNLOAD_DOCS'] = $pointId;
    }

    $deal = new \CCrmDeal(false);
    $dealId = $deal->Add($fields, true, ['DISABLE_USER_FIELD_CHECK' => true]);

    if (!$dealId) {
        pwaSendError($deal->LAST_ERROR ?: 'Ошибка создания сделки', 500);
    }

    pwaSendJson(['success' => true, 'deal_id' => $dealId]);
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

    if (!empty($deal['UF_UNLOAD_DOCS']) && \Bitrix\Main\Loader::includeModule('iblock')) {
        $el = \CIBlockElement::GetList([], ['ID' => (int)$deal['UF_UNLOAD_DOCS'], 'IBLOCK_ID' => 206], false, false, ['ID', 'PROPERTY_LATITUDE', 'PROPERTY_LONGITUDE'])->Fetch();
        if ($el) {
            $latVal = trim((string)($el['PROPERTY_LATITUDE_VALUE'] ?? ''));
            $lngVal = trim((string)($el['PROPERTY_LONGITUDE_VALUE'] ?? ''));
            if ($latVal !== '' && $lngVal !== '') {
                $deal['_UNLOAD_LAT'] = (float)$latVal;
                $deal['_UNLOAD_LNG'] = (float)$lngVal;
            }
        }
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
        'lat'         => $d['_UNLOAD_LAT'] ?? (($lf && !empty($d[$lf])) ? (float)$d[$lf] : null),
        'lng'         => $d['_UNLOAD_LNG'] ?? (($rf && !empty($d[$rf])) ? (float)$d[$rf] : null),
        'geo_set'     => !empty($d['_UNLOAD_LAT']) || ($lf && !empty($d[$lf])),
        'company_address' => $d['_COMPANY_ADDRESS'] ?? '',
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
