<?php
/**
 * include.php — Подключение модуля rocada.visits
 * Загружается Bitrix при каждом Loader::includeModule('rocada.visits')
 */

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

// Основные константы
define('ROCADA_PWA_MODULE_ID', 'rocada.visits');

// Версия
require __DIR__ . '/install/version.php';
foreach ($arModuleVersion as $key => $value) {
    define('ROCADA_PWA_' . $key, $value);
}

// Автозагрузка классов модуля
\Bitrix\Main\Loader::registerAutoLoadClasses(
    'rocada.visits',
    [
        '\\Rocada\\Pwa\\Api\\Router'    => 'lib/api/Router.php',
        '\\Rocada\\Pwa\\Helper\\Jwt'    => 'lib/helpers/Jwt.php',
        '\\Rocada\\Pwa\\Helper\\CrmUtil'=> 'lib/helpers/CrmUtil.php',
    ]
);
