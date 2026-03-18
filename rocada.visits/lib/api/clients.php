<?php
/**
 * Контроллер клиентов (для карты и детальных страниц)
 * rocada.visits / lib/api/clients.php
 *
 * GET /api/clients?direction=xxx&search=...&page=1&per_page=50   ← список для карты
 * GET /api/clients/:id?type=company|contact                       ← детальная карточка
 */

use Bitrix\Crm\DealTable;

function handleClients(array $params): void
{
    if ($params['method'] !== 'GET') {
        pwaSendError('Method Not Allowed', 405);
    }

    $userId = requireAuth();
    $q      = $params['get'];
    $mid    = $params['moduleId'];

    // ── Детальный запрос: /api/clients/:id?type=company|contact ─────────────
    if (!empty($params['id'])) {
        $entityId   = (int)$params['id'];
        $entityType = strtolower($q['type'] ?? 'company');

        if ($entityType === 'contact') {
            $contact = \CCrmContact::GetByID($entityId);
            if (!$contact) {
                pwaSendError('Контакт не найден', 404);
            }
            // Добавляем мультиполя
            $contact['PHONE'] = \CCrmFieldMulti::GetEntityFields('CONTACT', $entityId, 'PHONE', false, false);
            $contact['EMAIL'] = \CCrmFieldMulti::GetEntityFields('CONTACT', $entityId, 'EMAIL', false, false);
            // Название компании
            if (!empty($contact['COMPANY_ID'])) {
                $comp = \CCrmCompany::GetByID((int)$contact['COMPANY_ID']);
                $contact['COMPANY_TITLE'] = $comp['TITLE'] ?? '';
            }
            // Доп. поля из конфига
            $contactFields = json_decode(\Bitrix\Main\Config\Option::get($mid, 'pwa_contact_fields', '[]'), true) ?? [];
            $extraFields   = array_map(fn($code) => ['code' => $code, 'label' => $code], $contactFields);

            pwaSendJson([
                'contact'      => $contact,
                'extra_fields' => $extraFields,
            ]);
        } else {
            $company = \CCrmCompany::GetByID($entityId);
            if (!$company) {
                pwaSendError('Компания не найдена', 404);
            }
            $company['PHONE'] = \CCrmFieldMulti::GetEntityFields('COMPANY', $entityId, 'PHONE', false, false);
            $company['EMAIL'] = \CCrmFieldMulti::GetEntityFields('COMPANY', $entityId, 'EMAIL', false, false);

            // Контакты компании
            $contactRes = \CCrmContact::GetListEx(
                ['NAME' => 'ASC'],
                ['COMPANY_ID' => $entityId],
                false,
                ['nTopCount' => 20],
                ['ID', 'NAME', 'LAST_NAME']
            );
            $contacts = [];
            while ($row = $contactRes->Fetch()) {
                $row['PHONE'] = \CCrmFieldMulti::GetEntityFields('CONTACT', $row['ID'], 'PHONE', false, false);
                $contacts[]   = $row;
            }

            $companyFields = json_decode(\Bitrix\Main\Config\Option::get($mid, 'pwa_company_fields', '[]'), true) ?? [];
            $extraFields   = array_map(fn($code) => ['code' => $code, 'label' => $code], $companyFields);

            pwaSendJson([
                'company'      => $company,
                'contacts'     => $contacts,
                'extra_fields' => $extraFields,
            ]);
        }
        return;
    }

    // ── Список для карты: /api/clients?direction=xxx ──────────────────────
    $dirId   = $q['direction'] ?? 'sales';
    $search  = trim($q['search']   ?? '');
    $page    = max(1, (int)($q['page']     ?? 1));
    $perPage = min(100, max(1, (int)($q['per_page'] ?? 50)));

    $dirCfg = getDirectionConfig($dirId, $mid);
    $latF   = $dirCfg['lat_field'] ?? '';
    $lngF   = $dirCfg['lng_field'] ?? '';

    $stages = array_merge($dirCfg['stages_today'] ?? [], $dirCfg['stages_tomorrow'] ?? []);
    $filter = ['ASSIGNED_BY_ID' => $userId];
    if (!empty($stages)) {
        $filter['STAGE_ID'] = $stages;
    }

    $select = array_unique(array_filter(['ID', 'TITLE', 'COMPANY_ID', $latF, $lngF]));

    $rows = DealTable::getList([
        'filter' => $filter,
        'select' => $select,
        'limit'  => $perPage,
        'offset' => ($page - 1) * $perPage,
    ])->fetchAll();

    $seen    = [];
    $clients = [];

    foreach ($rows as $row) {
        $compId = (int)($row['COMPANY_ID'] ?? 0);
        $key    = $compId ?: 'deal_' . $row['ID'];
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        $item = [
            'deal_id'    => (int)$row['ID'],
            'deal_title' => $row['TITLE'] ?? '',
            'company_id' => $compId ?: null,
            'lat'        => ($latF && !empty($row[$latF])) ? (float)$row[$latF] : null,
            'lng'        => ($lngF && !empty($row[$lngF])) ? (float)$row[$lngF] : null,
        ];

        if ($compId) {
            $comp = \CCrmCompany::GetByID($compId);
            if ($comp) {
                $item['company_name']    = $comp['TITLE']   ?? '';
                $item['company_address'] = $comp['ADDRESS'] ?? '';
                $item['company_phone']   = pwaExtractFirstPhone($comp['PHONE'] ?? []);
            }
        }

        if ($search) {
            $haystack = strtolower(($item['company_name'] ?? '') . ' ' . $item['deal_title']);
            if (!str_contains($haystack, strtolower($search))) continue;
        }

        $clients[] = $item;
    }

    pwaSendJson(['items' => $clients, 'total' => count($clients), 'page' => $page, 'per_page' => $perPage]);
}
