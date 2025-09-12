<?php

if (!check_bitrix_sessid()) return;

use Bitrix\Main\Config\Option;

?>
<form action="<?echo $APPLICATION->GetCurPage()?>" method="post">
    <?echo bitrix_sessid_post();?>
    <input type="hidden" name="lang" value="<?echo LANG?>">
    <input type="hidden" name="id" value="test_module">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">
    <h3>Удалить данные модуля (highload-блок)?</h3>
    <label>
        <input type="radio" name="delete_data" value="Y" checked>
        Да, удалить данные
    </label><br>
    <label>
        <input type="radio" name="delete_data" value="N">
        Нет, сохранить данные
    </label>
    <br><br>
    <input type="submit" name="inst" value="Деинсталлировать">
</form>