<?php
use Bitrix\Main\Localization\Loc;

if (!check_bitrix_sessid()) {
    return;
}

Loc::loadMessages(__FILE__);

if ($errorException = $APPLICATION->getException()) {
    print_r($errorException);
    // ошибка при установке модуля
    CAdminMessage::showMessage(
        Loc::getMessage('ITNEBO_MAP_INSTALL_FAILED')
    );
} else {
    // модуль успешно установлен
    CAdminMessage::showNote(
        Loc::getMessage('ITNEBO_MAP_INSTALL_SUCCESS')
    );
}
?>

<form action="<?= $APPLICATION->getCurPage(); ?>"> <!-- Кнопка возврата к списку модулей -->
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID; ?>" />
    <input type="submit" value="<?= Loc::getMessage('ITNEBO_MAP_RETURN_MODULES'); ?>">
</form>