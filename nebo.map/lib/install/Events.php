<?
namespace Nebo\Map\Install;

use Bitrix\Main\EventManager;

class Events
{
    const MODULE_ID = 'nebo.map';

    /**
     * Массив обработчив, которые надо создать
     * @return \string[][]
     */
    private static function getHandlers()
    {
        return [
            [
                // Регистарция CSS
                'fromModuleId' => 'main',
                'eventType'    => 'OnProlog',
                'toClass'      => 'Nebo\Map\Events\Main',
                'toMethod'     => 'OnProlog',
                'sort'         => 99999
            ],
            [
                // Регистарция CSS
                'fromModuleId' => 'main',
                'eventType'    => 'onEpilog',
                'toClass'      => 'Nebo\Map\Events\Main',
                'toMethod'     => 'onEpilog',
                'sort'         => 99999
            ]
        ];
    }

    /**
     * Регистрируем свои обработчики
     */
    public static function install()
    {
        $eManager = EventManager::getInstance();

        foreach (self::getHandlers() as $handler) {
            $eManager->registerEventHandler($handler['fromModuleId'], $handler['eventType'], self::MODULE_ID, $handler['toClass'], $handler['toMethod'], $handler['sort']);
        }
    }

    /**
     * Удаляем свои обработчики
     */
    public static function uninstall()
    {
        $eManager = EventManager::getInstance();

        foreach (self::getHandlers() as $handler) {
            $eManager->unRegisterEventHandler($handler['fromModuleId'], $handler['eventType'], self::MODULE_ID, $handler['toClass'], $handler['toMethod']);
        }
    }
}