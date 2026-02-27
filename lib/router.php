<?php

define("NOT_CHECK_PERMISSIONS", true);
define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_STATISTIC", true);
define("STOP_STATISTICS", true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/bx_root.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
date_default_timezone_set('Europe/Moscow'); // Выставляем таймзону Москвы (+03:00)

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\UserTable;
use Rocada\Telegram\TelegramBot;
use Bitrix\Crm\DealTable;
use Bitrix\Main\Config\Option;
use Bitrix\Sender\Integration\Im\Notification;
use Bitrix\Crm\Timeline\CommentEntry;

Loader::includeModule('main');
Loader::includeModule('crm');
Loader::includeModule('rocada.telegram');
Loader::includeModule('highloadblock');
Loader::includeModule('im');

$telegramBot = new TelegramBot();

$requestBody = file_get_contents('php://input');
$requestData = json_decode($requestBody, true);

if (!$requestData) {
    exit;
}

$callbackQuery = $requestData['callback_query'] ?? null;
$message = $requestData['message'] ?? null;
$chatId = $message['chat']['id'] ?? ($callbackQuery['message']['chat']['id'] ?? null);
$text = $message['text'] ?? null;
$callbackData = $callbackQuery['data'] ?? null;
$location = $message['location'] ?? null;

$cmd = [
    'visits' => '🏰 Визиты на сегодня',
    'visitsTomorrow' => '🏰 Визиты на завтра',
    'sendComment' => '✏️ Запросить описание',
    'addComment' => '✍️ Написать комментарий',
    'backToDeal' => "⬅ Назад к сделке",
];
$needAuthMessage = "⚠️ *Добро пожаловать!* \n\nВы должны авторизоваться для дальнейшего использования бота. Ссылку авторизации можно запросить у Вашего руководителя.";

$moduleId = 'rocada.telegram';

$itemsPerPage = 5; // Количество сделок на одной странице
$page = 1; // По умолчанию первая страница

if (preg_match('/page_(\d+)/', $callbackData, $matches)) {
    $page = (int)$matches[1];
}

function getMainKeyboard() {
    global $cmd;
    global $moduleId;
    $keyboard = [
        'keyboard' => [
            [
                ['text' => $cmd['visits']],
            ]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    
    if (Option::get($moduleId, 'deal_stage_filter_tomorrow', '')) $keyboard['keyboard'][0][] = ['text' => $cmd['visitsTomorrow']];
    return $keyboard;
}

class HighloadBlockHandler {
    private $entityClass;

    public function __construct($hlblockName) {
        $hlblock = HighloadBlockTable::getList([
            'filter' => ['=NAME' => $hlblockName]
        ])->fetch();

        if ($hlblock) {
            $entity = HighloadBlockTable::compileEntity($hlblock);
            $this->entityClass = $entity->getDataClass();
        } else {
            throw new Exception("⚠️ Highload-block not found");
        }
    }

    public function getUserRecordByInfo($info) {
        return $this->entityClass::getList([
            'filter' => ['UF_INFO' => $info],
            'select' => ['ID', 'UF_USER', 'UF_STAGE', 'UF_INFO']
        ])->fetch();
    }

    public function getUserRecord($userId) {
        return $this->entityClass::getList([
            'filter' => ['UF_USER' => $userId],
            'select' => ['ID', 'UF_STAGE', 'UF_INFO']
        ])->fetch();
    }

    public function createOrUpdateUserRecord($userId, $stage, $info) {
        $record = $this->getUserRecord($userId);
        if ($record) {
            $this->entityClass::update($record['ID'], [
                'UF_STAGE' => $stage,
                'UF_INFO' => $info
            ]);
        } else {
            $this->entityClass::add([
                'UF_USER' => $userId,
                'UF_STAGE' => $stage,
                'UF_INFO' => $info
            ]);
        }
    }

    public function clearUserRecord($userId) {
        $record = $this->getUserRecord($userId);
        if ($record) {
            $this->entityClass::update($record['ID'], [
                'UF_STAGE' => null,
                'UF_INFO' => null
            ]);
        }
    }
}

function sendDealsList($telegramBot, $chatId, $page, $moduleId, $user, $callbackQuery = null, $tomorrow = false) {
    global $itemsPerPage;

    $stage = $tomorrow ? 'deal_stage_filter_tomorrow' : 'deal_stage_filter';

    $dealFilter = [
        'STAGE_ID' => Option::get($moduleId, $stage, ''),
        'ASSIGNED_BY_ID' => $user['ID'],
    ];
    if ($user['ID'] == 1062) unset($dealFilter['ASSIGNED_BY_ID']);

    $deals = DealTable::getList([
        'filter' => $dealFilter,
        'select' => ['ID', 'TITLE', Option::get($moduleId, 'latitude_field', '')],
        'order' => [Option::get($moduleId, 'latitude_field', '') => 'ASC'],
        'limit' => $itemsPerPage,
        'offset' => ($page - 1) * $itemsPerPage
    ])->fetchAll();

    $nextPageDeals = DealTable::getList([
        'filter' => $dealFilter,
        'select' => ['ID'],
        'order' => [Option::get($moduleId, 'latitude_field', '') => 'ASC'],
        'limit' => 1,
        'offset' => $page * $itemsPerPage
    ])->fetch();

    $visitsMessage = "📋 *Список визитов на ".($tomorrow ? 'завтра' : 'сегодня')."*\n(стр. $page):";
    $inlineKeyboard = ['inline_keyboard' => []];

    foreach ($deals as $deal) {
        $inlineKeyboard['inline_keyboard'][] = [
            ['text' => ($deal[Option::get($moduleId, 'latitude_field', '')] ? '✅' : '📝') . " [{$deal['ID']}] {$deal['TITLE']}", 'callback_data' => "deal_{$deal['ID']}"]
        ];
    }

    $paginationButtons = [];
    if ($page > 1) {
        $paginationButtons[] = ['text' => "⬅ Назад", 'callback_data' => 'page_' . ($page - 1) . ($tomorrow ? '?tomorrow' : '')];
    }
    if ($nextPageDeals) {
        $paginationButtons[] = ['text' => "Вперед ➡", 'callback_data' => 'page_' . ($page + 1) . ($tomorrow ? '?tomorrow' : '')];
    }
    $inlineKeyboard['inline_keyboard'][] = $paginationButtons;

    if ($callbackQuery) {
        $telegramBot->editMessage($chatId, $callbackQuery['message']['message_id'], $visitsMessage, $inlineKeyboard);
    } else {
        $telegramBot->sendMessage($chatId, $visitsMessage, $inlineKeyboard);
    }
}

$isAuthorized = UserTable::getList([
    'filter' => ['UF_ROCADOMED_TELEGEAM_ID' => $chatId],
    'select' => ['ID']
])->fetch();
$hlHandler = new HighloadBlockHandler('UserStageInfo');
$record = $hlHandler->getUserRecord($isAuthorized['ID']);

function selectDealError() {
    global $hlHandler;
    global $telegramBot;
    global $record;
    global $moduleId;
    global $chatId;
    
    if ($record && $record['UF_INFO']) {
        return $record;
    } else {
        $telegramBot->sendMessage($chatId, '❌ *Сначала выберите сделку!*');
    }
    return false;
}

function backToDeal($dealId) {
    global $hlHandler;
    global $isAuthorized;
    global $chatId;
    global $cmd;
    global $telegramBot;

    // Обновление статуса и айди сделки в Highload-блоке
    $hlHandler->createOrUpdateUserRecord($isAuthorized['ID'], 'location_requested', $dealId);

    // Получение информации о сделке
    $deal = DealTable::getById($dealId)->fetch();
    $dealInfo = "💼 *Сделка:* \n\n🆔 - _{$deal['ID']}_ \n*✏️ - {$deal['TITLE']}*";

    // Кнопки для отправки геолокации и возвращения назад
    $keyboard = [
        'keyboard' => [
            [
                ['text' => "📍 Отправить гео", 'request_location' => true],
                ['text' => $cmd['sendComment']],
            ],
            [
                ['text' => $cmd['addComment']],
                ['text' => "⬅ Назад"]
            ]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    $telegramBot->sendMessage($chatId, "$dealInfo.\n\n📍 Пожалуйста, отправьте вашу локацию.", $keyboard);
}

if ($chatId) {
    $textParts = explode(' ', $text);
    $command = $textParts[0] ?? '';
    $uuid = $textParts[1] ?? null;

    $telegramBot->crmId = $isAuthorized['ID'];

    if ($isAuthorized) {
        $telegramBot->log("Входящее сообщение от авторизованного пользователя", json_encode($requestData));
    } else {
        $telegramBot->log("Входящее сообщение от неавторизованного пользователя", json_encode($requestData));
    }

    if ($text === '/start' || $command === '/start') {
        if ($uuid) {
            $record = $hlHandler->getUserRecordByInfo($uuid);
            if ($record && $record['UF_STAGE'] === 'invited') {
                $user = new CUser;

                $existingUserFilter = [
                    'UF_ROCADOMED_TELEGEAM_ID' => $chatId
                ];
                $existingUsers = CUser::GetList(
                    $by = 'ID',
                    $order = 'ASC',
                    $existingUserFilter
                );
                $notifier = new Notification();

                $sendNotifyOption = boolval(Option::get($moduleId, 'notify_by_auth', ''));

                while ($existingUser = $existingUsers->Fetch()) {
                    $fieldsToReset = [
                        'UF_ROCADOMED_TELEGEAM_ID' => ''
                    ];

                    if ($sendNotifyOption) {
                        $res = $notifier->addTo($existingUser['ID'])->withMessage(
                            "RocadaMed Telegram Bot: Авторизация была сброшена на текущем bitrix24 аккаунте"
                        )->send();
                    }

                    $user->Update($existingUser['ID'], $fieldsToReset);

                    $telegramBot->log("Сброс авторизации для", $existingUser['ID']);
                }

                // Обновляем поле UF_ROCADOMED_TELEGEAM_ID для текущего пользователя
                $fields = [
                    'UF_ROCADOMED_TELEGEAM_ID' => $chatId
                ];

                if ($sendNotifyOption) {
                    $notifier->addTo($record['UF_USER'])->withMessage(
                        "RocadaMed Telegram Bot: Ссылка авторизации была активирована для Вашего аккаунта битрикс24"
                    )->send();
                }

                $telegramBot->log("Авторизация пользователя",  json_encode($existingUser['ID'], $existingUser));

                $user->Update($record['UF_USER'], $fields);

                $hlHandler->createOrUpdateUserRecord($record['UF_USER'], null, null);

                $telegramBot->sendMessage($chatId, "🎉 *Добро пожаловать!*\nВаш аккаунт успешно активирован", getMainKeyboard());
            } else {
                $responseMessage = "❌ *Пользователь с таким кодом не найден*";
                $telegramBot->sendMessage($chatId, $responseMessage);
            }
        } else {
            if ($isAuthorized) {
                $responseMessage = "👋 *Добро пожаловать!* \n\nВыберите действие:";
                $keyboard = getMainKeyboard();
                $telegramBot->sendMessage($chatId, $responseMessage, $keyboard);
            } else {
                $telegramBot->sendMessage($chatId, $needAuthMessage);
            }
            exit;
        }
        exit;
    }

    if (!$isAuthorized) {
        $telegramBot->sendMessage($chatId, $needAuthMessage);
	    exit;
    }

    if ($text == $cmd['visits'] || $text == $cmd['visitsTomorrow'] || strpos($callbackData, 'page_') === 0) {
        // Показать список визитов с пагинацией
        sendDealsList($telegramBot, $chatId, $page, $moduleId, $isAuthorized, $callbackQuery, 
            strpos($callbackData, 'page_') === 0
                ? (strpos($callbackData, '?tomorrow') ? true : false)
                : $text == $cmd['visitsTomorrow']
        );
    } else if ($text == $cmd['sendComment']) {
        $record = selectDealError();
        if ($record) {
            $deal = DealTable::getById($record['UF_INFO'])->fetch();
            $comment = $deal[Option::get($moduleId, 'comment_field', '')];
            if (!$comment) {
                $telegramBot->sendMessage($chatId, '❌ *Комментарий пуст!*');
            } else {
                $telegramBot->sendTextFile($chatId, $comment ?? '', $deal['TITLE'].'.txt');
            }
        }
    } else if ($text == $cmd['addComment']) {
        $record = selectDealError();
        if ($record) {
            $hlHandler->createOrUpdateUserRecord($isAuthorized['ID'], 'add_comment', $record['UF_INFO']);
            $keyboard = [
                'keyboard' => [
                    [
                        ['text' => $cmd['backToDeal']]
                    ]
                ],
                'resize_keyboard' => true,
            ];
            $telegramBot->sendMessage($chatId, '✍️ *Напишите комментарий: *', $keyboard);
        }
    } else if ($text == $cmd['backToDeal'] && $record['UF_INFO'] && $record['UF_STAGE'] == 'add_comment') {
        backToDeal($record['UF_INFO']);
    } else if ($record['UF_STAGE'] == 'add_comment') {
        if (strlen($text)) {
            $commentId = CommentEntry::create([
                'TEXT' => $text,
                'BINDINGS' => [['ENTITY_TYPE_ID' => \CCrmOwnerType::Deal, 'ENTITY_ID' => $record['UF_INFO']]],
                'AUTHOR_ID' => $isAuthorized['ID'],
            ]);
            $telegramBot->sendMessage($chatId, '✅ *Комментарий успешно добавлен!*');
            backToDeal($record['UF_INFO']);
        } else {
            $telegramBot->sendMessage($chatId, '❌ *Введите комментарий для сделки!*');
        }
    } else if (strpos($callbackData, 'deal_') === 0) {
        // Сохранение записи про выбранную сделку для текущего пользователя
        $dealId = str_replace('deal_', '', $callbackData);
        backToDeal($dealId);
    } else if ($location) {
        // Обработка отправки геолокации
        $latitude = $location['latitude'];
        $longitude = $location['longitude'];

        // Поиск записи про выбранную сделку
        $record = $hlHandler->getUserRecord($isAuthorized['ID']);

        if ($record && $record['UF_STAGE'] === 'location_requested') {
            // Обновление полей широта и долгота в сделке
            DealTable::update($record['UF_INFO'], [
                Option::get($moduleId, 'latitude_field', '') => $latitude,
                Option::get($moduleId, 'longitude_field', '') => $longitude
            ]);

            $telegramBot->sendMessage($chatId, "✅ *Геолокация успешно обновлена для сделки!*", ['remove_keyboard' => true]);
        } else {
            $telegramBot->sendMessage($chatId, "⚠️ *Сделка не выбрана* \n\n Пожалуйста, выберите сделку перед отправкой локации");
        }
    } else if ($text == '⬅ Назад') {
        // Сбросить статус пользователя и показать список визитов
        $hlHandler->clearUserRecord($isAuthorized['ID']);
        $telegramBot->sendMessage($chatId, "🔄 *Вы находитесь в главном меню*", getMainKeyboard());
        sendDealsList($telegramBot, $chatId, $page, $moduleId, $isAuthorized);
    } else {
        $visitsMessage = "❌ *Команда не найдена!* Пожалуйста, используйте доступные команды";
        $telegramBot->sendMessage($chatId, $visitsMessage);
    }
} else {
    echo "❌ Chat ID отсутствует.";
}
?>
