<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Bizproc\FieldType;
use Bitrix\Main\Localization\Loc;

$arActivityDescription = array(
    "NAME" => Loc::getMessage("ROCADA_INVITE_BP_NAME"),
    "DESCRIPTION" => Loc::getMessage("ROCADA_INVITE_BP_DESCR"),
    "TYPE" => "activity",
    "CLASS" => "RocadaTechInviteTelegram",
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => array(
        "ID" => "other",
    ),
	"RETURN" => array(
		"URL" => array(
			"NAME" => "URL",
			"TYPE" => FieldType::STRING
		),
	),
);
