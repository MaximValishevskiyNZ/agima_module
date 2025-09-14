<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

$request = HttpApplication::getInstance()->getContext()->getRequest();
$module_id = htmlspecialchars($request['mid'] != '' ? $request['mid'] : $request['id']);

if (!Loader::includeModule($module_id)) {
    return;
}


$iblockList = [];
if (Loader::includeModule('iblock')) {
    $res = CIBlock::GetList(
        ['SORT' => 'ASC'],
        ['ACTIVE' => 'Y']
    );
    while ($iblock = $res->Fetch()) {
        $iblockList[$iblock['ID']] = '[' . $iblock['ID'] . '] ' . $iblock['NAME'];
    }
}


if (empty($iblockList)) {
    $iblockList[''] = Loc::getMessage('MODULE_OPTIONS_NO_IBLOCKS');
}

$APPLICATION->SetTitle(Loc::getMessage('MODULE_OPTIONS_TITLE'));

$aTabs = [
    [
        'DIV'     => 'edit1',
        'TAB'     => Loc::getMessage('MODULE_OPTIONS_TAB_MAIN'),
        'TITLE'   => Loc::getMessage('MODULE_OPTIONS_TAB_MAIN_TITLE'),
        'OPTIONS' => [

            [
                'company_name',
                Loc::getMessage('MODULE_OPTIONS_COMPANY_NAME'),
                '',
                ['text', 50]
            ],

            [
                'company_type',
                Loc::getMessage('MODULE_OPTIONS_COMPANY_TYPE'),
                'ООО',
                [
                    'selectbox',
                    [
                        'ОАО' => 'ОАО',
                        'ЗАО' => 'ЗАО',
                        'ООО' => 'ООО',
                        'ИП'  => 'ИП'
                    ]
                ]
            ],

            [
                'uses_usn',
                Loc::getMessage('MODULE_OPTIONS_USES_USN'),
                'Y',
                ['checkbox']
            ],
        ]
    ],
    [
        'DIV'     => 'edit2',
        'TAB'     => Loc::getMessage('MODULE_OPTIONS_TAB_INTEGRATION'),
        'TITLE'   => Loc::getMessage('MODULE_OPTIONS_TAB_INTEGRATION_TITLE'),
        'OPTIONS' => [
            [
                'selected_iblock',
                Loc::getMessage('MODULE_OPTIONS_SELECTED_IBLOCK'),
                '',
                [
                    'selectbox',
                    $iblockList
                ]
            ],


            [
                'email_list',
                Loc::getMessage('MODULE_OPTIONS_EMAIL_LIST'),
                '',
                ['text', 60]
            ],
        ]
    ]
];


$tabControl = new CAdminTabControl('tabControl', $aTabs);


if ($request->isPost() && check_bitrix_sessid()) {
    foreach ($aTabs as $tab) {
        foreach ($tab['OPTIONS'] as $option) {
            if (!is_array($option)) continue;
            $name = $option[0];
            $postValue = $request->getPost($name);

            if ($request['apply'] || $request['save']) {

                if ($option[3][0] === 'checkbox') {
                    $value = ($postValue == 'Y') ? 'Y' : 'N';
                } else {
                    $value = is_array($postValue) ? implode(',', $postValue) : $postValue;
                }
                Option::set($module_id, $name, $value);
            } elseif ($request['default']) {

                $defaultValue = $option[2];
                Option::set($module_id, $name, $defaultValue);
            }
        }
    }


    LocalRedirect($APPLICATION->GetCurPage() . "?mid=" . $module_id . "&lang=" . LANGUAGE_ID . "&back_url_admin=" . urlencode($_REQUEST['back_url_admin']));
}
?>


<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= $module_id ?>&lang=<?= LANGUAGE_ID ?>" name="module_settings_form">
    <?= bitrix_sessid_post(); ?>

    <?php $tabControl->Begin(); ?>
    <?php foreach ($aTabs as $tab): ?>
        <?php $tabControl->BeginNextTab(); ?>
        <?php __AdmSettingsDrawList($module_id, $tab['OPTIONS']); ?>
    <?php endforeach; ?>

    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="save" value="<?= Loc::getMessage('MAIN_SAVE') ?>" class="adm-btn-save">
    <input type="submit" name="apply" value="<?= Loc::getMessage('MAIN_APPLY') ?>">
    <input type="button" name="cancel" value="<?= Loc::getMessage('MAIN_CANCEL') ?>"
        onclick="window.location='<?= $_REQUEST['back_url_admin'] ?: '/bitrix/admin/settings.php?lang=' . LANGUAGE_ID ?>'">

    <input type="submit" name="default" value="<?= Loc::getMessage('MAIN_DEFAULT') ?>"
        title="<?= Loc::getMessage('MAIN_HINT_DEFAULT') ?>">
    <input type="hidden" name="back_url_admin" value="<?= htmlspecialcharsbx($_REQUEST['back_url_admin']) ?>">

    <?php $tabControl->End(); ?>
</form>