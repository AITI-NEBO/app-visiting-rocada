<?php
use Bitrix\Main\Loader;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Config\Option;
use Bitrix\Crm\Category\DealCategory;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Sender\Integration\Im\Notification;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UserFieldTable;

Loader::includeModule('rocada.telegram');
Loader::includeModule('crm');
Loader::includeModule('intranet');
Loader::includeModule('im');
Loader::includeModule('highloadblock');

$request = HttpApplication::getInstance()->getContext()->getRequest();
$moduleId = 'rocada.telegram';

if ($request->isPost() && check_bitrix_sessid()) {
    // Сохранение настроек
    $telegramBotToken = $request['telegram_bot_token'];
    $notifyMessage = $request['notify_message'];
    $commentField = $request['comment_field'];
    $longitudeField = $request['longitude_field'];
    $latitudeField = $request['latitude_field'];
    $notifyByAuth = $request['notify_by_auth'];

    Option::set($moduleId, 'notify_message', $notifyMessage);
    Option::set($moduleId, 'comment_field', $commentField);
    Option::set($moduleId, 'longitude_field', $longitudeField);
    Option::set($moduleId, 'latitude_field', $latitudeField);
    Option::set($moduleId, 'deal_stage_filter', $request['deal_stage_filter']);
    Option::set($moduleId, 'deal_stage_filter_tomorrow', $request['deal_stage_filter_tomorrow']);
    Option::set($moduleId, 'notify_by_auth', $request['notify_by_auth']);

    $currentDir = __DIR__;
    $documentRoot = $_SERVER['DOCUMENT_ROOT'];
    $relativePath = str_replace($documentRoot, '', $currentDir);
    $domain = 'https://' . $_SERVER['SERVER_NAME'];
    $publicUrl = $domain . $relativePath . '/lib/router.php';
    $httpClientWebhook = new HttpClient();
    $response = $httpClientWebhook->get("https://api.telegram.org/bot$telegramBotToken/setWebhook?url=$publicUrl");
    $res = json_decode($response, true);

    if ($res['ok']) {
        Option::set($moduleId, 'telegram_bot_token', $telegramBotToken);

        if ($request['action'] === 'invite' && !empty($request['EMPLOYEES'])) {
            $chatIds = $request['EMPLOYEES'];

            // Получение Highload-блока
            $hlblock = HighloadBlockTable::getList([
                'filter' => ['=NAME' => 'UserStageInfo']
            ])->fetch();

            if ($hlblock) {
                $entity = HighloadBlockTable::compileEntity($hlblock);
                $entityClass = $entity->getDataClass();

                // Отправка запроса к Telegram API, чтобы получить данные о боте
                $httpClient = new HttpClient();
                $response = $httpClient->get("https://api.telegram.org/bot$telegramBotToken/getMe");
                $botData = json_decode($response, true);

                if ($botData['ok']) {
                    $botUsername = $botData['result']['username'];

                    foreach ($chatIds as $chatId) {
                        if (!$chatId) continue;
                        $uuid = str_replace('.', '', uniqid('', true));
                        $telegramBotUrl = "https://t.me/$botUsername?start=$uuid";
                        $existingRecord = $entityClass::getList([
                            'filter' => ['UF_USER' => $chatId],
                            'select' => ['ID']
                        ])->fetch();

                        if ($existingRecord) {
                            // Удаление старой записи
                            $entityClass::delete($existingRecord['ID']);
                        }

                        // Отправка уведомления
                        $message = "$notifyMessage\n\n$telegramBotUrl";
                        $notifier = new Notification();
                        $res = $notifier->addTo($chatId)->withMessage($message)->send();

                        \CEventLog::Add(
                            array(
                                'SEVERITY' => 'SECURITY',
                                'AUDIT_TYPE_ID' => 'ROCADA_TELEGRAM',
                                'MODULE_ID' => 'rocada.telegram',
                                'DESCRIPTION' => "Отправка приглашения в бот: $chatId | $message",
                            )
                        );

                        // Сохранение новой записи в Highload-блок
                        $entityClass::add([
                            'UF_USER' => $chatId,
                            'UF_STAGE' => 'invited',
                            'UF_INFO' => $uuid
                        ]);
                    }

                    echo '<div class="adm-info-message-wrap adm-info-message-green">
                        <div class="adm-info-message">Приглашения успешно отправлены и сохранены!</div>
                      </div>';
                } else {
                    echo '<div class="adm-info-message-wrap adm-info-message-red">
                        <div class="adm-info-message">Ошибка: не удалось получить информацию о боте.</div>
                      </div>';
                }
            } else {
                echo '<div class="adm-info-message-wrap adm-info-message-red">
                    <div class="adm-info-message">Ошибка: Highload-блок не найден.</div>
                  </div>';
            }
        }
    } else {
        echo '<div class="adm-info-message-wrap adm-info-message-red">
            <div class="adm-info-message">Не удалось установить вебхук для Telegram бота.</div>
          </div>';
    }
}

// Получение сохранённых значений
$telegramBotToken = Option::get($moduleId, 'telegram_bot_token', '');
$notifyMessage = Option::get($moduleId, 'notify_message', '');
$selectedStage = Option::get($moduleId, 'deal_stage_filter', '');
$selectedStageTomorrow = Option::get($moduleId, 'deal_stage_filter_tomorrow', '');
$selectedCommentField = Option::get($moduleId, 'comment_field', '');
$selectedLongitudeField = Option::get($moduleId, 'longitude_field', '');
$selectedLatitudeField = Option::get($moduleId, 'latitude_field', '');
$notifyByAuth = Option::get($moduleId, 'notify_by_auth', '');

// Получение всех стадий всех воронок
$stagesList = [];
$dealCategories = DealCategory::getAll(true);
foreach ($dealCategories as $category) {
    $stages = DealCategory::getStageList($category['ID']);
    foreach ($stages as $stageCode => $stageName) {
        $stagesList[$stageCode] = "(" . $category['NAME'] . ") " . $stageName;
    }
}

// Получение всех пользовательских полей сделки
$iterator = UserFieldTable::getList([
    'select' => array_merge(
        ['*'],
        UserFieldTable::getLabelsSelect()
    ),
    'filter' => [
        '=ENTITY_ID' => 'CRM_DEAL',
    ],
    'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
    'runtime' => [
        UserFieldTable::getLabelsReference('', Loc::getCurrentLang()),
    ],
]);
$fieldOptions = [
	'COMMENTS' => 'Комментарии',
];
while ($f = $iterator->fetch()) {
    $fieldOptions[$f['FIELD_NAME']] = $f['EDIT_FORM_LABEL'];
}
?>

<style>
    /* Основные стили страницы */
    body {
        font-family: Arial, sans-serif;
        background-color: #f0f2f5;
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    #settings {
        width: 100%;
        max-width: 800px;
        margin: 30px auto;
        background-color: #ffffff;
        padding: 30px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
    }

    h2 {
        font-size: 1.5rem;
        color: #333;
        text-align: center;
        margin-bottom: 20px;
    }

    .field {
        margin: 20px 0;
    }

    label {
        display: block;
        font-weight: bold;
        margin-bottom: 8px;
        color: #333;
    }

    input[type="text"],
    input[type="password"],
    select {
        width: calc(100% - 20px);
        padding: 10px;
        margin-bottom: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 1rem;
    }

    input[type="text"]:focus,
    input[type="password"]:focus,
    select:focus {
        border-color: #4CAF50;
        outline: none;
        box-shadow: 0 0 5px rgba(76, 175, 80, 0.5);
    }

    .field-block {
        border: 1px solid #e1e1e1;
        border-radius: 8px;
        background-color: #ffffff;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .employee-panel {
        border: 1px solid #ccc;
        border-radius: 8px;
        padding: 20px;
        background-color: #f9f9f9;
    }

    .employee-selector {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #ddd;
        padding: 15px;
        border-radius: 4px;
        background-color: #fff;
    }

    .adm-btn-save {
        padding: 12px 20px;
        background-color: #4CAF50;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        font-size: 1rem;
        transition: background-color 0.3s;
    }

    .adm-btn-save:hover {
        background-color: #45a049;
    }

    .invite-btn {
        background-color: #2196F3;
        margin-top: 20px;
    }

    .invite-btn:hover {
        background-color: #1976D2;
    }
</style>

<!-- Оформление формы -->
<form method="POST">
    <?= bitrix_sessid_post(); ?>
    <div id="settings">
        <h2>Настройки модуля</h2>
        <!-- Блок настроек с кнопкой "Сохранить" -->
        <div class="field-block">
            <div class="field">
                <label for="telegram_bot_token">Telegram Бот Токен:</label>
                <input type="password" name="telegram_bot_token" id="telegram_bot_token" value="<?= htmlspecialchars((string)$telegramBotToken) ?>" size="80">
            </div>
            <div class="field">
                <label for="telegram_bot_token">Сообщение для приглашения:</label>
                <input type="text" name="notify_message" id="notify_message" placeholder="Перейдите по ссылке в наш бот!" value="<?= htmlspecialchars((string)$notifyMessage) ?>" size="80">
            </div>

            <div class="field">
                <label for="deal_stage_filter">Стадия для фильтрации сделок (сегодня):</label>
                <select name="deal_stage_filter" id="deal_stage_filter">
                    <?php foreach ($stagesList as $stageCode => $stageName): ?>
                        <option value="<?= $stageCode ?>" <?= $selectedStage === $stageCode ? 'selected' : '' ?>>
                            <?= $stageName ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="deal_stage_filter_tomorrow">Стадия для фильтрации сделок (завтра):</label>
                <select name="deal_stage_filter_tomorrow" id="deal_stage_filter_tomorrow">
                    <?php foreach ($stagesList as $stageCode => $stageName): ?>
                        <option value="<?= $stageCode ?>" <?= $selectedStageTomorrow === $stageCode ? 'selected' : '' ?>>
                            <?= $stageName ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="comment_field">Поле комментария (для отправки файла):</label>
                <select name="comment_field" id="comment_field">
                    <?php foreach ($fieldOptions as $fieldKey => $fieldTitle): ?>
                        <option value="<?= $fieldKey ?>" <?= $selectedCommentField === $fieldKey ? 'selected' : '' ?>>
                            <?= $fieldTitle ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="longitude_field">Поле для долготы:</label>
                <select name="longitude_field" id="longitude_field">
                    <?php foreach ($fieldOptions as $fieldKey => $fieldTitle): ?>
                        <option value="<?= $fieldKey ?>" <?= $selectedLongitudeField === $fieldKey ? 'selected' : '' ?>>
                            <?= $fieldTitle ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="latitude_field">Поле для широты:</label>
                <select name="latitude_field" id="latitude_field">
                    <?php foreach ($fieldOptions as $fieldKey => $fieldTitle): ?>
                        <option value="<?= $fieldKey ?>" <?= $selectedLatitudeField === $fieldKey ? 'selected' : '' ?>>
                            <?= $fieldTitle ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="notify_by_auth">Отправлять уведомление при авторизации:</label>
                <input type="checkbox" name="notify_by_auth" id="notify_by_auth" <?= $notifyByAuth ? 'checked' : ''; ?>>
            </div>

            <!-- Кнопка "Сохранить" -->
            <input type="submit" name="save" value="Сохранить" class="adm-btn-save">
        </div>

        <!-- Блок с кнопкой "Пригласить" -->
        <div class="field-block">
            <input type="hidden" name="action" value="invite">
            <input type="submit" name="invite" value="Пригласить" class="adm-btn-save invite-btn">

            <label style="padding-top: 2rem" for="employee_selector">Выберите сотрудников для приглашения:</label>
            <div class="employee-selector">
                <?php
                // Используем компонент для выбора пользователей
                $APPLICATION->IncludeComponent(
                    "bitrix:intranet.user.selector",
                    "",
                    [
                        "MULTIPLE" => "Y",
                        "NAME" => "EMPLOYEES",
                        "INPUT_NAME" => "EMPLOYEES",
                        "SHOW_EXTRANET_USERS" => "NONE",
                        "INPUT_NAME_STRING" => "employee_selector",
                    ],
                    false,
                    ["HIDE_ICONS" => "Y"]
                );
                ?>
            </div>
        </div>
    </div>
</form>
