<?php

use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Entity;


class test_module extends CModule
{

    public function __construct()
    {
        if (is_file(__DIR__ . '/version.php')) {
            include_once(__DIR__ . '/version.php');
            $this->MODULE_ID           = 'test_module';
            $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
            $this->MODULE_NAME         = "Тестовый модуль";
            $this->MODULE_DESCRIPTION  = "Описание тестового модуля";
        } else {
            CAdminMessage::ShowMessage(
                "version.php не найден"
            );
        }
    }

    public function DoInstall()
    {
        global $APPLICATION;
        $step = (int)$_REQUEST['step'];

        if ($step < 2) {

            $APPLICATION->IncludeAdminFile(
                "Установка модуля test_module",
                __DIR__ . "/step.php"
            );
            return;
        }


        $selectedIblock = intval($_REQUEST['selected_iblock']);
        if ($selectedIblock > 0) {
            \Bitrix\Main\Config\Option::set($this->MODULE_ID, 'selected_iblock', $selectedIblock);

            if (\CModule::IncludeModule('iblock')) {
                $ibp = new \CIBlockProperty;

                $existingProps = [];
                $propRes = \CIBlockProperty::GetList([], ["IBLOCK_ID" => $selectedIblock]);
                while ($prop = $propRes->Fetch()) {
                    $existingProps[$prop["CODE"]] = true;
                }


                if (!isset($existingProps["EDIT_COUNT"])) {
                    $ibp->Add([
                        "NAME" => "Количество редактирования элемента",
                        "ACTIVE" => "Y",
                        "SORT" => "100",
                        "CODE" => "EDIT_COUNT",
                        "PROPERTY_TYPE" => "N",
                        "IBLOCK_ID" => $selectedIblock,
                    ]);
                }


                if (!isset($existingProps["MAX_SAVE_DURATION"])) {
                    $ibp->Add([
                        "NAME" => "Максимальная длительность сохранения введенных значений",
                        "ACTIVE" => "Y",
                        "SORT" => "110",
                        "CODE" => "MAX_SAVE_DURATION",
                        "PROPERTY_TYPE" => "N",
                        "IBLOCK_ID" => $selectedIblock,
                    ]);
                }

                if (!isset($existingProps["AVG_SAVE_DURATION"])) {
                    $ibp->Add([
                        "NAME" => "Среднее значение длительности сохранения введенных значений",
                        "ACTIVE" => "Y",
                        "SORT" => "120",
                        "CODE" => "AVG_SAVE_DURATION",
                        "PROPERTY_TYPE" => "N",
                        "IBLOCK_ID" => $selectedIblock,
                    ]);
                }


                if (!isset($existingProps["DISABLE_CHANGE_TRACKING"])) {
                    $ibp->Add([
                        "NAME" => "Запретить вести учет изменений",
                        "ACTIVE" => "Y",
                        "SORT" => "130",
                        "CODE" => "DISABLE_CHANGE_TRACKING",
                        "PROPERTY_TYPE" => "L",
                        "LIST_TYPE" => "C",
                        "VALUES" => [
                            ["VALUE" => "Y", "DEF" => "N", "SORT" => "10", "XML_ID" => "Y"],
                        ],
                        "IBLOCK_ID" => $selectedIblock,
                    ]);
                }
            }
        }

        ModuleManager::registerModule($this->MODULE_ID);

        //Дефолтные значения
        \Bitrix\Main\Config\Option::set($this->MODULE_ID, 'company_type', 'ООО');
        \Bitrix\Main\Config\Option::set($this->MODULE_ID, 'uses_usn', 'Y');


        EventManager::getInstance()->registerEventHandler(
            'iblock',
            'OnBeforeIBlockElementAdd',
            $this->MODULE_ID,
            'Test\\Main',
            'OnBeforeIBlockElementAddHandler'
        );

        EventManager::getInstance()->registerEventHandler(
            'iblock',
            'OnBeforeIBlockElementUpdate',
            $this->MODULE_ID,
            'Test\\Main',
            'OnBeforeIBlockElementUpdateHandler'
        );

        EventManager::getInstance()->registerEventHandler(
            'iblock',
            'OnAfterIBlockElementAdd',
            $this->MODULE_ID,
            'Test\\Main',
            'OnAfterIBlockElementAddHandler'
        );
        EventManager::getInstance()->registerEventHandler(
            'iblock',
            'OnAfterIBlockElementUpdate',
            $this->MODULE_ID,
            'Test\\Main',
            'OnAfterIBlockElementUpdateHandler'
        );



        \CModule::IncludeModule('highloadblock');
        $hlblockName = 'TestEntity';
        $hlblockTableName = 'test_entity';

        $hlblock = HL\HighloadBlockTable::getList([
            'filter' => ['=NAME' => $hlblockName]
        ])->fetch();

        if (!$hlblock) {

            $result = HL\HighloadBlockTable::add([
                'NAME' => $hlblockName,
                'TABLE_NAME' => $hlblockTableName
            ]);
            if ($result->isSuccess()) {
                $hlblockId = $result->getId();


                $userTypeEntity = new \CUserTypeEntity();


                $userTypeEntity->Add([
                    'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
                    'FIELD_NAME' => 'UF_DATE',
                    'USER_TYPE_ID' => 'datetime',
                    'XML_ID' => 'UF_DATE',
                    'SORT' => 100,
                    'MULTIPLE' => 'N',
                    'MANDATORY' => 'N',
                    'SHOW_FILTER' => 'Y',
                    'SHOW_IN_LIST' => 'Y',
                    'EDIT_IN_LIST' => 'Y',
                    'IS_SEARCHABLE' => 'N',
                    'SETTINGS' => [],
                    'EDIT_FORM_LABEL' => ['ru' => 'Дата/время'],
                    'LIST_COLUMN_LABEL' => ['ru' => 'Дата/время'],
                    'LIST_FILTER_LABEL' => ['ru' => 'Дата/время'],
                ]);

                $userTypeEntity->Add([
                    'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
                    'FIELD_NAME' => 'UF_USER_ID',
                    'USER_TYPE_ID' => 'integer',
                    'XML_ID' => 'UF_USER_ID',
                    'SORT' => 200,
                    'MULTIPLE' => 'N',
                    'MANDATORY' => 'N',
                    'SHOW_FILTER' => 'Y',
                    'SHOW_IN_LIST' => 'Y',
                    'EDIT_IN_LIST' => 'Y',
                    'IS_SEARCHABLE' => 'N',
                    'SETTINGS' => [],
                    'EDIT_FORM_LABEL' => ['ru' => 'ID пользователя'],
                    'LIST_COLUMN_LABEL' => ['ru' => 'ID пользователя'],
                    'LIST_FILTER_LABEL' => ['ru' => 'ID пользователя'],
                ]);

                $userTypeEntity->Add([
                    'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
                    'FIELD_NAME' => 'UF_ELEMENT_ID',
                    'USER_TYPE_ID' => 'integer',
                    'XML_ID' => 'UF_ELEMENT_ID',
                    'SORT' => 300,
                    'MULTIPLE' => 'N',
                    'MANDATORY' => 'N',
                    'SHOW_FILTER' => 'Y',
                    'SHOW_IN_LIST' => 'Y',
                    'EDIT_IN_LIST' => 'Y',
                    'IS_SEARCHABLE' => 'N',
                    'SETTINGS' => [],
                    'EDIT_FORM_LABEL' => ['ru' => 'ID элемента'],
                    'LIST_COLUMN_LABEL' => ['ru' => 'ID элемента'],
                    'LIST_FILTER_LABEL' => ['ru' => 'ID элемента'],
                ]);

                $userTypeEntity->Add([
                    'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
                    'FIELD_NAME' => 'UF_NAME',
                    'USER_TYPE_ID' => 'string',
                    'XML_ID' => 'UF_NAME',
                    'SORT' => 400,
                    'MULTIPLE' => 'N',
                    'MANDATORY' => 'N',
                    'SHOW_FILTER' => 'Y',
                    'SHOW_IN_LIST' => 'Y',
                    'EDIT_IN_LIST' => 'Y',
                    'IS_SEARCHABLE' => 'Y',
                    'SETTINGS' => ['SIZE' => 255],
                    'EDIT_FORM_LABEL' => ['ru' => 'Наименование'],
                    'LIST_COLUMN_LABEL' => ['ru' => 'Наименование'],
                    'LIST_FILTER_LABEL' => ['ru' => 'Наименование'],
                ]);

                $userTypeEntity->Add([
                    'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
                    'FIELD_NAME' => 'UF_PREVIEW_TEXT',
                    'USER_TYPE_ID' => 'string',
                    'XML_ID' => 'UF_PREVIEW_TEXT',
                    'SORT' => 500,
                    'MULTIPLE' => 'N',
                    'MANDATORY' => 'N',
                    'SHOW_FILTER' => 'Y',
                    'SHOW_IN_LIST' => 'Y',
                    'EDIT_IN_LIST' => 'Y',
                    'IS_SEARCHABLE' => 'Y',
                    'SETTINGS' => ['ROWS' => 3, 'SIZE' => 255],
                    'EDIT_FORM_LABEL' => ['ru' => 'Анонс'],
                    'LIST_COLUMN_LABEL' => ['ru' => 'Анонс'],
                    'LIST_FILTER_LABEL' => ['ru' => 'Анонс'],
                ]);
            }
        }

        $event = new CEventType;
        $event->Add(array(
            "LID" => "s1",
            "EVENT_NAME" => "NEW_IBLOCK_ELEMENT_NOTIFICATION",
            "NAME" => "Уведомление о новом элементе инфоблока",
            "DESCRIPTION" => "#NAME# - Наименование элемента\n#PREVIEW_TEXT# - Анонс элемента"
        ));

        $message = new CEventMessage;
        $message->Add(array(
            "ACTIVE" => "Y",
            "EVENT_NAME" => "NEW_IBLOCK_ELEMENT_NOTIFICATION",
            "LID" => "s1",
            "EMAIL_FROM" => "#DEFAULT_EMAIL_FROM#",
            "EMAIL_TO" => "admin@example.com",
            "SUBJECT" => "Новый элемент: #NAME#",
            "MESSAGE" => "Добавлен новый элемент инфоблока:\n\nНаименование: #NAME#\n\nАнонс: #PREVIEW_TEXT#",
            "BODY_TYPE" => "text"
        ));
    }

    public function DoUninstall()
    {
        global $APPLICATION, $step;
        $step = (int)$_REQUEST['step'];

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                "Удаление модуля test_module",
                __DIR__ . "/unstep.php"
            );
        } else {
            $deleteData = $_REQUEST['delete_data'] === 'Y';


            \Bitrix\Main\Config\Option::set($this->MODULE_ID, 'hlblock_deleted', $deleteData ? 'Y' : 'N');

            if ($deleteData) {
                \CModule::IncludeModule('highloadblock');
                $hlblock = HL\HighloadBlockTable::getList([
                    'filter' => ['=NAME' => 'TestEntity']
                ])->fetch();
                if ($hlblock) {
                    HL\HighloadBlockTable::delete($hlblock['ID']);
                }
            }


            $eventMessage = new CEventMessage;
            $res = $eventMessage->GetList(
                $by = "id",
                $order = "desc",
                ["EVENT_NAME" => "NEW_IBLOCK_ELEMENT_NOTIFICATION"]
            );
            while ($message = $res->Fetch()) {
                $eventMessage->Delete($message["ID"]);
            }


            $eventType = new CEventType;
            $eventType->Delete("NEW_IBLOCK_ELEMENT_NOTIFICATION");


            EventManager::getInstance()->unRegisterEventHandler(
                'iblock',
                'OnBeforeIBlockElementAdd',
                $this->MODULE_ID,
                'Test\\Main',
                'OnBeforeIBlockElementAddHandler'
            );

            EventManager::getInstance()->unRegisterEventHandler(
                'iblock',
                'OnAfterIBlockElementAdd',
                $this->MODULE_ID,
                'Test\\Main',
                'OnAfterIBlockElementAddHandler'
            );
            EventManager::getInstance()->unRegisterEventHandler(
                'iblock',
                'OnAfterIBlockElementUpdate',
                $this->MODULE_ID,
                'Test\\Main',
                'OnAfterIBlockElementUpdateHandler'
            );

            EventManager::getInstance()->unRegisterEventHandler(
                'iblock',
                'OnBeforeIBlockElementUpdate',
                $this->MODULE_ID,
                'Test\\Main',
                'OnBeforeIBlockElementUpdateHandler'
            );

            ModuleManager::unRegisterModule($this->MODULE_ID);
        }
    }
}
