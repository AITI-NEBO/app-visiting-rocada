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
//        try {
        Loader::includeModule('main');
        Loader::includeModule('highloadblock');

        // Регистрируем модуль
        RegisterModule($this->MODULE_ID);

        $hlblockExists = HighloadBlockTable::getList([
            'filter' => ['=NAME' => 'UserStageInfo']
        ])->fetch();
        print_r($hlblockExists);

        if (!$hlblockExists) {
            $result = HighloadBlockTable::add([
                'NAME' => 'UserStageInfo',
                'TABLE_NAME' => 'b_user_stage_info',
            ]);
        }

        if ($result) {
            if ($result->isSuccess()) {
                $hlblockId = $result->getId();

                // Добавляем поля в Highload-блок
                $userFieldEntity = 'HLBLOCK_' . $hlblockId;
                $this->addUserField($userFieldEntity, 'UF_USER', 'Привязка к пользователю', 'integer', [
                    'USER_TYPE_ID' => 'employee', // Устанавливаем тип привязки к пользователю
                ]);
                $this->addUserField($userFieldEntity, 'UF_STAGE', 'Стадия', 'string');
                $this->addUserField($userFieldEntity, 'UF_INFO', 'Доп информация', 'string');
            } else {
                throw new Exception(implode(', ', $result->getErrorMessages()));
            }
        }

        $this->addUserField('USER', 'UF_ROCADOMED_TELEGEAM_ID', 'ID Telegram Rocadomed', 'string');

//        } catch (Exception $e) {
//            $APPLICATION->ThrowException($e->getMessage());
//        }
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
