<?php

define("NOT_NEED_HEADER", true);
define("NOT_NEED_FOOTER", true);
define("NO_AGENT_CHECK", true);
define("NOT_CHECK_PERMISSIONS", true);
define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_STATISTIC", true);
define("STOP_STATISTICS", true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Crm\DealTable;
use Bitrix\Main\Config\Option;
use Bitrix\Crm\Timeline\CommentEntry;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\UserTable;
use Bitrix\Crm\StatusTable;
use Bitrix\Crm\PhaseSemantics;
use Bitrix\Main\Entity\Query;
use Bitrix\Crm\Company\Relation;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Binding\DealCompanyTable;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\CompanyTable;

Loader::includeModule('main');
Loader::includeModule('crm');
Loader::includeModule('highloadblock');
Loader::includeModule('iblock');

const UF_TELEGRAM_ID_FIELD = 'UF_ROCADOMED_TELEGEAM_ID';
const HLBLOCK_NAME = 'UserStageInfo';
const MODULE_ID = 'rocada.telegram';

// --- ФУНКЦИЯ АВТОРИЗАЦИИ ---
function verifyTelegramAuth(string $auth_data_string): bool {
    if (empty($auth_data_string)) return false;

    if (!Loader::includeModule(MODULE_ID)) return false;
    $bot_token = Option::get(MODULE_ID, 'telegram_bot_token', '');
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
    $rsUser = UserTable::getList(['filter' => [UF_TELEGRAM_ID_FIELD => $telegramId]])->fetch();
    if ($rsUser) {
        $USER->Authorize($rsUser['ID']);
        return true;
    }

    return false;
}

// --- ФУНКЦИЯ ДЛЯ ПОЛУЧЕНИЯ ID КОМПАНИЙ ---
function getCompanyIdsFromDeal(int $dealId): array
{
    $companyIds = [];
    if (!Loader::includeModule('crm')) {
        return $companyIds;
    }

    // Получаем COMPANY_ID напрямую из сделки
    $deal = DealTable::getList([
        'filter' => ['=ID' => $dealId],
        'select' => ['COMPANY_ID'],
    ])->fetch();

    if (!empty($deal['COMPANY_ID'])) {
        $companyIds[] = (int)$deal['COMPANY_ID'];
    }

    return array_unique($companyIds);
}

// --- AJAX РОУТЕР ДЛЯ MINI APP ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    function sendJsonResponse(array $data): void {
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

    $isTgAuthSuccess = verifyTelegramAuth($_POST['tgWebAppInitData'] ?? '');
    global $USER;
    if (!$isTgAuthSuccess && !$USER->IsAuthorized()) {
        sendJsonResponse(['success' => false, 'message' => 'Ошибка авторизации.']);
    }

    $dealId = (int)($_POST['deal_id'] ?? 0); // deal_id нужен для других экшенов

    switch ($_POST['action']) {
        // --- НОВОЕ ДЕЙСТВИЕ ПОИСКА СДЕЛОК ---
        case 'find_deals':
            $query = trim(htmlspecialcharsbx($_POST['query']));
            $foundDealsData = [];
            $dealSelect = ['ID', 'TITLE', 'UF_UNLOAD_DOCS'];

            if (is_numeric($query)) {
                // 1. Ищем по ID сделки
                $deal = DealTable::getList([
                    'filter' => ['=ID' => (int)$query],
                    'select' => $dealSelect
                ])->fetch();
                if ($deal) {
                    $foundDealsData[] = $deal;
                }
            }

            // 2. Если по ID не нашли ИЛИ запрос не числовой, ищем по названию компании
            if (empty($foundDealsData)) {
                $companyRes = CompanyTable::getList([
                    'filter' => ['%TITLE' => $query],
                    'select' => ['ID']
                ]);
                $companyIds = [];
                while ($company = $companyRes->fetch()) {
                    $companyIds[] = $company['ID'];
                }

                if (!empty($companyIds)) {
                    $dealIds = [];
                    
                    // Ищем сделки по старому полю COMPANY_ID (без использования DealCompanyTable)
                    $dealRes = DealTable::getList([
                        'filter' => ['@COMPANY_ID' => $companyIds],
                        'select' => ['ID']
                    ]);
                    while ($deal = $dealRes->fetch()) {
                        $dealIds[] = $deal['ID'];
                    }

                    $dealIds = array_unique($dealIds);

                    if (!empty($dealIds)) {
                        $dealListRes = DealTable::getList([
                            'filter' => ['@ID' => $dealIds, '!CLOSED' => 'Y'], // Добавим фильтр по открытым сделкам
                            'select' => $dealSelect,
                            'order' => ['ID' => 'DESC'],
                            'limit' => 20 // Ограничиваем количество результатов до 20
                        ]);
                        while ($deal = $dealListRes->fetch()) {
                            $foundDealsData[] = $deal;
                        }
                    }
                }
            }

            if (!empty($foundDealsData)) {
                sendJsonResponse([
                    'success' => true,
                    'data' => [
                        'deals' => $foundDealsData
                    ]
                ]);
            } else {
                sendJsonResponse(['success' => false, 'message' => 'Сделки не найдены по вашему запросу.']);
            }
            break;
            
        case 'get_unload_points':
            if ($dealId <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Некорректный ID сделки.']);
            }
            $companyIds = getCompanyIdsFromDeal($dealId);
        
            if (!empty($companyIds)) {
                $points = [];
                if (Loader::includeModule('iblock')) {
                    $filter = [
                        'IBLOCK_ID' => 206,
                        'PROPERTY_1817' => $companyIds
                    ];
                    $select = [
                        'ID', 
                        'NAME'
                    ];

                    $res = CIBlockElement::GetList([], $filter, false, false, $select);
                    while ($item = $res->fetch()) {
                        $points[] = ['id' => $item['ID'], 'name' => $item['NAME']];
                    }
                }
        
                if (empty($points)) {
                    sendJsonResponse(['success' => false, 'message' => 'Для этой компании не найдены пункты разгрузки.']);
                } else {
                    sendJsonResponse(['success' => true, 'data' => $points]);
                }
            } else {
                sendJsonResponse(['success' => false, 'message' => 'Для этой сделки не найдена связанная компания.']);
            }
            break;

        case 'plan_visit':
            if ($dealId <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Некорректный ID сделки.']);
            }
            $pointId = (int)$_POST['point_id'];
            $visitDate = htmlspecialcharsbx($_POST['visit_date']);
            $visitTime = htmlspecialcharsbx($_POST['visit_time']);

            if ($pointId <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Некорректный ID адреса.']);
            }
            if (empty($visitDate) || empty($visitTime)) {
                sendJsonResponse(['success' => false, 'message' => 'Дата и время визита обязательны.']);
            }
            
            $visitDateField = Option::get(MODULE_ID, 'visit_date_field', 'UF_CRM_1670850308849');

            $visitDateTime = '';
            try {
                $dt = new DateTime("{$visitDate} {$visitTime}");
                $visitDateTime = $dt->format('d.m.Y H:i:s');
            } catch (Exception $e) {
                sendJsonResponse(['success' => false, 'message' => 'Не удалось отформатировать дату и время.']);
            }

            $updateResult = DealTable::update($dealId, [
                'UF_UNLOAD_DOCS' => $pointId,
                $visitDateField => $visitDateTime
            ]);

            if ($updateResult->isSuccess()) {
                $pointName = '';
                if (Loader::includeModule('iblock')) {
                    $pointRes = CIBlockElement::GetList([], ['IBLOCK_ID' => 206, 'ID' => $pointId], false, false, ['NAME'])->fetch();
                    if ($pointRes) {
                        $pointName = $pointRes['NAME'];
                    }
                }
                $dealData = DealTable::getById($dealId)->fetch();
                $dealTitle = $dealData['TITLE'];
                
                $messageText = "✅ *Визит запланирован!* \n\n"
                    . "▫️ *Сделка:* [{$dealTitle}](https://office.rocadatech.ru/crm/deal/details/{$dealId}/)\n"
                    . "▫️ *Адрес:* {$pointName}\n"
                    . "▫️ *Дата и время:* {$visitDateTime}";
                
                // ... (Логика отправки сообщения в Telegram) ...

                sendJsonResponse(['success' => true, 'message' => 'Визит успешно запланирован.', 'deal_id' => $dealId]);
            } else {
                sendJsonResponse(['success' => false, 'message' => 'Ошибка планирования визита: ' . implode(', ', $updateResult->getErrorMessages())]);
            }
            break;
    }
    sendJsonResponse(['success' => false, 'message' => 'Неизвестное действие.']);
}

// --- НАЧАЛО ЗАГРУЗКИ СТРАНИЦЫ ---
$isTgAuthSuccess = verifyTelegramAuth($_GET['tgWebAppInitData'] ?? '');

global $USER;

if (!$isTgAuthSuccess && !$USER->IsAuthorized()) {
    ?>
    <!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Загрузка...</title><script src="https://telegram.org/js/telegram-web-app.js"></script><script src="https://cdn.tailwindcss.com"></script></head><body></body><script>
        window.Telegram.WebApp.ready()
        const tg = window.Telegram.WebApp;
        const urlParams = new URLSearchParams(window.location.search);
        if (!urlParams.has('tgWebAppInitData') && tg.initData) {
            // Fix the URL construction to properly append the parameter
            let url = window.location.href;
            // Remove any existing hash part
            url = url.split('#')[0];
            // Add the initData parameter correctly
            const separator = url.includes('?') ? '&' : '?';
            window.location.href = url + separator + 'tgWebAppInitData=' + encodeURIComponent(tg.initData);
        } else {
            document.body.innerHTML = '<div class="flex items-center justify-center h-screen bg-gray-100 dark:bg-gray-900"><div class="text-center p-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg"><h1 class="text-2xl font-bold text-red-600 dark:text-red-400">Доступ запрещен</h1><p class="mt-2 text-gray-700 dark:text-gray-300">Ошибка верификации пользователя Telegram или ваш аккаунт не привязан к системе.</p></div></div>';
        }
    </script></html>
    <?php
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
    die();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Планирование визита</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        :root { color-scheme: light dark; }
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden; /* Prevent horizontal scrolling */
        }
        body {
            background-color: var(--tg-theme-bg-color, #f3f4f6);
            color: var(--tg-theme-text-color, #111827);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            flex-direction: column;
            max-width: 100vw; /* Ensure content doesn't exceed viewport width */
        }
        /* Prevent any element from causing horizontal overflow */
        * {
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Ensure the app container never overflows */
        #app-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Additional containment for search content */
        #search-mode-content {
            padding: 16px;
            overflow-y: auto;
            flex: 1;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        .card {
            background-color: var(--tg-theme-secondary-bg-color, #ffffff);
            border-radius: 12px; 
            padding: 16px; 
            margin-bottom: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            width: 100%;
            box-sizing: border-box; /* Include padding in width calculation */
        }
        .tab-container {
            display: flex;
            background-color: var(--tg-theme-secondary-bg-color, #ffffff);
            margin: 0;
            border-radius: 0;
            padding: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            width: 100%;
        }
        .tab {
            flex: 1;
            text-align: center;
            padding: 12px;
            border-radius: 0;
            font-weight: 600;
            cursor: pointer;
            color: var(--tg-theme-hint-color, #6b7280);
            transition: all 0.2s;
            margin: 0;
        }
        .tab.active {
            background-color: var(--tg-theme-button-color, #3b82f6);
            color: var(--tg-theme-button-text-color, #ffffff);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        #map-mode-content {
            flex: 1;
            display: flex;
            overflow: hidden;
            padding: 0;
            width: 100%;
        }
        #map-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .form-input, .form-select {
            width: 100%;
            border: 1px solid var(--tg-theme-hint-color, #d1d5db);
            background-color: var(--tg-theme-bg-color, #ffffff);
            color: var(--tg-theme-text-color, #000000);
            border-radius: 8px; 
            padding: 12px; 
            font-size: 16px;
            box-sizing: border-box;
            min-width: 0;
            max-width: 100%;
            -webkit-appearance: none;
            appearance: none;
            /* Fix for date/time input overflow */
            position: relative;
            display: block;
        }
        
        /* Specific fix for date and time inputs to prevent overflow */
        #visit-date-input, #visit-time-input {
            width: calc(100% - 24px); /* Account for padding */
            padding: 12px;
            margin: 0;
            box-sizing: border-box;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--tg-theme-button-color, #3b82f6);
            box-shadow: 0 0 0 2px var(--tg-theme-button-color, #3b82f6);
        }
        .label { 
            display: block; 
            font-weight: 600; 
            margin-bottom: 6px; 
        }
        .btn {
            display: inline-flex; 
            justify-content: center; 
            align-items: center;
            width: 100%; 
            padding: 14px; 
            border-radius: 12px; /* Rounded buttons as requested */
            font-weight: 600;
            background-color: var(--tg-theme-button-color, #3b82f6);
            color: var(--tg-theme-button-text-color, #ffffff);
            cursor: pointer; 
            transition: background-color 0.2s;
            margin-top: 0;
            box-sizing: border-box;
            border: none;
            font-size: 16px;
        }
        .btn:hover:not(:disabled) {
            filter: brightness(0.9);
        }
        .btn:disabled {
            opacity: 0.6; 
            cursor: not-allowed;
        }
        .deal-select-btn {
            display: block; 
            width: 100%; 
            text-align: left;
            padding: 12px; 
            margin-bottom: 8px; 
            margin-top: 0;
            border: 1px solid var(--tg-theme-hint-color, #d1d5db);
            background-color: var(--tg-theme-bg-color, #ffffff);
            border-radius: 12px; /* Rounded buttons as requested */
            cursor: pointer;
            transition: background-color 0.2s;
            box-sizing: border-box;
        }
        .deal-select-btn:hover {
            background-color: var(--tg-theme-hint-color, #e5e7eb);
        }
        
        /* Additional improvements */
        h1, h2, h3 {
            word-wrap: break-word; /* Prevent long titles from causing overflow */
        }
        
        /* Specific styling for date and time inputs to ensure containment */
        input[type="date"], input[type="time"] {
            -webkit-appearance: none;
            appearance: none;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--tg-theme-hint-color, #d1d5db);
            background-color: var(--tg-theme-bg-color, #ffffff);
            color: var(--tg-theme-text-color, #000000);
            min-width: 0;
            max-width: 100%;
        }
        
        /* Remove inner spin buttons and calendar icons */
        input[type="date"]::-webkit-inner-spin-button,
        input[type="date"]::-webkit-calendar-picker-indicator,
        input[type="time"]::-webkit-inner-spin-button,
        input[type="time"]::-webkit-calendar-picker-indicator {
            -webkit-appearance: none;
            display: none;
        }
        
        /* Ensure all form elements fit within container */
        form, .form-group {
            width: 100%;
            max-width: 100%;
        }
        
        /* Improve spacing and readability */
        .mb-4 {
            margin-bottom: 1rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .card {
                padding: 12px;
            }
            .btn, .deal-select-btn {
                padding: 14px;
            }
            .form-input, .form-select {
                font-size: 16px; /* Prevent zoom on iOS */
            }
        }
        
        /* Additional specific fixes for iOS Safari */
        @media screen and (-webkit-min-device-pixel-ratio: 2) {
            #visit-date-input, #visit-time-input {
                font-size: 16px !important; /* Prevent iOS zoom */
                padding: 12px !important;
                width: 100% !important;
                box-sizing: border-box !important;
                min-width: 0 !important;
                max-width: 100% !important;
            }
            
            /* Hide native picker icons on iOS */
            input[type="date"]::-webkit-inner-spin-button,
            input[type="date"]::-webkit-calendar-picker-indicator,
            input[type="time"]::-webkit-inner-spin-button,
            input[type="time"]::-webkit-calendar-picker-indicator {
                -webkit-appearance: none;
                display: none;
            }
        }
        
        /* Ensure proper sizing for all input elements */
        input, select, textarea {
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Comprehensive overflow prevention */
        *, *:before, *:after {
            box-sizing: border-box;
            max-width: 100%;
        }
        
        /* Ensure all elements respect the viewport width */
        html, body {
            max-width: 100vw;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }
        
        /* Prevent any child element from causing horizontal overflow */
        #app-container, #search-mode-content, .card, form {
            max-width: 100%;
            overflow-x: hidden;
        }
        
        /* Fix for deal info section overflow */
        .deal-info-container {
            max-width: 100%;
            box-sizing: border-box;
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
        }
        
        .deal-info-line {
            margin: 0;
            padding: 0;
            max-width: 100%;
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
        }
        
        .deal-info-text {
            display: inline-block;
            max-width: calc(100% - 50px);
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
        }
        
        /* Specific fix for the deal info section */
        #plan-visit-section .p-3 {
            max-width: 100%;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        
        /* Ensure all form elements fit within their containers */
        .form-group, .mb-4 {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow-x: hidden;
        }
        
        /* Fix for long text in deal info */
        #deal-info-id, #deal-info-title {
            word-break: break-word;
            overflow-wrap: break-word;
            max-width: 100%;
        }
        
        /* Ensure the select element doesn't overflow */
        #unload-point-select {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Additional padding adjustments to prevent overflow */
        .card {
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
            padding: 16px;
            margin-left: 0;
            margin-right: 0;
            overflow-x: hidden;
        }
        
        /* Ensure the tab container doesn't cause overflow */
        .tab-container {
            width: 100vw;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Prevent horizontal scrolling on all devices */
        body {
            position: relative;
        }
        
        /* Ultimate overflow protection - ensures nothing can cause horizontal scroll */
        * {
            -webkit-overflow-scrolling: touch;
        }
        
        html, body {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
            position: relative;
        }
        
        #app-container {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            position: relative;
        }
        
        /* Hide any possible scrollbar */
        ::-webkit-scrollbar {
            display: none;
        }
        
        /* Ensure all absolutely positioned elements respect boundaries */
        * {
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Fix for any margin/padding issues that might cause overflow */
        .mt-4, .mb-4, .p-3 {
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        
    </style>
</head>
<body>

    <div id="app-container">
        <div class="tab-container">
            <div id="tab-search" class="tab active">🔍 Поиск</div>
            <div id="tab-map" class="tab">🗺️ Карта</div>
        </div>

        <div id="search-mode-content">
            <h1 class="text-2xl font-bold mb-4">🗓️ Планирование визита</h1>

            <div class="card">
                <div id="deal-search-section">
                    <h2 class="text-xl font-bold mb-4">Поиск сделки</h2>
                    <div class="mb-4">
                        <label for="search-query-input" class="label">ID сделки / Название компании:</label>
                        <input type="text" id="search-query-input" class="form-input" placeholder="Введите ID или название...">
                    </div>
                    <button id="search-deal-btn" class="btn">Найти</button>
                    <div id="search-message" class="mt-4 text-center text-sm font-semibold"></div>
                </div>
                
                <div id="search-results-section" style="display: none;" class="mt-4">
                    <h3 class="text-lg font-bold mb-2">Найдено несколько сделок:</h3>
                    <div id="search-results-list"></div>
                </div>

                <div id="plan-visit-section" style="display: none;" class="mt-4">
                    <h2 class="text-xl font-bold mb-4">Данные по сделке</h2>
                    <div class="deal-info-container p-3 rounded-lg bg-gray-200 dark:bg-gray-700 mb-4">
                        <p class="deal-info-line"><strong>ID:</strong> <span id="deal-info-id" class="deal-info-text"></span></p>
                        <p class="deal-info-line"><strong>Название:</strong> <span id="deal-info-title" class="deal-info-text"></span></p>
                    </div>
                    
                    <form id="plan-visit-form">
                        <input type="hidden" id="hidden-deal-id">
                        <div class="mb-4">
                            <label for="unload-point-select" class="label">Пункт разгрузки заказчика:</label>
                            <select id="unload-point-select" class="form-select" required>
                                <option value="">-- Загрузка адресов... --</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="visit-date-input" class="label">Дата визита:</label>
                            <div class="date-input-container">
                                <input type="date" id="visit-date-input" class="form-input" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="visit-time-input" class="label">Время визита:</label>
                            <div class="date-input-container">
                                <input type="time" id="visit-time-input" class="form-input" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">Запланировать визит</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="map-mode-content" style="display: none;">
            <?php
            // Generate map URL with domain, session, and filters
            $domain = ($_SERVER['HTTP_HOST'] ?? 'office.rocadatech.ru') . '/map/v1/get';
            
            // Use the session ID from the cookie directly
            // This preserves the existing authenticated session
            $sessid = $_COOKIE['PHPSESSID'] ?? '';
            
            // Define filters as a JSON object
            $filters = [
                '%STAGE_SEMANTIC_ID' => ['P'],
                '%STATUSES.ENTITY_ID' => ['DEAL_STAGE', 'DEAL_STAGE_7'],
                'CATEGORY_ID' => '7'
            ];
            
            // Encode filters as JSON
            $filtersJson = json_encode($filters);
            
            // Build the map URL
            // $mapUrl = "https://46e6b40d3398.ngrok-free.app/?tg=true&domain={$domain}&sessid={$sessid}&filters=" . urlencode($filtersJson);
            $mapUrl = "https://app-module-map-six.vercel.app/?tg=true&domain={$domain}&sessid={$sessid}&filters=" . urlencode($filtersJson);
            ?>
            <iframe id="map-iframe" src="<?php echo $mapUrl; ?>" title="Карта"></iframe>
        </div>
    </div>


    <script>
        const tg = window.Telegram.WebApp;
        tg.ready();
        tg.expand();
        const mainScriptUrl = window.location.href.split('plan-visit')[0] + 'index.php';

        // Глобальный кэш для найденных сделок
        let foundDealsCache = [];

        // Слушатель события scheduleVisit от iframe
        window.addEventListener('message', async function(event) {
            // Проверяем, что сообщение имеет тип scheduleVisit
            if (event.data && event.data.type === 'scheduleVisit') {
                // Получаем ID сделки из сообщения
                const dealId = event.data.dealId;
                
                // Отправляем AJAX запрос для получения данных сделки
                const formData = new FormData();
                formData.append('action', 'find_deals');
                formData.append('query', dealId);
                formData.append('tgWebAppInitData', tg.initData);
                
                try {
                    const response = await fetch(mainScriptUrl, { method: 'POST', body: formData });
                    const result = await response.json();
                    
                    if (result.success && result.data.deals.length > 0) {
                        // Загружаем данные первой (и единственной) найденной сделки
                        const deal = result.data.deals[0];
                        loadDealData(deal);
                        
                        // Переключаемся на вкладку поиска, если мы на вкладке карты
                        if (document.getElementById('tab-map').classList.contains('active')) {
                            document.getElementById('tab-search').click();
                        }
                    } else {
                        tg.showAlert('Сделка не найдена');
                    }
                } catch (error) {
                    tg.showAlert('Ошибка при загрузке данных сделки');
                }
            }
        });

        function toggleLoading(show) {
            const loadingHtml = `
                <div id="custom-loading-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; justify-content: center; align-items: center;">
                    <div style="width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid var(--tg-theme-button-color, #3b82f6); border-radius: 50%; animation: spin 1s linear infinite;"></div>
                </div>
                <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
            `;
            if (show) {
                if (!document.getElementById('custom-loading-overlay')) {
                    document.body.insertAdjacentHTML('beforeend', loadingHtml);
                }
            } else {
                const overlay = document.getElementById('custom-loading-overlay');
                if (overlay) {
                    overlay.remove();
                }
            }
        }

        function showLoading() {
            if (typeof tg.showProgress === 'function') {
                tg.showProgress();
            } else {
                toggleLoading(true);
            }
        }

        function hideLoading() {
            if (typeof tg.hideProgress === 'function') {
                tg.hideProgress();
            } else {
                toggleLoading(false);
            }
        }

        // --- ЛОГИКА ПЕРЕКЛЮЧЕНИЯ ТАБОВ ---
        const tabSearch = document.getElementById('tab-search');
        const tabMap = document.getElementById('tab-map');
        const searchContent = document.getElementById('search-mode-content');
        const mapContent = document.getElementById('map-mode-content');

        tabSearch.addEventListener('click', () => {
            tabSearch.classList.add('active');
            tabMap.classList.remove('active');
            searchContent.style.display = 'block';
            mapContent.style.display = 'none';
        });

        tabMap.addEventListener('click', () => {
            tabMap.classList.add('active');
            tabSearch.classList.remove('active');
            searchContent.style.display = 'none';
            mapContent.style.display = 'flex'; // Используем flex для растягивания
        });

        // --- НОВАЯ ФУНКЦИЯ ЗАГРУЗКИ ДАННЫХ СДЕЛКИ ---
        async function loadDealData(deal) {
            const planSection = document.getElementById('plan-visit-section');
            const dealInfoId = document.getElementById('deal-info-id');
            const dealInfoTitle = document.getElementById('deal-info-title');
            const searchMessage = document.getElementById('search-message');
            const searchResultsSection = document.getElementById('search-results-section');

            showLoading();
            
            dealInfoId.textContent = deal.ID;
            dealInfoTitle.textContent = deal.TITLE;
            document.getElementById('hidden-deal-id').value = deal.ID;
            
            searchMessage.textContent = 'Сделка выбрана!';
            searchMessage.style.color = '#16a34a';
            searchResultsSection.style.display = 'none'; // Скрываем список результатов
            foundDealsCache = []; // Очищаем кэш

            // Загружаем пункты разгрузки
            await fetchUnloadPoints(deal.ID, deal.UF_UNLOAD_DOCS);

            planSection.style.display = 'block';
            hideLoading();
        }

        async function fetchUnloadPoints(dealId, currentUnloadPointId) {
            const select = document.getElementById('unload-point-select');
            const formData = new FormData();
            formData.append('action', 'get_unload_points');
            formData.append('deal_id', dealId);
            formData.append('tgWebAppInitData', tg.initData);

            select.innerHTML = '';
            const loadingOption = document.createElement('option');
            loadingOption.value = '';
            loadingOption.textContent = '-- Загрузка адресов... --';
            select.appendChild(loadingOption);
            
            try {
                const response = await fetch(mainScriptUrl, { method: 'POST', body: formData });
                const result = await response.json();
                
                select.innerHTML = ''; // Очищаем после загрузки
                
                if (result.success && result.data.length > 0) {
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = '-- Выберите адрес --';
                    select.appendChild(defaultOption);

                    result.data.forEach(point => {
                        const option = document.createElement('option');
                        option.value = point.id;
                        option.textContent = point.name;
                        // Преобразуем оба значения к числу для корректного сравнения
                        if (Number(point.id) === Number(currentUnloadPointId)) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                } else {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = '-- ' + (result.message || 'Адреса для компании не найдены') + ' --';
                    select.appendChild(option);
                    tg.showAlert(result.message);
                }
            } catch (error) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = '-- Ошибка сети при загрузке адресов. --';
                select.appendChild(option);
                tg.showAlert('Ошибка сети при загрузке адресов.');
            }
        }

        // --- ОБНОВЛЕННЫЙ ПОИСК СДЕЛОК ---
        document.getElementById('search-deal-btn').addEventListener('click', async () => {
            const query = document.getElementById('search-query-input').value;
            const searchMessage = document.getElementById('search-message');
            const planSection = document.getElementById('plan-visit-section');
            const searchResultsSection = document.getElementById('search-results-section');
            const searchResultsList = document.getElementById('search-results-list');

            if (query.trim() === '') {
                searchMessage.textContent = 'Пожалуйста, введите ID или название компании.';
                searchMessage.style.color = '#dc2626';
                return;
            }

            showLoading();
            searchMessage.textContent = '';
            planSection.style.display = 'none';
            searchResultsSection.style.display = 'none';
            searchResultsList.innerHTML = '';
            foundDealsCache = []; // Очищаем кэш

            const formData = new FormData();
            formData.append('action', 'find_deals');
            formData.append('query', query);
            formData.append('tgWebAppInitData', tg.initData);

            try {
                const response = await fetch(mainScriptUrl, { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success && result.data.deals.length > 0) {
                    
                    if (result.data.deals.length === 1) {
                        // Если найдена 1 сделка - сразу загружаем ее
                        await loadDealData(result.data.deals[0]);
                    } else {
                        // Если найдено несколько - показываем список
                        foundDealsCache = result.data.deals; // Сохраняем в кэш
                        
                        foundDealsCache.forEach(deal => {
                            const dealButton = document.createElement('button');
                            dealButton.className = 'deal-select-btn';
                            dealButton.textContent = `[${deal.ID}] ${deal.TITLE}`;
                            dealButton.dataset.dealId = deal.ID;
                            searchResultsList.appendChild(dealButton);
                        });
                        searchResultsSection.style.display = 'block';
                        searchMessage.textContent = `Найдено: ${foundDealsCache.length} сделок.`;
                        searchMessage.style.color = '#16a34a';
                    }

                } else {
                    searchMessage.textContent = result.message || 'Сделки не найдены.';
                    searchMessage.style.color = '#dc2626';
                    planSection.style.display = 'none';
                }
            } catch (error) {
                searchMessage.textContent = 'Ошибка сети при поиске сделки.';
                searchMessage.style.color = '#dc2626';
                planSection.style.display = 'none';
            } finally {
                hideLoading();
            }
        });

        // --- ОБРАБОТЧИК КЛИКА ПО СДЕЛКЕ ИЗ СПИСКА ---
        document.getElementById('search-results-list').addEventListener('click', async (e) => {
            const targetButton = e.target.closest('.deal-select-btn');
            if (targetButton) {
                const dealId = targetButton.dataset.dealId;
                const selectedDeal = foundDealsCache.find(d => d.ID === dealId);

                if (selectedDeal) {
                    await loadDealData(selectedDeal);
                }
            }
        });


        document.getElementById('plan-visit-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const dealId = document.getElementById('hidden-deal-id').value;
            const pointId = document.getElementById('unload-point-select').value;
            const visitDate = document.getElementById('visit-date-input').value;
            const visitTime = document.getElementById('visit-time-input').value;

            if (!pointId) {
                tg.showAlert('Пожалуйста, выберите пункт разгрузки.');
                return;
            }
            if (!visitDate || !visitTime) {
                tg.showAlert('Пожалуйста, выберите дату и время визита.');
                return;
            }

            showLoading();

            const formData = new FormData();
            formData.append('action', 'plan_visit');
            formData.append('deal_id', dealId);
            formData.append('point_id', pointId);
            formData.append('visit_date', visitDate);
            formData.append('visit_time', visitTime);
            formData.append('tgWebAppInitData', tg.initData);

            try {
                const response = await fetch(mainScriptUrl, { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.success) {
                    tg.showAlert(result.message, () => {
                        tg.close();
                    });
                } else {
                    tg.showAlert(result.message);
                }
            } catch (error) {
                tg.showAlert('Ошибка сети при планировании визита.');
            } finally {
                hideLoading();
            }
        });
    </script>
</body>
</html>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php"); ?>