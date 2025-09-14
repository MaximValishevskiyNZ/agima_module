<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Поиск");
?>

<?$APPLICATION->IncludeComponent(
    "test:hlblock.view",
    "",
    [
        "PAGE_SIZE" => 10
    ]
);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>