<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
?>

<tr>
    <td width="40%">Кого удалить:</td>
    <td width="60%">
        <?=CBPDocument::ShowParameterField("user", "USER_INVITE", $arCurrentValues["USER_INVITE"], array('rows' => 1))?>
    </td>
</tr>
