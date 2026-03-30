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
use Bitrix\Main\Type\DateTime;

Loader::includeModule('main');
Loader::includeModule('crm');
Loader::includeModule('highloadblock');
Loader::includeModule('iblock');

const UF_TELEGRAM_ID_FIELD = 'UF_ROCADOMED_TELEGEAM_ID';
const HLBLOCK_NAME = 'UserStageInfo';
const MODULE_ID = 'rocada.telegram';
const VISIT_LOG_HLBLOCK_ID = 12; // ID HL-блока для логов визитов

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

    $dealIdentifier = new ItemIdentifier(\CCrmOwnerType::Deal, $dealId);

    // Поиск компаний через менеджер связей
    $parents = Container::getInstance()
        ->getRelationManager()
        ->getParentElements($dealIdentifier);

    foreach ($parents as $parent) {
        if ($parent->getEntityTypeId() === \CCrmOwnerType::Company) {
            $companyIds[] = $parent->getEntityId();
        }
    }

    // Fallback: если связей не найдено, ищем по полю COMPANY_ID
    if (empty($companyIds)) {
        $deal = DealTable::getList([
            'filter' => ['=ID' => $dealId],
            'select' => ['COMPANY_ID'],
        ])->fetch();

        if (!empty($deal['COMPANY_ID'])) {
            $companyIds[] = (int)$deal['COMPANY_ID'];
        }
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

    $dealId = (int)$_POST['deal_id'];
    if ($dealId <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Некорректный ID сделки.']);
    }
    
    switch ($_POST['action']) {
        case 'update_location':
            $latitude = htmlspecialcharsbx($_POST['latitude']);
            $longitude = htmlspecialcharsbx($_POST['longitude']);
            $latitudeField = Option::get(MODULE_ID, 'latitude_field', 'UF_CRM_1716383617122');
            $longitudeField = Option::get(MODULE_ID, 'longitude_field', 'UF_CRM_1716383636592');

            $updateResult = DealTable::update($dealId, [
                $latitudeField => $latitude,
                $longitudeField => $longitude
            ]);

            if ($updateResult->isSuccess()) {
                $locationUrl = "https://yandex.ru/maps/?pt={$longitude},{$latitude}&z=16&l=map";
                sendJsonResponse(['success' => true, 'message' => 'Геолокация успешно обновлена.', 'data' => ['latitude' => $latitude, 'longitude' => $longitude, 'map_url' => $locationUrl]]);
            } else {
                sendJsonResponse(['success' => false, 'message' => 'Ошибка обновления геолокации: ' . implode(', ', $updateResult->getErrorMessages())]);
            }
            break;

        case 'end_visit':
            $newStageId = htmlspecialcharsbx($_POST['stage_id']);
            $comment = trim(htmlspecialcharsbx($_POST['comment']));

            if (empty($newStageId)) {
                sendJsonResponse(['success' => false, 'message' => 'Некорректный статус завершения.']);
            }

			$dealData = DealTable::getList([
				'filter' => ['=ID' => $dealId],
				'select' => ['COMMENTS']
			])->fetch();

            // 1. Обновляем стадию сделки
            $updateResult = DealTable::update($dealId, ['STAGE_ID' => $newStageId, 'COMMENTS' => $dealData['COMMENTS']."\n".$comment]);

            if ($updateResult->isSuccess()) {
                // 2. Если есть комментарий, добавляем его в таймлайн
                if (!empty($comment)) {
                    CommentEntry::create([
                        'TEXT' => $comment,
                        'BINDINGS' => [['ENTITY_TYPE_ID' => \CCrmOwnerType::Deal, 'ENTITY_ID' => $dealId]],
                        'AUTHOR_ID' => $USER->GetID(),
                    ]);
                }
                sendJsonResponse(['success' => true, 'message' => 'Визит успешно завершен.']);
            } else {
                sendJsonResponse(['success' => false, 'message' => 'Ошибка завершения визита: ' . implode(', ', $updateResult->getErrorMessages())]);
            }
            break;

        case 'get_unload_points':
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
                    sendJsonResponse(['success' => true, 'data' => [], 'message' => 'Адреса для данной компании не найдены.']);
                } else {
                    sendJsonResponse(['success' => true, 'data' => $points]);
                }
            } else {
                sendJsonResponse(['success' => false, 'message' => 'Компания не найдена для этой сделки.']);
            }
            break;

        case 'update_unload_point':
            $pointId = (int)$_POST['point_id'];
            if ($pointId <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Некорректный ID адреса.']);
            }

            $updateResult = DealTable::update($dealId, ['UF_UNLOAD_DOCS' => $pointId]);

            if ($updateResult->isSuccess()) {
                sendJsonResponse(['success' => true, 'message' => 'Пункт разгрузки успешно обновлен.']);
            } else {
                sendJsonResponse(['success' => false, 'message' => 'Ошибка обновления пункта разгрузки: ' . implode(', ', $updateResult->getErrorMessages())]);
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
        window.Telegram.WebApp.ready();
        const tg = window.Telegram.WebApp;
        const urlParams = new URLSearchParams(window.location.search);
        if (!urlParams.has('tgWebAppInitData') && tg.initData) {
            const url = window.location.href.split('#')[0]
            window.location.href = url + '&tgWebAppInitData=' + encodeURIComponent(tg.initData);
        } else {
            document.body.innerHTML = '<div class="flex items-center justify-center h-screen bg-gray-100 dark:bg-gray-900"><div class="text-center p-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg"><h1 class="text-2xl font-bold text-red-600 dark:text-red-400">Доступ запрещен</h1><p class="mt-2 text-gray-700 dark:text-gray-300">Ошибка верификации пользователя Telegram или ваш аккаунт не привязан к системе.</p></div></div>';
        }
    </script></html>
    <?php
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
    die();
}

$startParam = $_GET['id'] ?? '';
$deal_id_parts = explode("_", htmlspecialcharsbx($startParam));
$id_deal = (int)($deal_id_parts[1] ?? 0);

if ($id_deal <= 0) {
    echo "<p>Ошибка: ID сделки не передан или имеет неверный формат.</p>";
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
    die();
}

// Получаем актуальные данные о сделке перед рендерингом страницы
$deal = CCrmDeal::getList([], ['ID' => $id_deal], ['STAGE_ID', 'COMMENTS', '*', 'UF_*'])->fetch();

$latitudeField = Option::get(MODULE_ID, 'latitude_field', 'UF_CRM_1716383617122');
$longitudeField = Option::get(MODULE_ID, 'longitude_field', 'UF_CRM_1716383636592');
$latitude = $deal[$latitudeField] ?? null;
$longitude = $deal[$longitudeField] ?? null;

// Получаем информацию о стадии сделки
$stageId = $deal['STAGE_ID'];
$stageList = StatusTable::getList([
    'filter' => ['STATUS_ID' => $stageId],
    'limit' => 1
])->fetch();
$stageName = $stageList ? $stageList['NAME'] : $stageId;
$stageSemantic = $stageList ? $stageList['SEMANTICS'] : '';

$winStages = json_decode(Option::get(MODULE_ID, 'deal_stage_filter_win', '[]'));
$loseStages = json_decode(Option::get(MODULE_ID, 'deal_stage_filter_lose', '[]'));

// НОВЫЙ БЛОК - ПОЛУЧЕНИЕ ТЕКУЩЕГО АДРЕСА
$currentUnloadPointId = (int)$deal['UF_UNLOAD_DOCS'];
$currentUnloadPointName = '';
if ($currentUnloadPointId > 0 && Loader::includeModule('iblock')) {
    $pointRes = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => 206, 'ID' => $currentUnloadPointId],
        false,
        false,
        ['NAME']
    )->fetch();
    if ($pointRes) {
        $currentUnloadPointName = $pointRes['NAME'];
    }
}
// КОНЕЦ НОВОГО БЛОКА
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Управление визитом</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        :root { color-scheme: light dark; }
        body {
            background-color: var(--tg-theme-bg-color, #f3f4f6);
            color: var(--tg-theme-text-color, #111827);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            padding: 16px;
        }
        .card {
            background-color: var(--tg-theme-secondary-bg-color, #ffffff);
            border-radius: 12px; padding: 16px; margin-bottom: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .form-textarea, .form-select {
            width: 100%;
            border: 1px solid var(--tg-theme-hint-color, #d1d5db);
            background-color: var(--tg-theme-bg-color, #ffffff);
            color: var(--tg-theme-text-color, #000000);
            border-radius: 8px; padding: 10px; font-size: 16px;
        }
        .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--tg-theme-button-color, #3b82f6);
            box-shadow: 0 0 0 2px var(--tg-theme-button-color, #3b82f6);
        }
        .label { display: block; font-weight: 600; margin-bottom: 6px; }
        .btn {
            display: inline-flex; justify-content: center; align-items: center;
            width: 100%; padding: 12px; border-radius: 8px; font-weight: 600;
            background-color: var(--tg-theme-button-color, #3b82f6);
            color: var(--tg-theme-button-text-color, #ffffff);
            cursor: pointer; transition: background-color 0.2s;
            border: none;
        }
        .btn:hover:not(:disabled) {
            filter: brightness(0.9);
        }
        .btn:disabled {
            opacity: 0.6; cursor: not-allowed;
        }
        .btn-success { background-color: #22c55e; }
        .btn-success:hover:not(:disabled) { background-color: #16a34a; }
        .btn-fail { background-color: #ef4444; }
        .btn-fail:hover:not(:disabled) { background-color: #dc2626; }
        .btn-confirm { background-color: #3b82f6; }
        .btn-confirm:hover:not(:disabled) { background-color: #2563eb; }
        
        /* НОВЫЕ СТИЛИ ДЛЯ ГОЛОСОВОГО ВВОДА */
        .voice-input-container {
            position: relative;
        }
        #record-btn {
            position: absolute;
            right: 8px;
            bottom: 8px;
            width: 40px;
            height: 40px;
            padding: 0;
            border-radius: 50%;
            background-color: var(--tg-theme-button-color, #3b82f6);
            color: var(--tg-theme-button-text-color, #ffffff);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #recording-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.7);
            z-index: 100;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
        }
        #recording-timer {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        .recording-dot {
            height: 12px;
            width: 12px;
            background-color: #ef4444;
            border-radius: 50%;
            display: inline-block;
            animation: recording-blink 1.5s infinite;
            margin-right: 8px;
        }
        @keyframes recording-blink {
            0% { opacity: 1; }
            50% { opacity: 0.3; }
            100% { opacity: 1; }
        }
        #recording-controls button {
             width: 120px; margin: 0 10px;
        }
    </style>
</head>
<body>

    <h1 class="text-2xl font-bold mb-2">Управление визитом</h1>
    <p class="mb-4">
        <a href="/crm/deal/details/<?=$id_deal?>/" target="_blank" class="text-sm" style="color: var(--tg-theme-link-color, #3b82f6);">Сделка #<?=$id_deal?>: <?=$deal['TITLE']?></a>
    </p>

    <div class="card mb-4">
        <h2 class="text-xl font-bold mb-2">Текущий статус</h2>
        <div class="p-3 rounded-lg text-sm font-semibold 
            <?php
            if ($stageSemantic === PhaseSemantics::SUCCESS) {
                echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
            } elseif ($stageSemantic === PhaseSemantics::FAILURE) {
                echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
            } else {
                echo 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
            }
            ?>">
            <?= htmlspecialchars($stageName) ?>
        </div>
    </div>

    <div class="card">
        <h2 class="text-xl font-bold mb-4">📍 Геолокация</h2>
        <div id="location-info" class="mb-4 text-sm">
            <?php if ($latitude && $longitude): ?>
                <p class="text-green-600 dark:text-green-400 font-semibold mb-2">Геолокация уже установлена!</p>
                <a href="https://yandex.ru/maps/?pt=<?= $longitude ?>,<?= $latitude ?>&z=16&l=map" target="_blank" class="text-blue-500 underline">Посмотреть на карте</a>
            <?php else: ?>
                <p class="text-yellow-600 dark:text-yellow-400">Геолокация не установлена.</p>
            <?php endif; ?>
        </div>
        <button id="send-geo-btn" class="btn">Отправить мою геолокацию</button>
    </div>

    <div class="card">
        <h2 class="text-xl font-bold mb-4">🏠 Изменение пункта разгрузки</h2>
        <div class="mb-4">
            <label class="label">Текущий пункт разгрузки:</label>
            <div id="current-unload-point" class="bg-gray-200 dark:bg-gray-700 p-3 rounded-lg text-sm whitespace-pre-wrap">
                <?= $currentUnloadPointName ? htmlspecialchars($currentUnloadPointName) : 'Не выбран' ?>
            </div>
        </div>
        <div>
            <label for="unload-point-select" class="label">Выберите новый пункт разгрузки:</label>
            <select id="unload-point-select" class="form-select">
                <option value="">-- Загрузка адресов... --</option>
            </select>
            <button id="update-unload-point-btn" class="btn mt-4">Сохранить</button>
        </div>
    </div>

    <div class="card mb-4">
        <div class="flex justify-between items-center cursor-pointer" onclick="toggleCommentVisibility()">
            <h2 class="text-xl font-bold mb-2">Комментарий к сделке</h2>
            <svg id="comment-arrow" class="w-4 h-4 transition-transform duration-300" fill="none" stroke="#9CA3AF" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </div>
        <div id="comment-content" class="overflow-hidden transition-all duration-300 ease-in-out" style="max-height: 0px; opacity: 0;">
            <div id="comment-inner" class="p-3 rounded-lg bg-gray-100 dark:bg-gray-700 overflow-x-auto">
                <?php
                function parseBBCode($text) {
                    // Remove extra whitespace but preserve line breaks
                    $text = trim($text);
                    
                    // Convert BBCode to HTML
                    $bbcodeMap = [
                        // Paragraphs
                        '/\[p\](.*?)\[\/p\]/is' => '<p>$1</p>',
                        // Bold
                        '/\[b\](.*?)\[\/b\]/is' => '<strong>$1</strong>',
                        // Italic
                        '/\[i\](.*?)\[\/i\]/is' => '<em>$1</em>',
                        // Underline
                        '/\[u\](.*?)\[\/u\]/is' => '<u>$1</u>',
                        // Strikethrough
                        '/\[s\](.*?)\[\/s\]/is' => '<del>$1</del>',
                        // Links
                        '/\[url\=(.*?)\](.*?)\[\/url\]/is' => '<a href="$1" target="_blank" style="color: var(--tg-theme-link-color, #3b82f6); text-decoration: underline;">$2</a>',
                        '/\[url\](.*?)\[\/url\]/is' => '<a href="$1" target="_blank" style="color: var(--tg-theme-link-color, #3b82f6); text-decoration: underline;">$1</a>',
                        // Lists
                        '/\[list\](.*?)\[\/list\]/is' => '<ul>$1</ul>',
                        '/\[list\=1\](.*?)\[\/list\]/is' => '<ol>$1</ol>',
                        '/\[\*\](.*?)\[\/\*\]/is' => '<li>$1</li>',
                        // Line breaks
                        '/\n/' => '<br>',
                    ];
                    
                    foreach ($bbcodeMap as $pattern => $replacement) {
                        $text = preg_replace($pattern, $replacement, $text);
                    }
                    
                    return $text;
                }
                
                $comments = $deal['COMMENTS'] ?? 'Комментарии отсутствуют';
                echo $comments ? parseBBCode($comments) : 'Комментарии отсутствуют';
                ?>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h2 class="text-xl font-bold mb-4">🔚 Завершить визит</h2>
        
        <div class="mb-4">
            <label for="comment-textarea" class="label">Комментарий к итогу визита:</label>
            <div class="voice-input-container">
                <textarea id="comment-textarea" class="form-textarea" rows="4" placeholder="Введите ваш комментарий или используйте голосовой ввод"></textarea>
                <button id="record-btn" title="Голосовой ввод">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line></svg>
                </button>
            </div>
        </div>

        <button id="win-visit-btn" class="btn btn-success mb-2">✅ Визит состоялся</button>
        
        <div id="lose-status-container" style="display: none;">
            <label for="lose-status-select" class="label mt-4">Выберите статус неуспешного визита:</label>
            <select id="lose-status-select" class="form-select">
                <option value="">-- Выберите статус --</option>
                <?php foreach ($loseStages as $stage) {
                    $stageParts = explode('|', $stage);
                    $stageId = $stageParts[0];
                    $stageName = $stageParts[1];
                    echo "<option value='{$stageId}'>{$stageName}</option>";
                } ?>
            </select>
            <button id="confirm-lose-btn" class="btn btn-confirm mt-2">Подтвердить неуспешный визит</button>
        </div>
        
        <button id="lose-visit-btn" class="btn btn-fail mt-2">❌ Визит не состоялся</button>

    </div>

    <div id="recording-overlay" style="display: none;">
        <p class="text-lg mb-4"><span class="recording-dot"></span>Идёт запись...</p>
        <div id="recording-timer">00:00</div>
        <div id="recording-controls" class="flex">
            <button id="stop-record-btn" class="btn btn-success">Остановить</button>
            <button id="cancel-record-btn" class="btn btn-fail">Отмена</button>
        </div>
    </div>

    <script>
        const tg = window.Telegram.WebApp;
        tg.ready();
        tg.expand();
        const dealId = <?=$id_deal?>;
        const mainScriptUrl = window.location.href;
        const currentUnloadPointId = <?= json_encode($currentUnloadPointId) ?>;

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

        document.getElementById('send-geo-btn').addEventListener('click', async () => {
            if (!navigator.geolocation) {
                tg.showAlert('Ваш браузер не поддерживает геолокацию.');
                return;
            }
            showLoading();
            navigator.geolocation.getCurrentPosition(async (position) => {
                const { latitude, longitude } = position.coords;
                const formData = new FormData();
                formData.append('action', 'update_location');
                formData.append('deal_id', dealId);
                formData.append('latitude', latitude);
                formData.append('longitude', longitude);
                formData.append('tgWebAppInitData', tg.initData);
                try {
                    const response = await fetch(mainScriptUrl, { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.success) {
                        tg.showAlert(result.message);
                        const locationInfo = document.getElementById('location-info');
                        locationInfo.innerHTML = `
                            <p class="text-green-600 dark:text-green-400 font-semibold mb-2">Геолокация уже установлена!</p>
                            <a href="https://yandex.ru/maps/?pt=${longitude},${latitude}&z=16&l=map" target="_blank" class="text-blue-500 underline">Посмотреть на карте</a>
                        `;
                    } else {
                        tg.showAlert(result.message);
                    }
                } catch (error) {
                    tg.showAlert('Ошибка сети при отправке геолокации.');
                } finally {
                    hideLoading();
                }
            }, (error) => {
                tg.showAlert('Не удалось получить геолокацию: ' + error.message);
                hideLoading();
            });
        });

        document.getElementById('win-visit-btn').addEventListener('click', () => {
            tg.showConfirm('Вы уверены, что хотите отметить визит как успешный?', async (isConfirmed) => {
                if (isConfirmed) {
                    const commentTextarea = document.getElementById('comment-textarea');
                    const comment = commentTextarea.value;
                    const winStageId = "<?= (!empty($winStages) && isset(explode('|', $winStages[0])[0])) ? explode('|', $winStages[0])[0] : '' ?>";

                    if (!winStageId) {
                        tg.showAlert('Ошибка: Не настроена успешная стадия завершения.');
                        return;
                    }

                    showLoading();
                    const formData = new FormData();
                    formData.append('action', 'end_visit');
                    formData.append('deal_id', dealId);
                    formData.append('stage_id', winStageId);
                    formData.append('comment', comment); // Отправляем комментарий
                    formData.append('tgWebAppInitData', tg.initData);

                    try {
                        const response = await fetch(mainScriptUrl, { method: 'POST', body: formData });
                        const result = await response.json();

                        if (result.success) {
                            const resultUrl = new URL('https://office.rocadatech.ru/local/modules/rocada.telegram/lib/app/result/');
                            resultUrl.searchParams.append('id', `D_${dealId}`);
                            resultUrl.searchParams.append('stage_id', winStageId);
                            if (tg.initData) {
                                resultUrl.searchParams.append('tgWebAppInitData', tg.initData);
                            }
                            window.location.href = resultUrl.href;
                        } else {
                            tg.showAlert(result.message);
                        }
                    } catch (error) {
                        tg.showAlert('Ошибка сети при завершении визита.');
                    } finally {
                        hideLoading();
                    }
                }
            });
        });
        document.getElementById('lose-visit-btn').addEventListener('click', () => {
            const container = document.getElementById('lose-status-container');
            container.style.display = container.style.display === 'none' ? 'block' : 'none';
        });

        document.getElementById('confirm-lose-btn').addEventListener('click', () => {
            const select = document.getElementById('lose-status-select');
            const stageId = select.value;
            if (!stageId) {
                tg.showAlert('Пожалуйста, выберите статус из списка.');
                return;
            }
            tg.showConfirm('Вы уверены, что хотите отметить визит как неуспешный?', async (isConfirmed) => {
                if (isConfirmed) {
                    const commentTextarea = document.getElementById('comment-textarea');
                    const comment = commentTextarea.value;
                    
                    showLoading();
                    const formData = new FormData();
                    formData.append('action', 'end_visit');
                    formData.append('deal_id', dealId);
                    formData.append('stage_id', stageId);
                    formData.append('comment', comment); // Отправляем комментарий
                    formData.append('tgWebAppInitData', tg.initData);
                    try {
                        const response = await fetch(mainScriptUrl, { method: 'POST', body: formData });
                        const result = await response.json();

                        if (result.success) {
                            tg.showPopup({
                                title: 'Успешно!',
                                message: result.message,
                                buttons: [{ type: 'ok' }]
                            }, () => tg.close());
                        } else {
                            tg.showAlert(result.message);
                        }
                    } catch (error) {
                        tg.showAlert('Ошибка сети при завершении визита.');
                    } finally {
                        hideLoading();
                    }
                }
            });
        });

        async function fetchUnloadPoints() {
            showLoading();
            const select = document.getElementById('unload-point-select');
            const formData = new FormData();
            formData.append('action', 'get_unload_points');
            formData.append('deal_id', dealId);
            formData.append('tgWebAppInitData', tg.initData);
            try {
                const response = await fetch(mainScriptUrl, { method: 'POST', body: formData });
                const result = await response.json();
                select.innerHTML = '';
                if (result.success && result.data.length > 0) {
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = '-- Выберите адрес --';
                    select.appendChild(defaultOption);
                    result.data.forEach(point => {
                        const option = document.createElement('option');
                        option.value = point.id;
                        option.textContent = point.name;
                        if (point.id === currentUnloadPointId) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                } else if (result.success && result.data.length === 0) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = '-- Адреса для компании не найдены --';
                    select.appendChild(option);
                } else {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = '-- Ошибка: ' + (result.message || 'Неизвестная ошибка') + ' --';
                    select.appendChild(option);
                }
            } catch (error) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = '-- Ошибка сети при загрузке адресов. --';
                select.appendChild(option);
                tg.showAlert('Ошибка сети при загрузке адресов.');
            } finally {
                hideLoading();
            }
        }
        fetchUnloadPoints();

        document.getElementById('update-unload-point-btn').addEventListener('click', async () => {
            const select = document.getElementById('unload-point-select');
            const pointId = select.value;
            if (!pointId) {
                tg.showAlert('Пожалуйста, выберите адрес из списка.');
                return;
            }
            showLoading();
            const formData = new FormData();
            formData.append('action', 'update_unload_point');
            formData.append('deal_id', dealId);
            formData.append('point_id', pointId);
            formData.append('tgWebAppInitData', tg.initData);
            try {
                const response = await fetch(mainScriptUrl, { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    tg.showAlert(result.message);
                    const selectedText = select.options[select.selectedIndex].text;
                    document.getElementById('current-unload-point').textContent = selectedText;
                } else {
                    tg.showAlert(result.message);
                }
            } catch (error) {
                tg.showAlert('Ошибка сети при обновлении адреса.');
            } finally {
                hideLoading();
            }
        });


        // --- НОВЫЙ КОД ДЛЯ ГОЛОСОВОГО ВВОДА ---
        const recordBtn = document.getElementById('record-btn');
        const stopRecordBtn = document.getElementById('stop-record-btn');
        const cancelRecordBtn = document.getElementById('cancel-record-btn');
        const recordingOverlay = document.getElementById('recording-overlay');
        const recordingTimerEl = document.getElementById('recording-timer');
        
        let mediaRecorder;
        let audioChunks = [];
        let timerInterval;

        const startRecording = async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                
                mediaRecorder.addEventListener('dataavailable', event => {
                    audioChunks.push(event.data);
                });

                mediaRecorder.addEventListener('stop', () => {
                    // Останавливаем все дорожки, чтобы микрофон выключился
                    stream.getTracks().forEach(track => track.stop()); 
                    if (audioChunks.length > 0) {
                        const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                        sendAudioToServer(audioBlob);
                    }
                    audioChunks = []; // Очищаем для следующей записи
                });

                audioChunks = [];
                mediaRecorder.start();
                recordingOverlay.style.display = 'flex';
                startTimer();
            } catch (error) {
                tg.showAlert('Не удалось получить доступ к микрофону. Пожалуйста, разрешите доступ в настройках браузера. Ошибка: ' + error.message);
            }
        };
        
        const stopRecording = () => {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                mediaRecorder.stop();
            }
            recordingOverlay.style.display = 'none';
            stopTimer();
        };

        const cancelRecording = () => {
            audioChunks = []; // Важно сбросить данные, чтобы onstop не отправил пустой файл
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

        const stopTimer = () => {
            clearInterval(timerInterval);
        };

        const sendAudioToServer = async (audioBlob) => {
            showLoading();

            const formData = new FormData();
            formData.append('audio', audioBlob, 'recording.webm');

            // Структура, которую ожидает ваш Node.js скрипт.
            // Мы создаем "фиктивный" инфоповод, чтобы AI сфокусировался на поиске общего комментария.
            const formStructure = {
                infopovody: [{
                    id: "general_comment",
                    name: "Общий комментарий по визиту",
                    statuses: [],
                    products: [],
                }]
            };
            formData.append('form_structure', JSON.stringify(formStructure));

            try {
                const response = await fetch('https://proxytestitnebo.fly.dev/process-audio', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();

                if (result.success && result.result) {
                    const commentText = findCommentInResponse(result.result);
                    if (commentText) {
                        const commentTextarea = document.getElementById('comment-textarea');
                        const existingText = commentTextarea.value.trim();
                        commentTextarea.value = existingText ? `${existingText}\n${commentText}` : commentText;
                        tg.showAlert('Комментарий из аудио добавлен в поле.');
                    } else {
                        tg.showAlert('В аудио не удалось распознать текст для комментария.');
                    }
                } else {
                    tg.showAlert('Ошибка обработки аудио: ' + (result.error || 'Неизвестный ответ от сервера.'));
                }
            } catch (error) {
                tg.showAlert('Сетевая ошибка при отправке аудио: ' + error.message);
            } finally {
                hideLoading();
            }
        };

        // Гибкая функция для поиска текста комментария в ответе от AI
        const findCommentInResponse = (responseObject) => {
            // Ищем любой текст в любом поле, так как точная структура ответа неизвестна
            for (const key in responseObject) {
                if (typeof responseObject[key] === 'object' && responseObject[key] !== null) {
                    const nested = responseObject[key];
                    for (const nestedKey in nested) {
                         if (typeof nested[nestedKey] === 'object' && nested[nestedKey] !== null && 'value' in nested[nestedKey]) {
                            const value = nested[nestedKey].value;
                            if(typeof value === 'string' && value.trim() !== '') {
                                return value;
                            }
                        }
                    }
                }
            }
            return null;
        };

        recordBtn.addEventListener('click', startRecording);
        stopRecordBtn.addEventListener('click', stopRecording);
        cancelRecordBtn.addEventListener('click', cancelRecording);

        // --- IMPROVED COLLAPSIBLE COMMENT SECTION ---
        let commentExpanded = false;
        
        function toggleCommentVisibility() {
            const content = document.getElementById('comment-content');
            const inner = document.getElementById('comment-inner');
            const arrow = document.getElementById('comment-arrow');
            
            if (commentExpanded) {
                // Collapse
                content.style.maxHeight = '0px';
                content.style.opacity = '0';
                arrow.style.transform = 'rotate(0deg)';
            } else {
                // Expand to the actual height of the content
                content.style.maxHeight = inner.scrollHeight + 'px';
                content.style.opacity = '1';
                arrow.style.transform = 'rotate(180deg)';
            }
            
            commentExpanded = !commentExpanded;
        }
        
        // Recalculate height on window resize to handle responsive changes
        window.addEventListener('resize', function() {
            const content = document.getElementById('comment-content');
            const inner = document.getElementById('comment-inner');
            
            if (commentExpanded) {
                content.style.maxHeight = inner.scrollHeight + 'px';
            }
        });
    </script>
</body>
</html>