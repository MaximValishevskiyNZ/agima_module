<?php

namespace Test;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Mail\Event;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Type\DateTime;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\ElementPropertyTable;

class Main
{
    public static $oldData;
    public static function OnBeforeIBlockElementAddHandler(&$arFields)
    {
        try {
            $moduleId = 'test_module';
            $selectedIblock = Option::get($moduleId, 'selected_iblock', '');
            $emailList = Option::get($moduleId, 'email_list', '');

            if (
                !empty($selectedIblock)
                && !empty($emailList)
                && isset($arFields['IBLOCK_ID'])
                && (int)$arFields['IBLOCK_ID'] === (int)$selectedIblock
            ) {
                $emails = array_filter(array_map('trim', explode(',', $emailList)));

                if (!empty($emails)) {
                    Event::send([
                        "EVENT_NAME" => "NEW_IBLOCK_ELEMENT_NOTIFICATION",
                        "LID"        => "s1",
                        "C_FIELDS"   => [
                            "NAME"         => $arFields['NAME'] ?? '',
                            "PREVIEW_TEXT" => $arFields['PREVIEW_TEXT'] ?? '',
                            "EMAIL_TO"     => implode(',', $emails),
                        ],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // логирование по необходимости
        }
    }

    public static function OnBeforeIBlockElementUpdateHandler(&$arFields) {
         self::$oldData = ElementTable::getRowById($arFields['ID']);
    }

    public static function OnAfterIBlockElementAddHandler(&$arFields)
    {
        $moduleId = 'test_module';
        $selectedIblock = Option::get($moduleId, 'selected_iblock', '');

        if (
            !empty($selectedIblock)
            && isset($arFields['IBLOCK_ID'])
            && (int)$arFields['IBLOCK_ID'] === (int)$selectedIblock
        ) {
            self::addHLRecord([
                'UF_DATE'        => new DateTime(),
                'UF_USER_ID'     => $arFields['MODIFIED_BY'] ?? $arFields['CREATED_BY'] ?? 0,
                'UF_ELEMENT_ID'  => $arFields['ID'],
                'UF_NAME'        => $arFields['NAME'] ?? '',
                'UF_PREVIEW_TEXT' => $arFields['PREVIEW_TEXT'] ?? '',
            ]);
        }
    }

    public static function OnAfterIBlockElementUpdateHandler(&$arFields)
    {
        $moduleId = 'test_module';
        $selectedIblock = Option::get($moduleId, 'selected_iblock', '');

        if (
            !empty($selectedIblock)
            && isset($arFields['IBLOCK_ID'])
            && (int)$arFields['IBLOCK_ID'] === (int)$selectedIblock
        ) {
           
            WriteToLog(self::$oldData);
            $nameChanged = (self::$oldData['NAME'] ?? '') !== ($arFields['NAME'] ?? '');
            $previewChanged = (self::$oldData['PREVIEW_TEXT'] ?? '') !== ($arFields['PREVIEW_TEXT'] ?? '');

            // Если ни название, ни анонс не изменились — выходим
            if (!$nameChanged && !$previewChanged) {
                return;
            }
            // Получаем свойства элемента через D7
            $props = [];
            $propRes = ElementPropertyTable::getList([
                'filter' => ['IBLOCK_ELEMENT_ID' => $arFields['ID']],
                'select' => ['*'],
            ]);

            while ($prop = $propRes->fetch()) {
                $props[$prop['IBLOCK_PROPERTY_ID']] = $prop;
            }

            // Получаем коды свойств (PropertyTable)
            $codes = [];
            if (!empty($props)) {
                $propertyRes = PropertyTable::getList([
                    'filter' => ['@ID' => array_keys($props)],
                    'select' => ['ID', 'CODE'],
                ]);
                while ($p = $propertyRes->fetch()) {
                    $codes[$p['ID']] = $p['CODE'];
                }
            }

            // Проверяем запрет учета изменений
            foreach ($codes as $pid => $code) {
                if ($code === 'DISABLE_CHANGE_TRACKING' && !empty($props[$pid]['VALUE'])) {
                    return;
                }
            }

            // Инкрементируем EDIT_COUNT
            $editCount = 1;
            foreach ($codes as $pid => $code) {
                if ($code === 'EDIT_COUNT' && is_numeric($props[$pid]['VALUE'])) {
                    $editCount = (int)$props[$pid]['VALUE'] + 1;
                }
            }

            // Записываем свойство EDIT_COUNT
            \CIBlockElement::SetPropertyValuesEx(
                $arFields['ID'],
                $arFields['IBLOCK_ID'],
                ['EDIT_COUNT' => $editCount]
            );

            // Фиксируем изменение в HL-блоке
            self::addHLRecord([
                'UF_DATE'        => new DateTime(),
                'UF_USER_ID'     => $arFields['MODIFIED_BY'] ?? 0,
                'UF_ELEMENT_ID'  => $arFields['ID'],
                'UF_NAME'        => $arFields['NAME'] ?? '',
                'UF_PREVIEW_TEXT' => $arFields['PREVIEW_TEXT'] ?? '',
            ]);

            // Получаем все изменения из HL-блока
            $hlblock = HL\HighloadBlockTable::getList([
                'filter' => ['=NAME' => 'TestEntity']
            ])->fetch();

            if ($hlblock) {
                $entity = HL\HighloadBlockTable::compileEntity($hlblock);
                $entityDataClass = $entity->getDataClass();
                $rsData = $entityDataClass::getList([
                    'filter' => ['UF_ELEMENT_ID' => $arFields['ID']],
                    'order'  => ['UF_DATE' => 'ASC'],
                    'select' => ['UF_DATE']
                ]);

                $dates = [];
                while ($row = $rsData->fetch()) {
                    if ($row['UF_DATE'] instanceof DateTime) {
                        $dates[] = $row['UF_DATE']->getTimestamp();
                    }
                }

                if (count($dates) > 1) {
                    $periods = [];
                    for ($i = 1; $i < count($dates); $i++) {
                        $periods[] = $dates[$i] - $dates[$i - 1];
                    }

                    $maxPeriod = max($periods);
                    $avgPeriod = array_sum($periods) / count($periods);

                    \CIBlockElement::SetPropertyValuesEx(
                        $arFields['ID'],
                        $arFields['IBLOCK_ID'],
                        [
                            'MAX_SAVE_DURATION' => $maxPeriod,
                            'AVG_SAVE_DURATION' => (int)$avgPeriod
                        ]
                    );
                }
            }
        }
    }

    protected static function addHLRecord($fields)
    {
        if (!\Bitrix\Main\Loader::includeModule('highloadblock')) {
            return;
        }

        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList([
            'filter' => ['=NAME' => 'TestEntity']
        ])->fetch();

        if (!$hlblock) {
            return;
        }

        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $entityDataClass = $entity->getDataClass();
        $entityDataClass::add($fields);
    }
}
