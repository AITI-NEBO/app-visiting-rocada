<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
    die();

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

Loader::includeModule('highloadblock');
Loader::includeModule('im');

class CBPRocadaTechRemoveTelegram extends CBPActivity
{
    /**
     * Инициализирует действие.
     * @param $name
     */
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = array(
            "USER_INVITE" => "",
        );
    }

    public function Execute()
    {
        // Проверка обязательных полей
        if (empty($this->USER_INVITE)) {
            $this->WriteToTrackingService(
                'Ошибка: Не заполнены обязательные поля (Кого удалить)',
                0,
                CBPTrackingType::Error
            );
            return CBPActivityExecutionStatus::Closed;
        }

        // Получаем ID пользователя, которого приглашаем
        $errors = [];
        $userInviteIdArr = CBPHelper::ExtractUsers(
            $this->USER_INVITE,
            $this->getDocumentId(),
            true
        ) ?? CBPHelper::ExtractUsers(
                    CBPHelper::UsersStringToArray($this->USER_INVITE, $this->getDocumentId(), $errors),
                    $this->getDocumentId(),
                    true
                );
        $this->WriteToTrackingService('Удаляемый: ' . json_encode([$this->USER_INVITE, $userInviteIdArr], JSON_UNESCAPED_UNICODE), 0, CBPTrackingType::Error);
        $userInviteId = is_array($userInviteIdArr) ? reset($userInviteIdArr) : $userInviteIdArr;
        if (empty($userInviteId)) {
            $this->WriteToTrackingService(
                'Ошибка: Не найден удаляемый пользователь - ' . json_encode($this->USER_INVITE, JSON_UNESCAPED_UNICODE),
                0,
                CBPTrackingType::Error
            );
            return CBPActivityExecutionStatus::Closed;
        }

        // Получаем данные пользователя и руководителя (РМ подразделения)
        $user = \Bitrix\Main\UserTable::getById($userInviteId)->fetch();
        if (!$user) {
            $this->WriteToTrackingService('Ошибка: Не удалось получить данные пользователя.', 0, CBPTrackingType::Error);
            return CBPActivityExecutionStatus::Closed;
        }
        // ФИО сотрудника
        $fio = trim($user['LAST_NAME'] . ' ' . $user['NAME'] . ' ' . $user['SECOND_NAME']);

        $bpU = new \Bitrix\Bizproc\Service\User;
        $createdBy = $bpU->getUserHeads($user['ID'])[0] ?? $user['ID'];

        // Highload блок — удаляем старую запись и добавляем новую
        $hlblock = HighloadBlockTable::getList(['filter' => ['=NAME' => 'UserStageInfo']])->fetch();
        if ($hlblock) {
            $entity = HighloadBlockTable::compileEntity($hlblock);
            $entityClass = $entity->getDataClass();
            $existingRecord = $entityClass::getList([
                'filter' => ['UF_USER' => $userInviteId],
                'select' => ['ID']
            ]);
            while ($record = $existingRecord->fetch()) {
                // Удаляем существующую запись
                $entityClass::delete($record['ID']);
            }
        }

        return CBPActivityExecutionStatus::Closed;
    }

    /**
     * @param $documentType
     * @param $activityName
     * @param $arWorkflowTemplate
     * @param $arWorkflowParameters
     * @param $arWorkflowVariables
     * @param null $arCurrentValues
     * @param string $formName
     * @return false|string|void|null
     * Статический метод возвращает HTML-код диалога настройки
     * свойств действия в визуальном редакторе.
     */
    public static function GetPropertiesDialog(
        $documentType,
        $activityName,
        $arWorkflowTemplate,
        $arWorkflowParameters,
        $arWorkflowVariables,
        $arCurrentValues = null,
        string $formName = ""
    ) {

        if (!is_array($arWorkflowParameters))
            $arWorkflowParameters = array();
        if (!is_array($arWorkflowVariables))
            $arWorkflowVariables = array();

        if (!is_array($arCurrentValues)) {
            $arCurrentValues = array(
                "USER_INVITE" => "",
                "RESPONSIBLE" => "",
                "GROUP" => "",
            );

            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);

            if (is_array($arCurrentActivity["Properties"])) {
                $arCurrentValues["USER_INVITE"] = $arCurrentActivity["Properties"]["USER_INVITE"] ?? "";
                $arCurrentValues["RESPONSIBLE"] = $arCurrentActivity["Properties"]["RESPONSIBLE"] ?? "";
                $arCurrentValues["GROUP"] = $arCurrentActivity["Properties"]["GROUP"] ?? "";
            }
        }


        $runtime = CBPRuntime::GetRuntime();
        return $runtime->ExecuteResourceFile(
            __FILE__,
            "properties_dialog.php",
            array(
                "arCurrentValues" => $arCurrentValues,
                "formName" => $formName
            )
        );

    }

    /**
     * @param $documentType
     * @param $activityName
     * @param $arWorkflowTemplate
     * @param $arWorkflowParameters
     * @param $arWorkflowVariables
     * @param $arCurrentValues
     * @param $arErrors
     * @return bool
     * Статический метод получает введенные в диалоге настройки свойств
     * значения и сохраняет их в шаблоне бизнес-процесса.
     */
    public static function GetPropertiesDialogValues(
        $documentType,
        $activityName,
        &$arWorkflowTemplate,
        &$arWorkflowParameters,
        &$arWorkflowVariables,
        $arCurrentValues,
        &$arErrors
    ): bool {
        $properties = [
            "USER_INVITE" => $arCurrentValues["USER_INVITE"],
            "RESPONSIBLE" => $arCurrentValues["RESPONSIBLE"],
            "GROUP" => $arCurrentValues["GROUP"],
        ];

        $currentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $currentActivity['Properties'] = $properties;


        return true;
    }


}