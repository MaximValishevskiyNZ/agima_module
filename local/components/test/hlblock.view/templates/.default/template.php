<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die(); ?>

<table border="1" cellpadding="5">
    <tr>
        <th>Дата/время</th>
        <th>ID пользователя</th>
        <th>Имя пользователя</th>
        <th>ID элемента</th>
        <th>Текущее наименование элемента</th>
        <th>Установленное имя</th>
        <th>Установленный анонс</th>
    </tr>
    <?php foreach ($arResult['ROWS'] as $row): ?>
        <tr>
            <td><?= htmlspecialcharsbx($row['UF_DATE']) ?></td>
            <td><?= intval($row['UF_USER_ID']) ?></td>
            <td><?= htmlspecialcharsbx(trim($row['USER_NAME'].' '.$row['USER_LAST_NAME'].' ['.$row['USER_LOGIN'].']')) ?></td>
            <td><?= intval($row['UF_ELEMENT_ID']) ?></td>
            <td><?= htmlspecialcharsbx($row['IBLOCK_ELEMENT_NAME']) ?></td>
            <td><?= htmlspecialcharsbx($row['UF_NAME']) ?></td>
            <td><?= htmlspecialcharsbx($row['UF_PREVIEW_TEXT']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<?
$APPLICATION->IncludeComponent(
    "bitrix:main.pagenavigation",
    "",
    array(
        // передаем объект 
        "NAV_OBJECT" => $arResult['NAV'],
        // включение/отключение ЧПУ или GET
        "SEF_MODE" => "N",
    ),
    false
);
?>