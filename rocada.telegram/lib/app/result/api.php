<?php
// Отключаем вывод штатного хидера и футера Bitrix
define("NOT_NEED_HEADER", true);
define("NOT_NEED_FOOTER", true);
define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_STATISTIC", true);
define("NO_AGENT_CHECK", true);
define("NOT_CHECK_PERMISSIONS", true);

// Подключаем ядро Bitrix в режиме "пролог до"
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Config\Option;

define("BOT_TOKEN", Option::get('rocada.telegram', 'telegram_bot_token', ''));

/**
 * Проверяет данные, полученные от Telegram, и авторизует пользователя.
 * @param string $initData Строка initData из Telegram Web App.
 * @return array|null Массив с данными пользователя Telegram или null в случае ошибки.
 */
function verifyAndAuthorizeUser(string $initData): ?array {
    $initDataParts = [];
    parse_str($initData, $initDataParts);

    if (!isset($initDataParts['hash'], $initDataParts['user'])) {
        sendJsonResponse(['success' => false, 'message' => 'Ошибка: Некорректные данные для верификации.']);
        return null;
    }

    $hash = $initDataParts['hash'];
    $userData = json_decode($initDataParts['user'], true);
    unset($initDataParts['hash']);
    ksort($initDataParts);

    $dataCheckString = urldecode(http_build_query($initDataParts, '', "\n"));
    $secretKey = hash_hmac('sha256', BOT_TOKEN, 'WebAppData', true);
    $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

    if ($calculatedHash !== $hash) {
        sendJsonResponse(['success' => false, 'message' => 'Ошибка: Верификация данных не удалась.']);
        return null;
    }

    // Проверка, что данные не устарели (например, 1 час)
    if (time() - $initDataParts['auth_date'] > 3600) {
        sendJsonResponse(['success' => false, 'message' => 'Ошибка: Сессия истекла. Перезапустите приложение.']);
        return null;
    }

    // Авторизация пользователя в Bitrix
    global $USER;
    if (!$USER->IsAuthorized()) {
        $telegramId = $userData['id'];
        $rsUser = CUser::GetList(($by = "id"), ($order = "desc"), ["UF_ROCADOMED_TELEGEAM_ID" => $telegramId]);
        if ($arUser = $rsUser->Fetch()) {
            $USER->Authorize($arUser['ID']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Пользователь с таким Telegram ID не найден в системе.']);
            return null;
        }
    }

    return $userData;
}

/**
 * Отправляет ответ в формате JSON и завершает выполнение скрипта.
 * @param array $data Данные для отправки.
 */
function sendJsonResponse(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data);
    die();
}

// --- Основная логика API ---

// Получаем POST-данные
$requestData = json_decode(file_get_contents('php://input'), true);
if (!$requestData || !isset($requestData['action'], $requestData['initData'])) {
    sendJsonResponse(['success' => false, 'message' => 'Некорректный запрос.']);
}

// Верификация и авторизация
$telegramUser = verifyAndAuthorizeUser($requestData['initData']);
if (!$telegramUser) {
    // Сообщение об ошибке уже отправлено внутри функции
    return;
}

// Подключаем необходимые модули
CModule::IncludeModule('crm');
CModule::IncludeModule('iblock');

// Роутинг по действиям
switch ($requestData['action']) {
    case 'getData':
        handleGetData($requestData);
        break;
    case 'getStatusInfo':
        handleGetStatusInfo($requestData);
        break;
    case 'saveData':
        handleSaveData($requestData);
        break;
    default:
        sendJsonResponse(['success' => false, 'message' => 'Неизвестное действие.']);
}


// --- Обработчики действий ---

/**
 * Обработчик для получения начальных данных для формы.
 * @param array $requestData
 */
function handleGetData(array $requestData): void {
    $dealId = (int)$requestData['dealId'];
    if ($dealId <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Некорректный ID сделки.']);
    }

    $dbDeal = Bitrix\Crm\DealTable::getList([
        'select' => ['COMPANY_ID', 'UF_CRM_1595504443'],
        'filter' => ['=ID' => $dealId]
    ])->fetch();

    if (!$dbDeal) {
        sendJsonResponse(['success' => false, 'message' => 'Сделка не найдена.']);
    }

    $infopovodIds = $dbDeal['UF_CRM_1595504443'];
    if (empty($infopovodIds)) {
        sendJsonResponse(['success' => false, 'message' => 'В сделке не найдены инфоповоды.']);
    }
    
    // ... (Здесь большая часть вашего кода по получению данных)
    // Я адаптировал его для сборки в единый массив
    
    // Получаем инфоповоды
    $rsIblock = CIBlockElement::GetList([], ['IBLOCK_ID' => Infopovods::IBLOCK_ID, 'ID' => $infopovodIds], false, false, ['ID', 'NAME', 'PROPERTY_TYPE_INFO']);
    $infopovods = [];
    while ($arIblock = $rsIblock->Fetch()) {
        $infopovods[$arIblock['ID']] = $arIblock;
    }

    // Получаем продукты, связанные с инфоповодами
    $rsIblock = CIBlockElement::GetList([], ['IBLOCK_ID' => Infopovods::IBLOCK_ID, 'ID' => $infopovodIds]);
    $allProductsLinks = [];
    $allProductsIds = [];
    while ($arIblock = $rsIblock->GetNextElement()) {
        $fields = $arIblock->GetFields();
        $props = $arIblock->GetProperties();
        if (!empty($props['PRODUCT']['VALUE']) && is_array($props['PRODUCT']['VALUE'])) {
            $allProductsLinks[$fields['ID']] = $props['PRODUCT']['VALUE'];
            $allProductsIds = array_merge($allProductsIds, $props['PRODUCT']['VALUE']);
        }
    }
    $allProductsIds = array_unique($allProductsIds);

    // Получаем информацию о самих продуктах
    $products = [];
    if (!empty($allProductsIds)) {
        $rsIblock = CIBlockElement::GetList([], ['IBLOCK_ID' => 26, 'ID' => $allProductsIds], false, false, ['ID', 'XML_ID', 'NAME']);
        while ($arIblock = $rsIblock->Fetch()) {
            $products[$arIblock['ID']] = $arIblock;
        }
    }

    // Собираем продукты в удобную структуру
    $allProductsModified = [];
    foreach ($allProductsLinks as $infopovodId => $productIds) {
        foreach ($productIds as $productId) {
            if (isset($products[$productId])) {
                $allProductsModified[$infopovodId][] = ['xml_id' => $products[$productId]['XML_ID'], 'name' => $products[$productId]['NAME']];
            }
        }
    }

    // Получаем все возможные статусы
    $rsStatus = CIBlockElement::GetList([], ['IBLOCK_ID' => 236, 'ACTIVE' => 'Y'], false, false, ['ID', 'NAME', 'PROPERTY_TYPE_INFO', 'PROPERTY_STATUS', 'PROPERTY_COMMENT']);
    $statuses = [];
    while ($arStatus = $rsStatus->Fetch()) {
        $statuses[$arStatus['ID']] = $arStatus;
    }

    $property_enums = CIBlockPropertyEnum::GetList([], ["PROPERTY_ID" => 2183]);
    $infopovodTypes = [];
    while ($enum_fields = $property_enums->GetNext()) {
        $infopovodTypes[$enum_fields['XML_ID']] = $enum_fields["ID"];
    }

    // Собираем финальный массив данных
    $responseData = [
        'dealId' => $dealId,
        'companyId' => $dbDeal['COMPANY_ID'],
        'infopovods' => []
    ];

    foreach ($infopovods as $id => $value) {
        $typeEnumIdStatus = $infopovodTypes[$value['PROPERTY_TYPE_INFO_ENUM_ID']] ?? null;

        $filterStatuses = function($statusType) use ($statuses, $typeEnumIdStatus) {
            $result = [];
            $statusMap = [ 'success' => 3371, 'error' => 3372, 'potential' => 3524 ];
            $statusEnumId = $statusMap[$statusType];

            foreach($statuses as $status) {
                if ($status['PROPERTY_TYPE_INFO_ENUM_ID'] == $typeEnumIdStatus && $status['PROPERTY_STATUS_ENUM_ID'] == $statusEnumId) {
                     $result[] = [
                        'id' => $status['ID'],
                        'name' => $status['NAME'],
                        'is_comment_required' => ($status['PROPERTY_COMMENT_ENUM_ID'] == 3381)
                    ];
                }
            }
            return $result;
        };

        $responseData['infopovods'][] = [
            'id' => $id,
            'name' => $value['NAME'],
            'products' => $allProductsModified[$id] ?? [],
            'statuses' => [
                'success' => $filterStatuses('success'),
                'error' => $filterStatuses('error'),
                'potential' => $filterStatuses('potential')
            ]
        ];
    }

    sendJsonResponse(['success' => true, 'data' => $responseData]);
}

/**
 * Возвращает информацию о конкретном статусе.
 * @param array $requestData
 */
function handleGetStatusInfo(array $requestData): void {
    $statusId = (int)$requestData['statusId'];
    if ($statusId <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Некорректный ID статуса.']);
    }
    
    $res = CIBlockElement::GetList([], ["ID" => $statusId, "IBLOCK_ID" => 236], false, false, ["ID", "IBLOCK_ID"]);
    $element = $res->GetNextElement();
    if (!$element) {
        sendJsonResponse(['success' => false, 'message' => 'Статус не найден.']);
    }

    $props = $element->GetProperties();
    $result = [
        'success' => true,
        'data' => [
            'placeholder' => $props['PLACEHOLDER']['VALUE'] ?: 'Важна любая обратная связь',
            'commentRequired' => $props['COMMENT']['VALUE_XML_ID'] === 'Y',
            'needCommunicationDate' => $props['NEED_COMMUNICATION_DATE']['VALUE_XML_ID'] === 'Y',
            'smsSend' => $props['SMS_SEND']['VALUE_XML_ID'] === 'Y',
            'launchBP' => $props['LAUNCH_BP']['VALUE_XML_ID'] === 'Y',
        ]
    ];
    
    sendJsonResponse($result);
}


/**
 * Сохраняет данные из формы.
 * @param array $requestData
 */
function handleSaveData(array $requestData): void {
    // Здесь должна быть логика, аналогичная вашему ajax/add.php
    // Она будет принимать $requestData['formData'] и обрабатывать его.
    // Для примера просто вернем успех.
    
    $formData = $requestData['formData'];
    // TODO: Реализуйте здесь логику сохранения данных в Bitrix,
    // используя данные из $formData.
    // Например, создание элементов инфоблока, запуск бизнес-процессов и т.д.
    
    // Пример доступа к данным:
    // $dealId = $formData['deal'];
    // foreach ($formData as $key => $value) {
    //     if (strpos($key, 'kasatka-status-') === 0) {
    //         $infopovodId = str_replace('kasatka-status-', '', $key);
    //         $statusId = $value;
    //         $comment = $formData['kasatka-comment-' . $infopovodId];
    //         // ... ваша логика сохранения
    //     }
    // }

    sendJsonResponse(['success' => true, 'message' => 'Результаты успешно сохранены!']);
}

