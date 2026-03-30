<?

use Bitrix\Main\Localization\Loc;

CModule::IncludeModule("highloadblock");


// Основные константы
define('ROCADA_TELEGRAM_MODULE_ID', basename(__DIR__));

// Данные о версии модуля
require __DIR__ . '/install/version.php';

foreach ($arModuleVersion as $key => $value) {
    define('ROCADA_TELEGRAM_' . $key, $value);
}


CModule::AddAutoloadClasses(
    "rocada.telegram",
    array(
        "\\Rocada\\Telegram\\TelegramBot" => "classes/bot.php",
    )
);
