<?php
/**
 * Контроллер для работы с инфоповодами
 * GET  /api/visits/{id}/infopovods - Выдача структуры инфоповодов для UI
 * POST /api/visits/{id}/infopovods - Сохранение результатов в HL-блок "Результаты касаний с Партнером"
 *
 * Все ID (инфоблоки, HL-блок, поля) настраиваются в модуле:
 * Настройки → Модули → rocada.visits → таб «Инфоповоды».
 * Дефолты соответствуют историческим захардкоженным значениям.
 */

use \Bitrix\Main\Loader;
use \Bitrix\Main\Config\Option;
use \Bitrix\Crm\DealTable;
use \Bitrix\Highloadblock\HighloadBlockTable;

// Исторические дефолты (сохраняют поведение до выноса в настройки)
const RESULTS_IBLOCK_ID = 237;
const VISIT_LOG_HLBLOCK_ID = 12;

/**
 * Настройки блока «Инфоповоды» из Option модуля.
 * Пустая настройка = исторический дефолт, т.е. без настройки всё работает как раньше.
 */
function getInfopovodSettings(string $mid = 'rocada.visits'): array
{
    $opt = fn(string $key, string $default) => trim(Option::get($mid, $key, '')) ?: $default;

    $paymentTypesRaw = Option::get($mid, 'infopovod_payment_types', '');
    $paymentTypes = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $paymentTypesRaw))));

    return [
        // Инфоблоки и HL-блок
        'statuses_iblock_id'  => (int)$opt('infopovod_statuses_iblock_id', '236'),
        'products_iblock_id'  => (int)$opt('infopovod_products_iblock_id', '26'),
        'hlblock_id'          => (int)$opt('infopovod_hlblock_id', (string)VISIT_LOG_HLBLOCK_ID),
        // UF-поле сделки со ссылками на инфоповоды
        'deal_field'          => $opt('infopovod_deal_field', 'UF_CRM_1595504443'),
        // ID свойства TYPE_INFO в ИБ Инфоповодов (для сопоставления enum по XML_ID)
        'type_property_id'    => (int)$opt('infopovod_type_property_id', '2183'),
        // Enum-ID значений свойства STATUS у статусов завершения
        'status_enum_success'   => (int)$opt('infopovod_status_enum_success', '3371'),
        'status_enum_error'     => (int)$opt('infopovod_status_enum_error', '3372'),
        'status_enum_potential' => (int)$opt('infopovod_status_enum_potential', '3524'),
        // Enum-ID значения «Да» свойства COMMENT (обязательность комментария)
        'comment_enum_yes'      => (int)$opt('infopovod_comment_enum_yes', '3381'),
        // Маппинг динамических полей на UF-поля HL-блока (по ТЗ)
        'hl_field_next_comm_date' => $opt('infopovod_hl_field_next_comm_date', 'UF_DATA_SLEDUYUSHCHEY_KOMMUNIKATSII'),
        'hl_field_fio'            => $opt('infopovod_hl_field_fio', 'UF_FIO'),
        'hl_field_phone'          => $opt('infopovod_hl_field_phone', 'UF_PHONE'),
        'hl_field_payment_type'   => $opt('infopovod_hl_field_payment_type', 'UF_PAYMENT_TYPE'),
        'hl_field_product'        => $opt('infopovod_hl_field_product', 'UF_PRODUCT'),
        // Варианты «Формы оплаты» (по одному на строку в настройках)
        'payment_types'           => $paymentTypes,
    ];
}

/**
 * Свойство-флаг статуса завершения (ADD_DATE, FIO, PHONE и т.д.).
 * Считаем включённым любое непустое значение кроме явных «нет».
 * Работает и для чекбокса «да/нет», и для списка, и для строки.
 */
function pwaInfopovodFlag($value): bool
{
    if (is_array($value)) {
        $value = reset($value);
    }
    $value = trim((string)$value);
    if ($value === '') {
        return false;
    }
    return !in_array(mb_strtolower($value), ['n', 'no', 'нет', '0', 'false'], true);
}

/**
 * Загрузка статусов завершения из ИБ статусов со всеми свойствами динамических полей
 */
function pwaLoadInfopovodStatuses(array $cfg): array
{
    $rsStatus = \CIBlockElement::GetList([], ['IBLOCK_ID' => $cfg['statuses_iblock_id'], 'ACTIVE' => 'Y'], false, false, [
        'ID', 'NAME',
        'PROPERTY_TYPE_INFO', 'PROPERTY_STATUS', 'PROPERTY_COMMENT',
        'PROPERTY_PLACEHOLDER_TEXT', 'PROPERTY_ADD_DATE', 'PROPERTY_FIO',
        'PROPERTY_PHONE', 'PROPERTY_PAYMENT_TYPE', 'PROPERTY_LAUNCH_BP',
    ]);
    $statuses = [];
    while ($arStatus = $rsStatus->Fetch()) {
        $statuses[] = $arStatus;
    }
    return $statuses;
}

/**
 * Конфиг динамических полей одного статуса для UI и валидации
 */
function pwaStatusFieldsConfig(array $st, array $cfg): array
{
    return [
        'placeholder'  => trim((string)($st['PROPERTY_PLACEHOLDER_TEXT_VALUE'] ?? '')),
        'add_date'     => pwaInfopovodFlag($st['PROPERTY_ADD_DATE_VALUE'] ?? ''),
        'fio'          => pwaInfopovodFlag($st['PROPERTY_FIO_VALUE'] ?? ''),
        'phone'        => pwaInfopovodFlag($st['PROPERTY_PHONE_VALUE'] ?? ''),
        'payment_type' => pwaInfopovodFlag($st['PROPERTY_PAYMENT_TYPE_VALUE'] ?? ''),
        'launch_bp'    => pwaInfopovodFlag($st['PROPERTY_LAUNCH_BP_VALUE'] ?? ''),
        'is_comment_required' => ((int)($st['PROPERTY_COMMENT_ENUM_ID'] ?? 0) === $cfg['comment_enum_yes']),
    ];
}

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

    $cfg = getInfopovodSettings($params['moduleId'] ?? 'rocada.visits');

    if ($params['method'] === 'GET') {
        getInfopovods($dealId, $cfg);
    } elseif ($params['method'] === 'POST') {
        saveInfopovods($dealId, $userId, $params['body'] ?? [], $cfg);
    } else {
        pwaSendError('Method Not Allowed', 405);
    }
}

function getInfopovods(int $dealId, array $cfg): void
{
    $dealField = $cfg['deal_field'];

    $dbDeal = DealTable::getList([
        'select' => ['COMPANY_ID', 'ASSIGNED_BY_ID', $dealField],
        'filter' => ['=ID' => $dealId]
    ])->fetch();

    if (!$dbDeal) {
        pwaSendError('Deal not found', 404);
    }

    $infopovodIds = $dbDeal[$dealField] ?? null;
    if (empty($infopovodIds) || !is_array($infopovodIds)) {
        pwaSendJson(['items' => [], 'payment_types' => $cfg['payment_types']]);
    }

    // 1. Получаем сами инфоповоды (по ID — уникальны в рамках системы)
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
        $rsProd = \CIBlockElement::GetList([], ['IBLOCK_ID' => $cfg['products_iblock_id'], 'ID' => array_unique($allProductsIds)], false, false, ['ID', 'XML_ID', 'NAME']);
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

    // 3. Допустимые статусы завершения (со свойствами динамических полей)
    $statuses = pwaLoadInfopovodStatuses($cfg);

    // PROPERTY_TYPE_INFO enum (ИБ Инфоповоды)
    $property_enums = \CIBlockPropertyEnum::GetList([], ["PROPERTY_ID" => $cfg['type_property_id']]);
    $infopovodTypes = [];
    while ($enum_fields = $property_enums->GetNext()) {
        $infopovodTypes[$enum_fields['XML_ID']] = $enum_fields["ID"];
    }

    // Собираем результат
    $resultItems = [];
    foreach ($infopovods as $id => $val) {
        $typeEnumIdStatus = $infopovodTypes[$val['PROPERTY_TYPE_INFO_ENUM_ID']] ?? null;

        $filterStatuses = function($statusEnumId) use ($statuses, $typeEnumIdStatus, $cfg) {
            $res = [];
            foreach ($statuses as $st) {
                if ($st['PROPERTY_TYPE_INFO_ENUM_ID'] == $typeEnumIdStatus && $st['PROPERTY_STATUS_ENUM_ID'] == $statusEnumId) {
                    $fieldsCfg = pwaStatusFieldsConfig($st, $cfg);
                    $res[] = [
                        'id' => $st['ID'],
                        'name' => $st['NAME'],
                        'is_comment_required' => $fieldsCfg['is_comment_required'],
                        'placeholder' => $fieldsCfg['placeholder'],
                        'fields' => [
                            'add_date'     => $fieldsCfg['add_date'],
                            'fio'          => $fieldsCfg['fio'],
                            'phone'        => $fieldsCfg['phone'],
                            'payment_type' => $fieldsCfg['payment_type'],
                            'launch_bp'    => $fieldsCfg['launch_bp'],
                        ],
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
                'success' => $filterStatuses($cfg['status_enum_success']),
                'error' => $filterStatuses($cfg['status_enum_error']),
                'potential' => $filterStatuses($cfg['status_enum_potential'])
            ]
        ];
    }

    pwaSendJson([
        'company_id' => $dbDeal['COMPANY_ID'],
        'items' => $resultItems,
        'payment_types' => $cfg['payment_types'],
    ]);
}

function saveInfopovods(int $dealId, int $userId, array $bodyData, array $cfg): void
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

    $hlblock = HighloadBlockTable::getById($cfg['hlblock_id'])->fetch();
    if (!$hlblock) {
        pwaSendError('HL block not found', 500);
    }

    $entity = HighloadBlockTable::compileEntity($hlblock);
    $hlDataClass = $entity->getDataClass();
    // Существующие поля HL-блока — пишем только в них, чтобы опечатка
    // в настройках маппинга не ломала сохранение целиком
    $hlFields = array_keys($entity->getFields());

    // Конфиги статусов для серверной валидации обязательных полей
    $statusesCfg = [];
    foreach (pwaLoadInfopovodStatuses($cfg) as $st) {
        $statusesCfg[(int)$st['ID']] = pwaStatusFieldsConfig($st, $cfg) + ['name' => $st['NAME']];
    }

    $processed = 0;
    $errors = [];

    foreach ($items as $item) {
        $infopovodId = (int)($item['id'] ?? 0);
        $statusId = (int)($item['status_id'] ?? 0);

        if ($infopovodId <= 0 || $statusId <= 0) continue;

        $stCfg = $statusesCfg[$statusId] ?? null;

        // Серверная валидация обязательных полей по конфигу статуса
        if ($stCfg) {
            $stName = $stCfg['name'] ?? $statusId;
            if ($stCfg['is_comment_required'] && trim($item['comment'] ?? '') === '') {
                $errors[] = "Статус «{$stName}»: комментарий обязателен";
                continue;
            }
            if ($stCfg['fio'] && trim($item['fio'] ?? '') === '') {
                $errors[] = "Статус «{$stName}»: поле «ФИО» обязательно";
                continue;
            }
            if ($stCfg['phone'] && trim($item['phone'] ?? '') === '') {
                $errors[] = "Статус «{$stName}»: поле «Номер телефона» обязательно";
                continue;
            }
        }

        $hlData = [
            'UF_ID_INFOPOVOD' => $infopovodId,
            'UF_ID_CRM'       => $dealId,
            'UF_STATUS'       => $statusId,
            'UF_COMMENT'      => trim($item['comment'] ?? ''),
            'UF_ID_COMPANY'   => $companyId,
            'UF_MANAGER'      => $dbDeal['ASSIGNED_BY_ID'],
            'UF_DATETIME'     => new \Bitrix\Main\Type\DateTime()
        ];

        // Динамические поля по ТЗ → настроенные UF-поля HL-блока (строковые)
        $dynamicMap = [
            'next_comm_date' => $cfg['hl_field_next_comm_date'],
            'fio'            => $cfg['hl_field_fio'],
            'phone'          => $cfg['hl_field_phone'],
            'payment_type'   => $cfg['hl_field_payment_type'],
            'product'        => $cfg['hl_field_product'],
        ];
        foreach ($dynamicMap as $bodyKey => $hlField) {
            $value = trim((string)($item[$bodyKey] ?? ''));
            if ($value !== '' && !empty($hlField)) {
                $hlData[$hlField] = $value;
            }
        }

        // Legacy-ключи старого фронта — сохраняем обратную совместимость
        if (!empty($item['phone_sms'])) {
            $hlData['UF_PHONE_SMS'] = trim($item['phone_sms']);
        }
        if (!empty($item['product_xml_id'])) {
            $hlData['UF_PRODUCT_XML_ID'] = trim($item['product_xml_id']);
        }

        // Отбрасываем поля, которых нет в HL-блоке (не создано UF-поле / опечатка в настройках)
        $hlData = array_intersect_key($hlData, array_flip($hlFields));

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
