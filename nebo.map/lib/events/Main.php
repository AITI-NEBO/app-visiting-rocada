<?
namespace Nebo\Map\Events;

use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\EventResult;

Loc::loadMessages(__FILE__);

class Main
{

    /**
     * Регистрация расширения стилей документов
     * @return void
     */
    public static function OnProlog()
    {
        \CJSCore::RegisterExt(
            "nebo.map.integration",
            [
                "js" => [
                    "/local/modules/nebo.map/install/assets/js/integration.js",
                ],
                "skip_core" => true
            ]
        );
    }

    /**
     * Подключение события
     * @return void
     */
    public static function onEpilog()
    {
        global $APPLICATION;
        if(strpos($APPLICATION->GetCurPage(), 'crm/deal'))
        	\CJSCore::Init(['nebo.map.integration']);
    }

}?>
