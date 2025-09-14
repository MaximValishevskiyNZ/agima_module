<?php

use Bitrix\Main\Loader;

if (!check_bitrix_sessid()) return;

Loader::includeModule('iblock');

$iblockList = [];
$res = CIBlock::GetList(['SORT' => 'ASC'], ['ACTIVE' => 'Y']);
while ($iblock = $res->Fetch()) {
    $iblockList[$iblock['ID']] = '[' . $iblock['ID'] . '] ' . $iblock['NAME'];
}
?>
<form action="" method="post">
    <?=bitrix_sessid_post()?>
    <h3>Выберите инфоблок для работы модуля:</h3>
    <select name="selected_iblock" required>
        <option value="">-- выберите инфоблок --</option>
        <?php foreach ($iblockList as $id => $name): ?>
            <option value="<?=$id?>" <?=($_REQUEST['selected_iblock'] == $id ? 'selected' : '')?>><?=$name?></option>
        <?php endforeach; ?>
    </select>
    <br><br>
    <input type="hidden" name="step" value="2">
    <input type="submit" value="Далее" class="adm-btn-save">
</form>