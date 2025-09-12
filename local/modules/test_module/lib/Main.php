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
            // Получаем старые значения
            $dbRes = \CIBlockElement::GetList([], ['ID' => $arFields['ID']], false, false, ['ID', 'NAME', 'PREVIEW_TEXT']);
            $old = $dbRes->Fetch();
            if (
                ($old && (
                    $old['NAME'] !== $arFields['NAME'] ||
                    $old['PREVIEW_TEXT'] !== $arFields['PREVIEW_TEXT']
                ))
            ) {
                self::addHLRecord([
                    'UF_DATE' => new \Bitrix\Main\Type\DateTime(),
                    'UF_USER_ID' => $arFields['MODIFIED_BY'] ?? 0,
                    'UF_ELEMENT_ID' => $arFields['ID'],
                    'UF_NAME' => $arFields['NAME'],
                    'UF_PREVIEW_TEXT' => $arFields['PREVIEW_TEXT'],
                ]);
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
