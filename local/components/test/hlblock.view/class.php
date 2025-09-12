<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Loader;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Entity;
use Bitrix\Main\ORM\Query\Query;

class TestHlblockViewComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        if (!Loader::includeModule('highloadblock') || !Loader::includeModule('iblock')) {
            ShowError('Необходимые модули не подключены');
            return;
        }

        $pageSize = intval($this->arParams['PAGE_SIZE']) ?: 10;
        $nav = new PageNavigation("nav-hlblock");
        $nav->allowAllRecords(true)
            ->setPageSize($pageSize)
            ->initFromUri();

   
        $hlblock = HL\HighloadBlockTable::getList([
            'filter' => ['=NAME' => 'TestEntity']
        ])->fetch();
        if (!$hlblock) {
            ShowError('HL-блок не найден');
            return;
        }

        $entity = HL\HighloadBlockTable::compileEntity($hlblock);
        $dataClass = $entity->getDataClass();

        $totalCount = $dataClass::getCount();

        $query = new Query($entity);
        $query->setSelect([
            'UF_DATE',
            'UF_USER_ID',
            'USER_LOGIN' => 'USER.LOGIN',
            'USER_NAME' => 'USER.NAME',
            'USER_LAST_NAME' => 'USER.LAST_NAME',
            'UF_ELEMENT_ID',
            'IBLOCK_ELEMENT_NAME' => 'ELEMENT.NAME',
            'UF_NAME',
            'UF_PREVIEW_TEXT'
        ]);
        $query->setOrder(['UF_DATE' => 'DESC']);
        $query->setLimit($nav->getLimit());
        $query->setOffset($nav->getOffset());

        $query->registerRuntimeField(
            'USER',
            [
                'data_type' => '\Bitrix\Main\UserTable',
                'reference' => ['=this.UF_USER_ID' => 'ref.ID'],
                'join_type' => 'LEFT'
            ]
        );
    
        $query->registerRuntimeField(
            'ELEMENT',
            [
                'data_type' => '\Bitrix\Iblock\ElementTable',
                'reference' => ['=this.UF_ELEMENT_ID' => 'ref.ID'],
                'join_type' => 'LEFT'
            ]
        );

        $result = $query->exec();
        $rows = [];
        while ($row = $result->fetch()) {
            $rows[] = $row;
        }

        $nav->setRecordCount($totalCount);

        $this->arResult = [
            'ROWS' => $rows,
            'NAV' => $nav,
        ];

        $this->includeComponentTemplate();
    }
}