<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== TRUE) die();

use \Bitrix\Main\Localization\Loc;
use Nebo\Map\Install\Events;
use Nebo\Map\Install\Files;

Loc::loadMessages(__FILE__);
/**
 *
 */
class nebo_map extends CModule
{
    public $MODULE_ID = "nebo.map";
    public $MODULE_SORT = -1;
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;

    public function __construct()
    {
        $arModuleVersion = [];

        include __DIR__ . '/version.php';

        if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }

        $this->MODULE_NAME = Loc::getMessage('ITNEBO_MAP_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ITNEBO_MAP_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('ITNEBO_MAP_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('ITNEBO_MAP_PARTNER_URI');
    }

    public function DoInstall()
    {
        RegisterModule($this->MODULE_ID);
        
        \CModule::includeModule($this->MODULE_ID);

        \Bitrix\Main\UrlRewriter::add(SITE_ID, array(
            'CONDITION' => '#^/crm/deal/map/#',
            'RULE' => '',
            'ID' => '',
            'PATH' => '/crm/deal/map/index.php',
            "SORT" => 75,
        ));

        Events::install();
        Files::install();
        // $this->InstallEvents();
        // $this->InstallDB();
        return true;
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        \CModule::includeModule($this->MODULE_ID);

        Events::uninstall();
        Files::uninstall();

        \Bitrix\Main\UrlRewriter::delete(SITE_ID, [
            'CONDITION' => '#^/crm/deal/map/#',
            'PATH' => '/crm/deal/map/index.php',
        ]);

        // $this->UnInstallEvents();
        // $this->UnInstallDB();

        UnRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile(Loc::getMessage("ITNEBO_MAP_UNINSTALL_TITLE"),  $this->getPath() . "/install/unstep.php");

        return true;
    }

    /**
     * функция возвращает текущий PATH для инсталлятора
     * @param bool $notDocumentRoot
     * @return mixed|string
     */
    protected function GetPath($notDocumentRoot=false)
    {
        if($notDocumentRoot)
            return str_ireplace(\Bitrix\Main\Application::getDocumentRoot(),'',dirname(__DIR__));
        else
            return dirname(__DIR__);
    }

    // Таблицы
    public function InstallDB() {
        return true;
    }
    public function UnInstallDB() {
        return true;
    }

    // События
    public function InstallEvents()
    {
        return true;
    }
    public function UnInstallEvents()
    {
        return true;
    }

}