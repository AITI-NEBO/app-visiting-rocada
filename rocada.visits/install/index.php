<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

Loc::loadMessages(__FILE__);

class rocada_visits extends CModule
{
    public $MODULE_ID          = 'rocada.visits';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME       = 'IT Небо';
    public $PARTNER_URI        = 'https://itnebo.ru';

    public function __construct()
    {
        $this->MODULE_NAME        = Loc::getMessage('ROCADA_PWA_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ROCADA_PWA_MODULE_DESCRIPTION');
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    // ── Установка ─────────────────────────────────────────────────────────────
    public function DoInstall()
    {
        global $APPLICATION;

        try {
            Loader::includeModule('main');
            Loader::includeModule('crm');
            Loader::includeModule('highloadblock');

            // Регистрируем модуль
            RegisterModule($this->MODULE_ID);

            // ── HighloadBlock: UserPwaSession ─────────────────────────────────
            // Хранит UUID-сессии для связи пользователя Б24 с PWA-сессией
            $hlblockExists = HighloadBlockTable::getList([
                'filter' => ['=NAME' => 'UserPwaSession'],
            ])->fetch();

            if (!$hlblockExists) {
                $result = HighloadBlockTable::add([
                    'NAME'       => 'UserPwaSession',
                    'TABLE_NAME' => 'b_user_pwa_session',
                ]);

                if ($result->isSuccess()) {
                    $hlblockId = $result->getId();
                    $entity    = 'HLBLOCK_' . $hlblockId;

                    // UF_USER — привязка к пользователю Б24
                    $this->addUserField($entity, 'UF_USER', 'Пользователь Б24', 'integer');
                    // UF_TOKEN — JWT-токен (для будущего blacklist/refresh)
                    $this->addUserField($entity, 'UF_TOKEN', 'Последний токен', 'string');
                    // UF_LAST_SEEN — последняя активность
                    $this->addUserField($entity, 'UF_LAST_SEEN', 'Последняя активность', 'datetime');
                    // UF_DEVICE — User-Agent / устройство
                    $this->addUserField($entity, 'UF_DEVICE', 'Устройство', 'string');
                } else {
                    throw new \Exception(implode(', ', $result->getErrorMessages()));
                }
            }

            // ── UF-поле на сотруднике: признак доступа к PWA ─────────────────
            // Администратор может включать/выключать доступ прямо из карточки сотрудника
            $this->addUserField('USER', 'UF_ROCADA_PWA_ACCESS', 'Доступ к RocadaMed PWA', 'boolean');

            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('ROCADA_PWA_INSTALL_TITLE'),
                __DIR__ . '/step1.php'
            );
        } catch (\Exception $e) {
            $APPLICATION->ThrowException($e->getMessage());
        }
    }

    // ── Деинсталляция ─────────────────────────────────────────────────────────
    public function DoUninstall()
    {
        global $APPLICATION;

        try {
            // Удаляем HL-блок UserPwaSession
            $hlblock = HighloadBlockTable::getList([
                'filter' => ['=NAME' => 'UserPwaSession'],
            ])->fetch();

            if ($hlblock) {
                HighloadBlockTable::delete($hlblock['ID']);
            }

            UnRegisterModule($this->MODULE_ID);

            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('ROCADA_PWA_UNINSTALL_TITLE'),
                __DIR__ . '/unstep1.php'
            );
        } catch (\Exception $e) {
            $APPLICATION->ThrowException($e->getMessage());
        }
    }

    // ── Вспомогательный метод добавления UF-поля ─────────────────────────────
    private function addUserField(
        string $entityId,
        string $fieldName,
        string $fieldTitle,
        string $fieldType,
        array  $extra = []
    ): void {
        $ute = new \CUserTypeEntity();

        // Не дублируем если поле уже существует
        $exists = $ute->GetList([], [
            'ENTITY_ID'  => $entityId,
            'FIELD_NAME' => $fieldName,
        ])->Fetch();

        if ($exists) {
            return;
        }

        $data = array_merge([
            'ENTITY_ID'         => $entityId,
            'FIELD_NAME'        => $fieldName,
            'USER_TYPE_ID'      => $fieldType,
            'XML_ID'            => $fieldName,
            'SORT'              => 100,
            'MULTIPLE'          => 'N',
            'MANDATORY'         => 'N',
            'SHOW_FILTER'       => 'N',
            'SHOW_IN_LIST'      => 'Y',
            'EDIT_IN_LIST'      => 'Y',
            'IS_SEARCHABLE'     => 'N',
            'SETTINGS'          => [],
            'EDIT_FORM_LABEL'   => ['ru' => $fieldTitle, 'en' => $fieldTitle],
            'LIST_COLUMN_LABEL' => ['ru' => $fieldTitle, 'en' => $fieldTitle],
            'LIST_FILTER_LABEL' => ['ru' => $fieldTitle, 'en' => $fieldTitle],
        ], $extra);

        $ute->Add($data);
    }
}
