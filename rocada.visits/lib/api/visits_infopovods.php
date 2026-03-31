<?php
/**
 * Контроллер для работы с инфоповодами
 * GET /api/visits/{id}/infopovods - Выдача структуры инфоповодов для UI
 * POST /api/visits/{id}/infopovods - Корректное сохранение в HL-блок 12 (VISIT_LOG_HLBLOCK_ID)
 */

use \Bitrix\Main\Loader;
use \Bitrix\Crm\DealTable;
use \Bitrix\Highloadblock\HighloadBlockTable;

const RESULTS_IBLOCK_ID = 237;
const VISIT_LOG_HLBLOCK_ID = 12;

function handleVisitsInfopovods(array $params): void
{
    $dealId = (int)($params['id'] ?? 0);
    if ($dealId <= 0) {
        pwaSendError('B24_ERROR_DEAL_ID', 400);
    }

    $userId = (int) requireAuth();

    Loader::includeModule('crm');
    Loader::includeModule('iblock');
    Loader::includeModule('highloadblock');

    if ($params['method'] === 'GET') {
        getInfopovods($dealId);
    } elseif ($params['method'] === 'POST') {
        saveInfopovods($dealId, $userId, $params['body'] ?? []);
    } else {
        pwaSendError('Method Not Allowed', 405);
    }
}

function getInfopovods(int $dealId): void
{
    $dbDeal = DealTable::getList([
        'select' => ['COMPANY_ID', 'ASSIGNED_BY_ID', 'UF_CRM_1595504443'],
        'filter' => ['=ID' => $dealId]
    ])->fetch();

    if (!$dbDeal) {
        pwaSendError('Deal not found', 404);
    }

    $infopovodIds = $dbDeal['UF_CRM_1595504443'];
    if (empty($infopovodIds) || !is_array($infopovodIds)) {
        pwaSendJson(['items' => []]);
    }

    // 1. Получаем сами инфоповоды (ИБ Инфоповодов, предполагается 219 или константа из класса)
    // Legacy app uses `Infopovods::IBLOCK_ID`. Let's assume it's 219 (or we fetch it dynamically if we don't know). 
    // Wait, the legacy app code had CIBlockElement::GetList with IBLOCK_ID => Infopovods::IBLOCK_ID.
    // Let's find Infopovods::IBLOCK_ID if possible, or just look up by ID since ID is unique across system.
    $rsIblock = \CIBlockElement::GetList([], ['ID' => $infopovodIds], false, false, ['ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_TYPE_INFO']);
    $infopovods = [];
    $allProductsLinks = [];
    $allProductsIds = [];

    while ($arIblock = $rsIblock->GetNextElement()) {
        $fields = $arIblock->GetFields();
        $props = $arIblock->GetProperties();
        $infopovods[$fields['ID']] = $fields;
        
        if (!empty($props['PRODUCT']['VALUE'])) {
            $val = is_array($props['PRODUCT']['VALUE']) ? $props['PRODUCT']['VALUE'] : [$props['PRODUCT']['VALUE']];
            $allProductsLinks[$fields['ID']] = $val;
            $allProductsIds = array_merge($allProductsIds, $val);
        }
    }

    // 2. Получаем Продукты
    $products = [];
    if (!empty($allProductsIds)) {
        $rsProd = \CIBlockElement::GetList([], ['IBLOCK_ID' => 26, 'ID' => array_unique($allProductsIds)], false, false, ['ID', 'XML_ID', 'NAME']);
        while ($arProd = $rsProd->Fetch()) {
            $products[$arProd['ID']] = $arProd;
        }
    }

    $allProductsModified = [];
    foreach ($allProductsLinks as $key => $vals) {
        foreach ($vals as $v) {
            if (isset($products[$v])) {
                $allProductsModified[$key][] = ['xml_id' => $products[$v]['XML_ID'], 'name' => $products[$v]['NAME']];
            }
        }
    }

    // 3. Допустимые статусы (ИБ 236)
    $rsStatus = \CIBlockElement::GetList([], ['IBLOCK_ID' => 236, 'ACTIVE' => 'Y'], false, false, ['ID', 'NAME', 'PROPERTY_TYPE_INFO', 'PROPERTY_STATUS', 'PROPERTY_COMMENT']);
    $statuses = [];
    while ($arStatus = $rsStatus->Fetch()) {
        $statuses[] = $arStatus;
    }

    // PROPERTY_TYPE_INFO enum (ИБ Инфоповоды) - PROPERTY_ID 2183
    $property_enums = \CIBlockPropertyEnum::GetList([], ["PROPERTY_ID" => 2183]);
    $infopovodTypes = [];
    while ($enum_fields = $property_enums->GetNext()) {
        $infopovodTypes[$enum_fields['XML_ID']] = $enum_fields["ID"];
    }

    // Собираем результат
    $resultItems = [];
    foreach ($infopovods as $id => $val) {
        $typeEnumIdStatus = $infopovodTypes[$val['PROPERTY_TYPE_INFO_ENUM_ID']] ?? null;

        $filterStatuses = function($statusEnumId) use ($statuses, $typeEnumIdStatus) {
            $res = [];
            foreach ($statuses as $st) {
                if ($st['PROPERTY_TYPE_INFO_ENUM_ID'] == $typeEnumIdStatus && $st['PROPERTY_STATUS_ENUM_ID'] == $statusEnumId) {
                    $res[] = [
                        'id' => $st['ID'],
                        'name' => $st['NAME'],
                        'is_comment_required' => ($st['PROPERTY_COMMENT_ENUM_ID'] == 3381)
                    ];
                }
            }
            return $res;
        };

        $resultItems[] = [
            'id' => $id,
            'name' => $val['NAME'],
            'products' => $allProductsModified[$id] ?? [],
            'statuses' => [
                'success' => $filterStatuses(3371),
                'error' => $filterStatuses(3372),
                'potential' => $filterStatuses(3524)
            ]
        ];
    }

    pwaSendJson([
        'company_id' => $dbDeal['COMPANY_ID'],
        'items' => $resultItems
    ]);
}

function saveInfopovods(int $dealId, int $userId, array $bodyData): void
{
    $dbDeal = DealTable::getList([
        'select' => ['COMPANY_ID', 'ASSIGNED_BY_ID'],
        'filter' => ['=ID' => $dealId]
    ])->fetch();

    if (!$dbDeal) {
        pwaSendError('Deal not found', 404);
    }

    $companyId = (int)($dbDeal['COMPANY_ID'] ?? 0);
    $items = $bodyData['items'] ?? [];

    if (!is_array($items)) {
        pwaSendError('Invalid data format', 400);
    }

    $hlblock = HighloadBlockTable::getById(VISIT_LOG_HLBLOCK_ID)->fetch();
    if (!$hlblock) {
        pwaSendError('HL block not found', 500);
    }

    $entity = HighloadBlockTable::compileEntity($hlblock);
    $hlDataClass = $entity->getDataClass();

    $processed = 0;
    $errors = [];

    foreach ($items as $item) {
        $infopovodId = (int)($item['id'] ?? 0);
        $statusId = (int)($item['status_id'] ?? 0);

        if ($infopovodId <= 0 || $statusId <= 0) continue;

        $hlData = [
            'UF_ID_INFOPOVOD' => $infopovodId,
            'UF_ID_CRM'       => $dealId,
            'UF_STATUS'       => $statusId,
            'UF_COMMENT'      => trim($item['comment'] ?? ''),
            'UF_ID_COMPANY'   => $companyId,
            'UF_MANAGER'      => $dbDeal['ASSIGNED_BY_ID'],
            'UF_DATETIME'     => new \Bitrix\Main\Type\DateTime()
        ];

        if (!empty($item['next_comm_date'])) {
            $hlData['UF_NEXT_COMM_DATE'] = trim($item['next_comm_date']);
        }
        if (!empty($item['phone_sms'])) {
            $hlData['UF_PHONE_SMS'] = trim($item['phone_sms']);
        }
        if (!empty($item['product_xml_id'])) {
            // Поле UF_PRODUCT_XML_ID (тип: строка) должно быть создано в HL-блоке 12 в Bitrix-администрировании
            $hlData['UF_PRODUCT_XML_ID'] = trim($item['product_xml_id']);
        }

        $hlResult = $hlDataClass::add($hlData);
        if (!$hlResult->isSuccess()) {
            $errors[] = "Ошибка для ID $infopovodId: " . implode(', ', $hlResult->getErrorMessages());
        } else {
            $processed++;
        }
    }

    if (!empty($errors)) {
        pwaSendError(implode('; ', $errors), 400);
    }

    pwaSendJson([
        'success' => true,
        'processed' => $processed
    ]);
}
