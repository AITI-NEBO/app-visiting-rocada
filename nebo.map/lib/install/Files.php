<?php
namespace Nebo\Map\Install;

use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;

class Files
{
    const MODULE_ID  = 'nebo.doc';

    /**
     * Копирование файлов при установке модуля
     */
    public static function install()
    {
        CopyDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . "/local/modules/nebo.map/install/map/",
            $_SERVER["DOCUMENT_ROOT"].'/crm/deal/map/',
            true,
            true);

        CopyDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . "/local/modules/nebo.map/install/routes/",
            $_SERVER["DOCUMENT_ROOT"].'/bitrix/routes/',
            true,
            true);

        return true;
    }

    /**
     * Удаление файлов при удалении модуля
     */
    public static function uninstall()
    {
        Directory::deleteDirectory($_SERVER["DOCUMENT_ROOT"]."/crm/deal/map/");
        File::deleteFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/routes/itnebo_map.php");

        return true;
    }
}