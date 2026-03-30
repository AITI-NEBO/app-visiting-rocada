<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Entity;

class rocada_telegram extends CModule
{
    public $MODULE_ID = 'rocada.telegram';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;

    function __construct()
    {
        $this->MODULE_NAME = Loc::getMessage('ROCADA_TELEGRAM_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ROCADA_TELEGRAM_MODULE_DESCRIPTION');
        include(__DIR__ . '/version.php');
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    public function DoInstall()
    {
        global $APPLICATION;

        Loader::includeModule('main');
        Loader::includeModule('highloadblock');

        RegisterModule($this->MODULE_ID);

        // Создание HL-блока
        $hlblockExists = \Bitrix\Highloadblock\HighloadBlockTable::getList([
            'filter' => ['=NAME' => 'UserStageInfo']
        ])->fetch();

        if (!$hlblockExists) {
            $result = \Bitrix\Highloadblock\HighloadBlockTable::add([
                'NAME' => 'UserStageInfo',
                'TABLE_NAME' => 'b_user_stage_info',
            ]);

            if ($result && $result->isSuccess()) {
                $hlblockId = $result->getId();
                $userFieldEntity = 'HLBLOCK_' . $hlblockId;
                $this->addUserField($userFieldEntity, 'UF_USER', 'Привязка к пользователю', 'integer', [
                    'USER_TYPE_ID' => 'employee',
                ]);
                $this->addUserField($userFieldEntity, 'UF_STAGE', 'Стадия', 'string');
                $this->addUserField($userFieldEntity, 'UF_INFO', 'Доп информация', 'string');
            } elseif ($result) {
                throw new \Exception(implode(', ', $result->getErrorMessages()));
            }
        }

        $this->addUserField('USER', 'UF_ROCADOMED_TELEGEAM_ID', 'ID Telegram Rocadomed', 'string');

        // Копирование активити
        $sourceActivitiesPath = __DIR__ . '/../activities/';
        $targetActivitiesPath = $_SERVER['DOCUMENT_ROOT'] . '/local/activities/';

        if (is_dir($sourceActivitiesPath)) {
            $activityDirs = scandir($sourceActivitiesPath);
            foreach ($activityDirs as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }

                $src = $sourceActivitiesPath . $dir;
                $dst = $targetActivitiesPath . $dir;

                if (is_dir($src)) {
                    // Создать папку, если нет
                    if (!is_dir($dst) && !mkdir($dst, 0775, true)) {
                        AddMessage2Log("Ошибка создания папки активности: $dst", "DoInstall");
                        continue;
                    }

                    // Копируем только файлы и подпапки из нашей активности
                    $objects = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );

                    foreach ($objects as $object) {
                        $targetPath = $dst . DIRECTORY_SEPARATOR . $objects->getSubPathName();
                        try {
                            if ($object->isDir()) {
                                if (!is_dir($targetPath) && !mkdir($targetPath, 0775, true)) {
                                    AddMessage2Log("Ошибка создания подпапки: $targetPath", "DoInstall");
                                }
                            } else {
                                if (!copy($object, $targetPath)) {
                                    AddMessage2Log("Ошибка копирования файла: $object -> $targetPath", "DoInstall");
                                }
                            }
                        } catch (\Throwable $e) {
                            AddMessage2Log("Исключение при копировании {$object}: " . $e->getMessage(), "DoInstall");
                        }
                    }
                } else {
                    AddMessage2Log("Пропущено: $src не является директорией", "DoInstall");
                }
            }
        } else {
            AddMessage2Log("Путь к активностям не найден: $sourceActivitiesPath", "DoInstall");
        }

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('ROCADA_TELEGRAM_INSTALL_TITLE'),
            __DIR__ . '/step.php'
        );
    }

    public function DoUninstall()
    {
        global $APPLICATION;
        try {
            UnRegisterModule($this->MODULE_ID);
        } catch (Exception $e) {
            $APPLICATION->ThrowException($e->getMessage());
        }
    }

    private function addUserField($entityId, $fieldName, $fieldTitle, $fieldType, $additionalSettings = [])
    {
        $userTypeEntity = new \CUserTypeEntity();
        $userFieldData = [
            'ENTITY_ID' => $entityId,
            'FIELD_NAME' => $fieldName,
            'USER_TYPE_ID' => $fieldType,
            'XML_ID' => $fieldName,
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'N',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'SETTINGS' => [],
            'EDIT_FORM_LABEL' => ['ru' => $fieldTitle, 'en' => $fieldTitle],
            'LIST_COLUMN_LABEL' => ['ru' => $fieldTitle, 'en' => $fieldTitle],
            'LIST_FILTER_LABEL' => ['ru' => $fieldTitle, 'en' => $fieldTitle],
        ];

        // Добавляем дополнительные настройки, если они переданы
        if (!empty($additionalSettings)) {
            $userFieldData = array_merge($userFieldData, $additionalSettings);
        }

        $userTypeEntity->Add($userFieldData);
    }
}
