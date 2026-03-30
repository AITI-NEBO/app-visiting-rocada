<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Localization\Loc;

$arActivityDescription = array(
    "NAME" => Loc::getMessage("ROCADA_REMOVE_BP_NAME"),
    "DESCRIPTION" => Loc::getMessage("ROCADA_REMOVE_BP_DESCR"),
    "TYPE" => "activity",
    "CLASS" => "RocadaTechRemoveTelegram",
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => array(
        "ID" => "other",
    )
);
