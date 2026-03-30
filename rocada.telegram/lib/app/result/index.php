<?php
define("NOT_NEED_HEADER", true);
define("NOT_NEED_FOOTER", true);
define("NO_AGENT_CHECK", true);
define("NOT_CHECK_PERMISSIONS", true);
define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_STATISTIC", true);
define("STOP_STATISTICS", true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Config\Option;
use Bitrix\Crm\DealTable;
use Bitrix\Highloadblock\HighloadBlockTable;

// --- НАСТРОЙКИ ---
const UF_TELEGRAM_ID_FIELD = 'UF_ROCADOMED_TELEGEAM_ID';
const RESULTS_IBLOCK_ID = 237;
const VISIT_LOG_HLBLOCK_ID = 12;

// Add a debug mode flag
const DEBUG_MODE = true;

// --- AJAX РОУТЕР ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    function sendJsonResponse(array $data): void {
        header('Content-Type: application/json');
        // Add debug logging
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Sending JSON response: " . print_r($data, true));
        }
        echo json_encode($data);
        die();
    }

    global $USER;
    // In debug mode, we might want to skip authorization in browser
    if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
        if (!$USER->IsAuthorized()) {
            sendJsonResponse(['success' => false, 'message' => 'Ошибка авторизации.']);
        }
    }

    CModule::IncludeModule('iblock');
    CModule::IncludeModule('highloadblock');

    // Log incoming request for debugging
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("POST request received: " . print_r($_POST, true));
        error_log("USER authorized: " . ($USER->IsAuthorized() ? 'Yes' : 'No'));
        error_log("USER ID: " . $USER->GetID());
    }

    switch ($_POST['action']) {
        case 'get_status_info':
            $statusId = (int)$_POST['id'];
            if ($statusId <= 0) sendJsonResponse(['success' => false, 'message' => 'Некорректный ID статуса.']);

            $res = CIBlockElement::GetList([], ["ID" => $statusId, "IBLOCK_ID" => 236], false, false, ["ID", "IBLOCK_ID"]);
            $element = $res->GetNextElement();
            if (!$element) sendJsonResponse(['success' => false, 'message' => 'Статус не найден.']);

            $props = $element->GetProperties();
            sendJsonResponse([
                'success' => true,
                'placeholder' => $props['PLACEHOLDER']['VALUE'] ?: 'Важна любая обратная связь',
                'commentRequired' => $props['COMMENT']['VALUE_XML_ID'] === 'Y',
                'needCommunicationDate' => $props['NEED_COMMUNICATION_DATE']['VALUE_XML_ID'] === 'Y',
                'smsSend' => $props['SMS_SEND']['VALUE_XML_ID'] === 'Y',
                'launchBP' => $props['LAUNCH_BP']['VALUE_XML_ID'] === 'Y',
            ]);
            break;

        case 'add_result':
            $el = new CIBlockElement;
            $dealId = (int)$_POST['deal'];
            $deal = \Bitrix\Crm\DealTable::getList([
                'filter' => [
                    'ID' => $dealId
                ],
                'select' => ['COMPANY_ID', 'UF_CRM_1595504443', 'ASSIGNED_BY_ID']
            ])->fetch();
            $companyId = (int)($deal['COMPANY_ID'] ?? 0);
            $userId = $USER->GetID();
            $errors = [];

            // Log the received data for debugging
            if (DEBUG_MODE) {
                error_log("Received POST data: " . print_r($_POST, true));
                error_log("Deal data: " . print_r($deal, true));
            }

            $processedCount = 0;
            
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'id-info-') === 0) {
                    $infopovodId = (int)$value;
                    $statusId = (int)($_POST["kasatka-status-{$infopovodId}"] ?? 0);

                    if ($statusId === 0) {
                        if (DEBUG_MODE) {
                            error_log("Skipping infopovod ID: $infopovodId - invalid status ID");
                        }
                        continue;
                    }

                    $processedCount++;
                    
                    // Add logging for each infopovod
                    if (DEBUG_MODE) {
                        error_log("Processing infopovod ID: $infopovodId, Status ID: $statusId");
                    }

                    $hlblock = HighloadBlockTable::getById(VISIT_LOG_HLBLOCK_ID)->fetch();
                    if ($hlblock) {
                        $entity = HighloadBlockTable::compileEntity($hlblock);
                        $hlDataClass = $entity->getDataClass();

                        $comment = trim($_POST["kasatka-comment-{$infopovodId}"] ?? '');
                        $nextCommDate = trim($_POST["kasatka-date-communication-{$infopovodId}"] ?? '');
                        $phoneSms = trim($_POST["kasatka-phone-{$infopovodId}"] ?? '');
                        $productXmlId = trim($_POST["kasatka-product-{$infopovodId}"] ?? '');

                        $hlData = [
                            'UF_ID_INFOPOVOD' => $infopovodId,
                            'UF_ID_CRM'       => $dealId,
                            'UF_STATUS'       => $statusId,
                            'UF_COMMENT'      => $comment,
                            'UF_ID_COMPANY'   => $companyId,
                            'UF_MANAGER'      => $deal['ASSIGNED_BY_ID'],
                            'UF_DATETIME'     => new \Bitrix\Main\Type\DateTime()
                        ];

                        // Add optional fields if they exist
                        if (!empty($nextCommDate)) {
                            $hlData['UF_NEXT_COMM_DATE'] = $nextCommDate;
                        }
                        if (!empty($phoneSms)) {
                            $hlData['UF_PHONE_SMS'] = $phoneSms;
                        }
                        if (!empty($productXmlId)) {
                            $hlData['UF_PRODUCT_XML_ID'] = $productXmlId;
                        }

                        if (DEBUG_MODE) {
                            error_log("HL Data to save: " . print_r($hlData, true));
                        }

                        $hlResult = $hlDataClass::add($hlData);
                        if (!$hlResult->isSuccess()) {
                            $errorMessages = $hlResult->getErrorMessages();
                            $errors[] = "Ошибка сохранения в Highload блок для инфоповода ID {$infopovodId}: " . implode(", ", $errorMessages);
                            if (DEBUG_MODE) {
                                error_log("HL Block error for infopovod ID $infopovodId: " . implode(", ", $errorMessages));
                            }
                        } else {
                            if (DEBUG_MODE) {
                                error_log("Successfully saved HL record for infopovod ID: $infopovodId");
                            }
                        }
                    } else {
                        $errors[] = "Highload блок не найден";
                        if (DEBUG_MODE) {
                            error_log("HL Block not found");
                        }
                    }
                }
            }

            if (DEBUG_MODE) {
                error_log("Processed $processedCount infopovods");
            }

            // If no infopovods were processed, it's still a success
            if (empty($errors)) {
                sendJsonResponse(['success' => true, 'message' => 'Результаты успешно сохранены!', 'processed' => $processedCount]);
            } else {
                sendJsonResponse(['success' => false, 'message' => implode("\n", $errors), 'processed' => $processedCount]);
            }
            break;
    }

    sendJsonResponse(['success' => false, 'message' => 'Неизвестное действие.']);
}

// --- ФУНКЦИЯ АВТОРИЗАЦИИ ---
function verifyTelegramAuth(string $auth_data_string): bool {
    if (empty($auth_data_string)) return false;

    if (!CModule::IncludeModule('rocada.telegram')) return false;
    $bot_token = Option::get('rocada.telegram', 'telegram_bot_token', '');

    if (empty($bot_token)) return false;

    $auth_data = [];
    parse_str($auth_data_string, $auth_data);

    if (!isset($auth_data['hash'], $auth_data['user'])) return false;

    $check_hash = $auth_data['hash'];
    $user_data = json_decode($auth_data['user'], true);
    unset($auth_data['hash']);
    ksort($auth_data);

    $data_check_string = [];
    foreach ($auth_data as $k => $v) {
        $data_check_string[] = $k . '=' . $v;
    }
    $data_check_string = implode("\n", $data_check_string);

    $secret_key = hash_hmac('sha256', $bot_token, 'WebAppData', true);
    $hash = hash_hmac('sha256', $data_check_string, $secret_key);

    if (strcmp($hash, $check_hash) !== 0) return false;
    if ((time() - $auth_data['auth_date']) > 3600) return false;

    global $USER;
    $telegramId = $user_data['id'];
    $rsUser = CUser::GetList(($by = "id"), ($order = "desc"), ["UF_TELEGRAM_ID_FIELD" => $telegramId]);

    if ($arUser = $rsUser->Fetch()) {
        $USER->Authorize($arUser['ID']);
        return true;
    }

    return false;
}

// --- НАЧАЛО ЗАГРУЗКИ СТРАНИЦЫ ---
$isTgAuthSuccess = verifyTelegramAuth($_GET['tgWebAppInitData'] ?? $_POST['tgWebAppInitData'] ?? '');

global $USER;
// In debug mode, allow access without Telegram auth for browser testing
if (DEBUG_MODE) {
    // Check if we're running in a browser (not Telegram)
    if (!isset($_GET['tgWebAppInitData']) && !isset($_POST['tgWebAppInitData'])) {
        // For browser testing, we can simulate a logged-in user
        // This is just for debugging purposes
        if (!$USER->IsAuthorized()) {
            // You might want to manually set a user ID for testing
            // $USER->Authorize(1); // Uncomment and set a valid user ID for testing
            error_log("Debug mode: User not authorized, but allowing access for testing");
        }
    } else {
        // Telegram mode, require proper auth
        if (!$isTgAuthSuccess && !$USER->IsAuthorized()) {
            ?>
            <!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Загрузка...</title><script src="https://telegram.org/js/telegram-web-app.js"></script><script src="https://cdn.tailwindcss.com"></script></head><body></body><script>
                window.Telegram.WebApp.ready();
                const tg = window.Telegram.WebApp;
                const urlParams = new URLSearchParams(window.location.search);
                if (!urlParams.has('tgWebAppInitData') && tg.initData) {
                    const url = window.location.href.split('#')[0]
                    window.location.href = url + '&tgWebAppInitData='='encodeURIComponent(tg.initData);
                    window.location.replace(window.location.href);
                } else {
                    document.body.innerHTML = '<div class="flex items-center justify-center h-screen bg-gray-100 dark:bg-gray-900"><div class="text-center p-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg"><h1 class="text-2xl font-bold text-red-600 dark:text-red-400">Доступ запрещен</h1><p class="mt-2 text-gray-700 dark:text-gray-300">Ошибка верификации пользователя Telegram или ваш аккаунт не привязан к системе.</p></div></div>';
                }
            </script></html>
            <?php
            require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
            die();
        }

    }
} else {
    // Production mode - require proper Telegram auth
    if (!$isTgAuthSuccess && !$USER->IsAuthorized()) {
        ?>
        <!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Загрузка...</title><script src="https://telegram.org/js/telegram-web-app.js"></script><script src="https://cdn.tailwindcss.com"></script></head><body></body><script>
            window.Telegram.WebApp.ready();
            const tg = window.Telegram.WebApp;
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('tgWebAppInitData') && tg.initData) {
                const url = window.location.href.split('#')[0]
                window.location.href = url + '&tgWebAppInitData='='encodeURIComponent(tg.initData);
                window.location.replace(window.location.href);
            } else {
                document.body.innerHTML = '<div class="flex items-center justify-center h-screen bg-gray-100 dark:bg-gray-900"><div class="text-center p-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg"><h1 class="text-2xl font-bold text-red-600 dark:text-red-400">Доступ запрещен</h1><p class="mt-2 text-gray-700 dark:text-gray-300">Ошибка верификации пользователя Telegram или ваш аккаунт не привязан к системе.</p></div></div>';
            }
        </script></html>
        <?php
        require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
        die();
    }

}

CModule::IncludeModule('crm');
CModule::IncludeModule('iblock');

$startParam = $_GET['tgWebAppStartParam'] ?? $_GET['id'] ?? '';
$deal_id_parts = explode("_", htmlspecialcharsbx($startParam));
$id_deal = (int)($deal_id_parts[1] ?? 0);

if ($id_deal <= 0) {
    echo "<p>Ошибка: ID сделки не передан или имеет неверный формат.</p>";
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
    die();
}

// --- НАЧАЛО БЛОКА: ОБНОВЛЕНИЕ СТАДИИ СДЕЛКИ ---
$stageId = isset($_GET['stage_id']) ? htmlspecialcharsbx(trim($_GET['stage_id'])) : null;
if ($id_deal > 0 && !empty($stageId)) {
    // Подключаем модуль CRM, если он еще не подключен
    if (CModule::IncludeModule('crm')) {
        $deal = new CCrmDeal(false); // false - не проверять права, т.к. мы уже авторизованы через Telegram
        $updateFields = ['STAGE_ID' => $stageId];
        // Обновляем сделку и не прерываем выполнение, даже если есть ошибка,
        // чтобы пользователь в любом случае увидел форму для заполнения
        $deal->Update($id_deal, $updateFields, ['ENABLE_USER_FIELD_CHECK' => false]);
    }
}
// --- КОНЕЦ БЛОКА: ОБНОВЛЕНИЕ СТАДИИ СДЕЛКИ ---

$userId = $USER->GetID();
$dbDeal = DealTable::getList(['select' => ['COMPANY_ID', 'UF_CRM_1595504443'],'filter' => ['=ID' => $id_deal]])->fetch();
$company = $dbDeal['COMPANY_ID'];
$infopovodIds = $dbDeal['UF_CRM_1595504443'];
$infopovodInfo = [];

if(!empty($infopovodIds)) {
    $rsIblock = CIBlockElement::GetList([], ['IBLOCK_ID' => Infopovods::IBLOCK_ID, 'ID' => $infopovodIds], false, false, ['ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_TYPE_INFO']);
    $infopovods = [];
    while ($arIblock = $rsIblock->Fetch()) $infopovods[$arIblock['ID']] = $arIblock;

    $rsIblock = CIBlockElement::GetList([], ['IBLOCK_ID' => Infopovods::IBLOCK_ID, 'ID' => $infopovodIds]);
    $allProducts = []; $allProducts1 = [];
    while ($arIblock = $rsIblock->GetNextElement()) {
        $fields = $arIblock->GetFields(); $props = $arIblock->GetProperties();
        $allProducts[$fields['ID']] = $props['PRODUCT']['VALUE'];
        if(is_array($props['PRODUCT']['VALUE'])) $allProducts1 = array_merge($props['PRODUCT']['VALUE'], $allProducts1);
    }

    $products = [];
    if(!empty($allProducts1)) {
        $rsIblock = CIBlockElement::GetList([], ['IBLOCK_ID' => 26, 'ID' => array_unique($allProducts1)], false, false, ['ID', 'IBLOCK_ID', 'XML_ID', 'NAME']);
        while ($arIblock = $rsIblock->Fetch()) $products[$arIblock['ID']] = $arIblock;
    }

    $allProductsModified = [];
    foreach ($allProducts as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $value1) {
                if(array_key_exists($value1, $products)) $allProductsModified[$key][] = ['XML_ID' => $products[$value1]['XML_ID'], 'NAME' => $products[$value1]['NAME']];
            }
        }
    }

    $rsStatus = CIBlockElement::GetList([], ['IBLOCK_ID' => 236, 'ACTIVE' => 'Y'], false, false, ['ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_TYPE_INFO', 'PROPERTY_STATUS', 'PROPERTY_COMMENT']);
    $statuses = [];
    while ($arStatus = $rsStatus->Fetch()) $statuses[$arStatus['ID']] = $arStatus;

    $property_enums = CIBlockPropertyEnum::GetList([], ["PROPERTY_ID" => 2183]);
    $infopovodTypes = [];
    while($enum_fields = $property_enums->GetNext()) $infopovodTypes[$enum_fields['XML_ID']] = $enum_fields["ID"];

    foreach ($infopovods as $id => $value) {
        $typeEnumIdStatus = $infopovodTypes[$value['PROPERTY_TYPE_INFO_ENUM_ID']] ?? null;
        $infopovodInfo[$id] = [
            'NAME' => $value['NAME'],
            'STATUS_SUCCESS' => array_filter($statuses, fn($val) => $val['PROPERTY_TYPE_INFO_ENUM_ID'] == $typeEnumIdStatus && $val['PROPERTY_STATUS_ENUM_ID'] == 3371),
            'STATUS_ERROR' => array_filter($statuses, fn($val) => $val['PROPERTY_TYPE_INFO_ENUM_ID'] == $typeEnumIdStatus && $val['PROPERTY_STATUS_ENUM_ID'] == 3372),
            'POTENTIAL_STATUS' => array_filter($statuses, fn($val) => $val['PROPERTY_TYPE_INFO_ENUM_ID'] == $typeEnumIdStatus && $val['PROPERTY_STATUS_ENUM_ID'] == 3524)
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Результаты по Инфоповодам</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        :root { color-scheme: light dark; }
        body {
            background-color: var(--tg-theme-bg-color, #f3f4f6);
            color: var(--tg-theme-text-color, #111827);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            padding: 16px;
            padding-bottom: 80px;
        }
        .card {
            background-color: var(--tg-theme-secondary-bg-color, #ffffff);
            border-radius: 12px; padding: 16px; margin-bottom: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .form-select, .form-textarea, .form-input {
            width: 100%;
            border: 1px solid var(--tg-theme-hint-color, #d1d5db);
            background-color: var(--tg-theme-bg-color, #ffffff);
            color: var(--tg-theme-text-color, #000000);
            border-radius: 8px; padding: 10px; font-size: 16px;
        }
        .form-select:focus, .form-textarea:focus, .form-input:focus {
            outline: none;
            border-color: var(--tg-theme-button-color, #3b82f6);
            box-shadow: 0 0 0 2px var(--tg-theme-button-color, #3b82f6);
        }
        .label { display: block; font-weight: 600; margin-bottom: 6px; }
        .form-error { border-color: #ef4444 !important; }
        .hidden { display: none !important; }
        /* Styles for Voice Input Block */
        #recording-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(0,0,0,0.7);
            z-index: 100;
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            color: white;
        }
        #recording-timer { font-size: 2.5rem; margin-bottom: 20px; }
        .recording-dot {
            height: 12px; width: 12px;
            background-color: #ef4444; border-radius: 50%;
            display: inline-block;
            animation: recording-blink 1.5s infinite;
            margin-right: 8px;
        }
        @keyframes recording-blink {
            0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; }
        }
        #recording-controls button {
             width: 120px; margin: 0 10px;
             display: inline-flex; justify-content: center; align-items: center;
             padding: 12px; border-radius: 8px; font-weight: 600;
             color: #ffffff; cursor: pointer; border: none;
        }
        .btn-stop { background-color: #22c55e; }
        .btn-cancel { background-color: #ef4444; }
    </style>
</head>
<body>
    <div id="main-content">
        <h1 class="text-2xl font-bold mb-2">Результаты по Инфоповодам</h1>
        <p class="mb-4"><a href="/crm/deal/details/<?=$id_deal?>/" target="_blank" class="text-sm" style="color: var(--tg-theme-link-color, #3b82f6);">Ссылка на сделку #<?=$id_deal?></a></p>
        <? if (empty($infopovodInfo)): ?>
            <div class="card text-center"><p>В данной сделке не найдены инфоповоды для заполнения.</p></div>
        <? else: ?>
            <div class="card">
                <h2 class="text-xl font-bold mb-2">Голосовой ввод</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Нажмите на микрофон, чтобы начать запись, и продиктуйте результаты для всех инфоповодов. Система автоматически заполнит поля.</p>
                <button type="button" id="record-btn" class="w-full flex items-center justify-center p-4 rounded-lg" style="background-color: var(--tg-theme-button-color, #3b82f6); color: var(--tg-theme-button-text-color, #ffffff);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line></svg>
                    <span class="ml-2 font-semibold">Начать запись</span>
                </button>
            </div>
            <form id="infopovod-form" method="POST">
                <input type="hidden" name="action" value="add_result">
                <input type="hidden" name="deal" value="<?=$id_deal?>"/>
                <input type="hidden" name="company" value="<?=$company?>"/>
                <input type="hidden" name="user" value="<?=$userId?>"/>
                <? foreach ($infopovodInfo as $id => $value): ?>
                    <div class="card space-y-4">
                        <label class="label text-lg"><?=$value['NAME']?></label>
                        <input type="hidden" name="id-info-<?=$id?>" value="<?=$id?>"/>
                        <div>
                            <select name="kasatka-status-<?=$id?>" class="form-select kasatka-status" data-infopovod-id="<?=$id?>" required>
                                <option value="0">Выберите статус</option>
                                <?
                                $comment_text = "(Обязателен комментарий)";
                                $render_options = function($statuses) use ($comment_text) {
                                    foreach ($statuses as $status) {
                                        $label = $status['PROPERTY_COMMENT_ENUM_ID'] == 3381 ? sprintf('%s %s', $status['NAME'], $comment_text) : $status['NAME'];
                                        echo "<option value=\"{$status['ID']}\">{$label}</option>";
                                    }
                                };
                                $render_options($value['STATUS_SUCCESS']);
                                $render_options($value['STATUS_ERROR']);
                                $render_options($value['POTENTIAL_STATUS']);
                                ?>
                            </select>
                        </div>
                        <div><textarea name="kasatka-comment-<?=$id?>" placeholder="Важна любая обратная связь" class="form-textarea" rows="3"></textarea></div>
                        <div class="dynamic-field date-communication-<?=$id?> hidden"><label class="label">Дата следующей коммуникации</label><input type="date" name="kasatka-date-communication-<?=$id?>" class="form-input"></div>
                        <div class="dynamic-field phone-sms-send-<?=$id?> hidden"><label class="label">Номер телефона для СМС</label><input type="tel" name="kasatka-phone-<?=$id?>" class="form-input" placeholder="79991234567"></div>
                        <div class="dynamic-field product-<?=$id?> hidden">
                            <label class="label">Товар для апробации</label>
                            <select name="kasatka-product-<?=$id?>" class="form-select">
                                <? if (!empty($allProductsModified[$id])): foreach ($allProductsModified[$id] as $product): ?>
                                    <option value="<?=$product['XML_ID']?>"><?=$product['NAME']?></option>
                                <? endforeach; endif; ?>
                            </select>
                        </div>
                    </div>
                <? endforeach; ?>
            </form>
            <p class="text-xs text-gray-500 mt-4 px-2">*Убедитесь в правильности заполнения. Сохранённые результаты изменению не подлежат.</p>
        <? endif; ?>
    </div>
    <div id="recording-overlay" class="hidden">
        <p class="text-lg mb-4"><span class="recording-dot"></span>Идёт запись...</p>
        <div id="recording-timer">00:00</div>
        <div id="recording-controls" class="flex">
            <button id="stop-record-btn" class="btn-stop">Остановить</button>
            <button id="cancel-record-btn" class="btn-cancel">Отмена</button>
        </div>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', function () {
    // Check if we're in Telegram or browser
    let tg;
    let inTelegram = false;
    try {
        if (window.Telegram && window.Telegram.WebApp) {
            tg = window.Telegram.WebApp;
            tg.ready();
            tg.expand();
            inTelegram = true;
        }
    } catch (e) {
        console.log("Not running in Telegram WebApp");
    }
    
    // Show debug panel in browser mode
    if (!inTelegram) {
        const debugSection = document.getElementById('debug-section');
        if (debugSection) {
            debugSection.style.display = 'block';
        }
    }

    const form = document.getElementById('infopovod-form');
    if (!form) return;

    // --- FORM LOGIC ---
    if (inTelegram) {
        tg.MainButton.setText('Сохранить результаты');
        tg.MainButton.onClick(handleFormSubmit);
        tg.MainButton.show();
    }

    document.querySelectorAll('.kasatka-status').forEach(select => {
        select.addEventListener('change', handleStatusChange);
    });

    async function handleStatusChange(event) {
        const select = event.target;
        const statusId = select.value;
        const infopovodId = select.dataset.infopovodId;
        const card = select.closest('.card');

        card.querySelectorAll(`[class*="dynamic-field"]`).forEach(el => el.classList.add('hidden'));
        card.querySelectorAll('input[type="date"], input[type="tel"], textarea').forEach(el => el.required = false);

        if (statusId === '0') return;

        tg.MainButton.showProgress();
        try {
            const formData = new FormData();
            formData.append('action', 'get_status_info');
            formData.append('id', statusId);
            formData.append('tgWebAppInitData', tg.initData);

            const response = await fetch('', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                const textarea = card.querySelector(`[name="kasatka-comment-${infopovodId}"]`);
                textarea.placeholder = result.placeholder;
                if (result.commentRequired) textarea.required = true;

                if (result.needCommunicationDate) {
                    const field = card.querySelector(`.date-communication-${infopovodId}`);
                    field.classList.remove('hidden');
                    field.querySelector('input').required = true;
                }
                if (result.smsSend) {
                    const field = card.querySelector(`.phone-sms-send-${infopovodId}`);
                    field.classList.remove('hidden');
                    field.querySelector('input').required = true;
                }
                if (result.launchBP) {
                    card.querySelector(`.product-${infopovodId}`).classList.remove('hidden');
                }
            } else {
                tg.showAlert(result.message || 'Не удалось получить детали статуса.');
            }
        } catch (err) {
            tg.showAlert('Ошибка сети при получении деталей статуса.');
        } finally {
            tg.MainButton.hideProgress();
        }
    }

    async function handleFormSubmit() {
        // Check if we're in Telegram or browser
        let tg;
        let inTelegram = true;
        try {
            tg = window.Telegram.WebApp;
        } catch (e) {
            inTelegram = false;
            console.log("Running in browser mode");
        }
        
        if (inTelegram) {
            tg.MainButton.showProgress();
            tg.MainButton.disable();
        } else {
            // Show debug panel submit button as loading
            const debugBtn = document.getElementById('debug-submit-btn');
            if (debugBtn) {
                debugBtn.disabled = true;
                debugBtn.innerHTML = 'Submitting...';
            }
        }
        
        // Clear previous debug output
        const debugOutput = document.getElementById('debug-output');
        if (debugOutput) {
            debugOutput.innerHTML = '<strong>Submitting form...</strong>';
        }

        try {
            const formData = new FormData(form);
            
            // Log form data for debugging
            if (!inTelegram) {
                console.log("Form data being submitted:");
                for (let [key, value] of formData.entries()) {
                    console.log(key, value);
                }
            }

            const response = await fetch(window.location.href, { 
                method: 'POST', 
                body: formData 
            });
            
            const responseText = await response.text();
            console.log("Server response:", responseText);
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (e) {
                throw new Error("Invalid JSON response: " + responseText);
            }

            if (result.success) {
                const successMsg = result.message || 'Данные успешно сохранены!';
                if (inTelegram) {
                    tg.showPopup({
                        title: 'Успешно!',
                        message: successMsg,
                        buttons: [{ type: 'ok' }]
                    }, () => tg.close());
                } else {
                    alert(successMsg);
                    if (debugOutput) {
                        debugOutput.innerHTML = '<strong style="color: green;">Success: ' + successMsg + '</strong>';
                    }
                }
            } else {
                const errorMsg = result.message || 'Ошибка при сохранении данных.';
                if (inTelegram) {
                    tg.showAlert(errorMsg);
                } else {
                    alert('Error: ' + errorMsg);
                    if (debugOutput) {
                        debugOutput.innerHTML = '<strong style="color: red;">Error: ' + errorMsg + '</strong>';
                        if (result.processed !== undefined) {
                            debugOutput.innerHTML += '<br>Processed items: ' + result.processed;
                        }
                    }
                }
            }
        } catch (error) {
            const errorMsg = `Ошибка сети при сохранении: ${error.message}`;
            if (inTelegram) {
                tg.showAlert(errorMsg);
            } else {
                alert('Network Error: ' + errorMsg);
                if (debugOutput) {
                    debugOutput.innerHTML = '<strong style="color: red;">Network Error: ' + errorMsg + '</strong>';
                }
            }
            console.error("Submission error:", error);
        } finally {
            if (inTelegram) {
                tg.MainButton.hideProgress();
                tg.MainButton.enable();
            } else {
                const debugBtn = document.getElementById('debug-submit-btn');
                if (debugBtn) {
                    debugBtn.disabled = false;
                    debugBtn.innerHTML = 'Submit Form (Debug)';
                }
            }
        }
    }

    function validateForm() {
        let isValid = true;
        form.querySelectorAll('select, textarea, input').forEach(el => {
            el.classList.remove('form-error');
            let isInvalid = false;
            if (el.required && !el.value) isInvalid = true;
            if (el.tagName === 'SELECT' && el.value === '0' && el.required) isInvalid = true;
            if (isInvalid) {
                isValid = false;
                el.classList.add('form-error');
            }
        });
        return isValid;
    }

    // --- NEW VOICE INPUT LOGIC ---
    const recordBtn = document.getElementById('record-btn');
    const stopRecordBtn = document.getElementById('stop-record-btn');
    const cancelRecordBtn = document.getElementById('cancel-record-btn');
    const recordingOverlay = document.getElementById('recording-overlay');
    const recordingTimerEl = document.getElementById('recording-timer');
    const voiceUrl = 'https://proxytestitnebo.fly.dev/process-audio';

    let mediaRecorder;
    let audioChunks = [];
    let timerInterval;

    const startRecording = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);

            mediaRecorder.addEventListener('dataavailable', event => audioChunks.push(event.data));
            mediaRecorder.addEventListener('stop', () => {
                stream.getTracks().forEach(track => track.stop());
                if (audioChunks.length > 0) {
                    sendAudioToServer(new Blob(audioChunks, { type: 'audio/webm' }));
                }
                audioChunks = [];
            });

            audioChunks = [];
            mediaRecorder.start();
            recordingOverlay.classList.remove('hidden');
            recordingOverlay.style.display = 'flex'; // Enforce display
            startTimer();
        } catch (error) {
            tg.showAlert(`Не удалось получить доступ к микрофону: ${error.message}`);
        }
    };

    const stopRecording = () => {
        if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
        recordingOverlay.classList.add('hidden');
        stopTimer();
    };

    const cancelRecording = () => {
        audioChunks = [];
        stopRecording();
    };

    const startTimer = () => {
        let seconds = 0;
        recordingTimerEl.textContent = '00:00';
        timerInterval = setInterval(() => {
            seconds++;
            const min = String(Math.floor(seconds / 60)).padStart(2, '0');
            const sec = String(seconds % 60).padStart(2, '0');
            recordingTimerEl.textContent = `${min}:${sec}`;
        }, 1000);
    };

    const stopTimer = () => clearInterval(timerInterval);

    const getFormStructure = () => {
        const infopovody = [];
        form.querySelectorAll('.card').forEach(card => {
            const statusSelect = card.querySelector('select[name^="kasatka-status-"]');
            if (!statusSelect) return;

            const infopovodId = statusSelect.dataset.infopovodId;
            const nameLabel = card.querySelector('.label.text-lg');

            const statuses = Array.from(statusSelect.options)
                .filter(opt => opt.value && opt.value !== '0')
                .map(opt => ({ id: opt.value, name: opt.textContent.trim() }));

            const commentTextarea = card.querySelector(`textarea[name="kasatka-comment-${infopovodId}"]`);

            infopovody.push({
                id: infopovodId,
                name: nameLabel ? nameLabel.textContent.trim() : `Инфоповод ${infopovodId}`,
                statuses: statuses,
                products: Array.from(card.querySelectorAll(`select[name="kasatka-product-${infopovodId}"] option`))
                             .map(opt => ({ id: opt.value, name: opt.textContent.trim() }))
            });
        });
        return { infopovody };
    };

const sendAudioToServer = async (audioBlob) => {
    tg.MainButton.showProgress();
    try {
      const formData = new FormData();
      formData.append('audio', audioBlob, 'recording.webm');
      formData.append('form_structure', JSON.stringify(getFormStructure()));

      const response = await fetch(voiceUrl, { method: 'POST', body: formData });
      const result = await response.json();

      if (response.ok && result && result.success) { // Добавил проверку на result.success
        applyFields(result); // Используем result, а не result.result
        const successMessage = 'Поля успешно заполнены голосом.';
                // Проверяем версию для обратной связи
                if (tg.isVersionAtLeast('6.2')) {
                    tg.showAlert(successMessage);
                } else {
                    alert(successMessage);
                }
      } else {
        throw new Error(result.error || result.message || 'Неизвестный ответ от сервера.');
      }
    } catch (error) {
            const errorMessage = `Ошибка обработки аудио: ${error.message}`;
            // Проверяем версию для обратной связи
            if (tg.isVersionAtLeast('6.2')) {
                tg.showAlert(errorMessage);
            } else {
                alert(errorMessage);
            }
    } finally {
      tg.MainButton.hideProgress();
    }
  };

const applyFields = (data) => {
    // Проверяем, что result существует и это объект, а не массив
    if (!data.result || typeof data.result !== 'object') return;

    // Проходим по ключам объекта (ID инфоповодов)
    for (const id in data.result) {
      if (data.result.hasOwnProperty(id)) {
        const infopovodData = data.result[id];
        let statusValue = null;
        let commentValue = null;

                // Гибкий поиск статуса и комментария внутри объекта
                for (const key in infopovodData) {
                    if (key.includes('status') && infopovodData[key].value) {
                        statusValue = infopovodData[key].value;
                    }
                    if (key.includes('comment') && infopovodData[key].value) {
                        commentValue = infopovodData[key].value;
                    }
                }

        // Применяем статус
        if (statusValue) {
          const statusSelect = form.querySelector(`select[name="kasatka-status-${id}"]`);
          if (statusSelect) {
            statusSelect.value = statusValue;
            // Вызываем событие change, чтобы подгрузились зависимые поля
            statusSelect.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }

        // Применяем комментарий
        if (commentValue) {
          const commentTextarea = form.querySelector(`textarea[name="kasatka-comment-${id}"]`);
          if (commentTextarea) {
            commentTextarea.value = commentValue;
          }
        }
      }
    }
  };

    recordBtn.addEventListener('click', startRecording);
    stopRecordBtn.addEventListener('click', stopRecording);
    cancelRecordBtn.addEventListener('click', cancelRecording);
});
    </script>
</body>
</html>
<div id="debug-section" style="position: fixed; bottom: 20px; right: 20px; background: #fff; padding: 15px; border: 1px solid #ccc; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 1000; max-width: 400px; display: none;">
    <h3 style="margin-top: 0;">Debug Panel</h3>
    <button id="debug-submit-btn" style="background: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-bottom: 10px;">Submit Form (Debug)</button>
    <button id="show-data-btn" style="background: #2196F3; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-bottom: 10px; margin-left: 10px;">Show Form Data</button>
    <button id="fill-sample-btn" style="background: #FF9800; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin-bottom: 10px; margin-left: 10px;">Fill Sample Data</button>
    <div id="debug-output" style="margin-top: 10px; max-height: 200px; overflow-y: auto; font-size: 12px; background: #f5f5f5; padding: 10px;"></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Check if we're in Telegram or browser
    let tg;
    let inTelegram = true;
    try {
        tg = window.Telegram.WebApp;
    } catch (e) {
        inTelegram = false;
        console.log("Running in browser mode");
    }
    
    document.getElementById('debug-submit-btn').addEventListener('click', function() {
        handleFormSubmit();
    });
    
    document.getElementById('show-data-btn').addEventListener('click', function() {
        const formData = new FormData(form);
        let output = '<strong>Form Data:</strong><br>';
        for (let [key, value] of formData.entries()) {
            output += `${key}: ${value}<br>`;
        }
        document.getElementById('debug-output').innerHTML = output;
    });
    
    document.getElementById('fill-sample-btn').addEventListener('click', function() {
        // Fill first infopovod with sample data if exists
        const statusSelect = document.querySelector('select[name^="kasatka-status-"]');
        if (statusSelect) {
            statusSelect.value = statusSelect.options[1]?.value || statusSelect.options[0]?.value;
            statusSelect.dispatchEvent(new Event('change'));
            
            const infopovodId = statusSelect.name.split('-').pop();
            const commentField = document.querySelector(`textarea[name="kasatka-comment-${infopovodId}"]`);
            if (commentField) {
                commentField.value = "Тестовый комментарий";
            }
        }
        
        document.getElementById('debug-output').innerHTML = '<strong>Sample data filled</strong>';
    });
});
</script>
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");