<?php


namespace Test;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Mail\Event;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Entity;

class Main
{
    public static function OnBeforeIBlockElementAddHandler(&$arFields)
    {
        $logFile = __DIR__ . '/../log/event_handler.log';
        $logData = date('Y-m-d H:i:s') . " | Вызван обработчик OnBeforeIBlockElementAddHandler\n";
        $logData .= 'arFields: ' . var_export($arFields, true) . "\n";
        try {
          
            $moduleId = 'test_module';
            $selectedIblock = Option::get($moduleId, 'selected_iblock', '');
            $emailList = Option::get($moduleId, 'email_list', '');
            $logData .= "selectedIblock: $selectedIblock, emailList: $emailList\n";

            
            if (
                !empty($selectedIblock)
                && !empty($emailList)
                && isset($arFields['IBLOCK_ID'])
                && $arFields['IBLOCK_ID'] == $selectedIblock
            ) {
                
                $emails = array_filter(array_map('trim', explode(',', $emailList)));
                $logData .= 'emails: ' . var_export($emails, true) . "\n";
                if (!empty($emails)) {
                  
                    $eventResult = Event::send([
                        "EVENT_NAME" => "NEW_IBLOCK_ELEMENT_NOTIFICATION",
                        "LID" => "s1",
                        "C_FIELDS" => [
                            "NAME" => $arFields['NAME'],
                            "PREVIEW_TEXT" => $arFields['PREVIEW_TEXT'],
                            "EMAIL_TO" => implode(',', $emails),
                        ],
                    ]);
                    $logData .= 'Event::send result: ' . var_export($eventResult, true) . "\n";
                } else {
                    $logData .= "Пустой список email\n";
                }
            } else {
                $logData .= "Условия для отправки события не выполнены\n";
            }
        } catch (\Throwable $e) {
            $logData .= 'Ошибка: ' . $e->getMessage() . "\n";
        }
        file_put_contents($logFile, $logData, FILE_APPEND);
    }

    public static function OnAfterIBlockElementAddHandler(&$arFields)
    {
        $moduleId = 'test_module';
        $selectedIblock = Option::get($moduleId, 'selected_iblock', '');
        if (
            !empty($selectedIblock)
            && isset($arFields['IBLOCK_ID'])
            && $arFields['IBLOCK_ID'] == $selectedIblock
        ) {
            self::addHLRecord([
                'UF_DATE' => new \Bitrix\Main\Type\DateTime(),
                'UF_USER_ID' => $arFields['MODIFIED_BY'] ?? $arFields['CREATED_BY'] ?? 0,
                'UF_ELEMENT_ID' => $arFields['ID'],
                'UF_NAME' => $arFields['NAME'],
                'UF_PREVIEW_TEXT' => $arFields['PREVIEW_TEXT'],
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
            && $arFields['IBLOCK_ID'] == $selectedIblock
        ) {
            // Получаем свойства элемента
            if (\CModule::IncludeModule('iblock')) {
                $props = [];
                $res = \CIBlockElement::GetProperty($arFields['IBLOCK_ID'], $arFields['ID'], [], ['CODE' => false]);
                while ($prop = $res->Fetch()) {
                    $props[$prop['CODE']] = $prop;
                }

                // Логирование $props
                $logFile = __DIR__ . '/../log/props_debug.log';
                $logData = date('Y-m-d H:i:s') . " | props: " . var_export($props, true) . "\n";
                file_put_contents($logFile, $logData, FILE_APPEND);

                // Проверяем запрет учета изменений
                
                if ($props['DISABLE_CHANGE_TRACKING']['VALUE']) {
                    return;
                }
            }

            // 1. Инкрементируем EDIT_COUNT
            $editCount = 1;
            if (isset($props['EDIT_COUNT']['VALUE']) && is_numeric($props['EDIT_COUNT']['VALUE'])) {
                $editCount = intval($props['EDIT_COUNT']['VALUE']) + 1;
            }
            $el = new \CIBlockElement();
            $el->SetPropertyValuesEx($arFields['ID'], $arFields['IBLOCK_ID'], [
                'EDIT_COUNT' => $editCount
            ]);

            // 2. Фиксируем изменение в HL-блоке
            self::addHLRecord([
                'UF_DATE' => new \Bitrix\Main\Type\DateTime(),
                'UF_USER_ID' => $arFields['MODIFIED_BY'] ?? 0,
                'UF_ELEMENT_ID' => $arFields['ID'],
                'UF_NAME' => $arFields['NAME'],
                'UF_PREVIEW_TEXT' => $arFields['PREVIEW_TEXT'],
            ]);

            // 3. Получаем все изменения из HL-блока
            if (\CModule::IncludeModule('highloadblock')) {
                $hlblock = HL\HighloadBlockTable::getList([
                    'filter' => ['=NAME' => 'TestEntity']
                ])->fetch();
                if ($hlblock) {
                    $entity = HL\HighloadBlockTable::compileEntity($hlblock);
                    $entityDataClass = $entity->getDataClass();
                    $rsData = $entityDataClass::getList([
                        'filter' => ['UF_ELEMENT_ID' => $arFields['ID']],
                        'order' => ['UF_DATE' => 'ASC'],
                        'select' => ['UF_DATE']
                    ]);
                    $dates = [];
                    while ($row = $rsData->fetch()) {
                        if ($row['UF_DATE'] instanceof \Bitrix\Main\Type\DateTime) {
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

                        // Сохраняем значения в свойствах (в секундах)
                        $el->SetPropertyValuesEx($arFields['ID'], $arFields['IBLOCK_ID'], [
                            'MAX_SAVE_DURATION' => $maxPeriod,
                            'AVG_SAVE_DURATION' => (int)$avgPeriod
                        ]);
                    }
                }
            }
        }
    }

    protected static function addHLRecord($fields)
    {
        if (!\CModule::IncludeModule('highloadblock')) return;
        $hlblock = HL\HighloadBlockTable::getList([
            'filter' => ['=NAME' => 'TestEntity']
        ])->fetch();
        if (!$hlblock) return;
        $entity = HL\HighloadBlockTable::compileEntity($hlblock);
        $entityDataClass = $entity->getDataClass();
        $entityDataClass::add($fields);
    }
}
