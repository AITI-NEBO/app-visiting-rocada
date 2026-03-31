<?php
namespace Nebo\Map\Api;

use Bitrix\Crm\StatusTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

class Objects
{
public static function visits($sessid = false)
{
    global $USER;

    // Аккуратно работаем с сессией
    session_write_close();
    session_id($sessid);
    session_start();

    $context = json_decode($_SESSION['SESS_AUTH']['CONTEXT'], true);

    // Сразу закрываем сессию, чтобы не блокировалась на время запроса
    session_write_close();

    if (empty($context['userId'])) {
        return [-2];
    }

    // Авторизация пользователя
    $USER->Authorize((int)$context['userId']);

    $deals = [];

    if (\Bitrix\Main\Loader::includeModule('crm')) {
        $moduleId = 'rocada.telegram';

        // Настройки модуля
        $dealStagesOption = \Bitrix\Main\Config\Option::get($moduleId, 'deal_stage_filter', '');
        $arDealStages     = !empty($dealStagesOption) ? explode(',', $dealStagesOption) : [];

        $fieldLonCode = \Bitrix\Main\Config\Option::get($moduleId, 'longitude_field', '');
        $fieldLatCode = \Bitrix\Main\Config\Option::get($moduleId, 'latitude_field', '');

        // Поле даты визита (фиксированное требование)
        $visitFieldCode = 'UF_CRM_1670850308849';

        if (!empty($arDealStages)) {
            // Собираем SELECT
            $arSelect = ['ID', 'TITLE', $visitFieldCode, 'COMPANY_ID'];
            if (!empty($fieldLonCode)) { $arSelect[] = $fieldLonCode; }
            if (!empty($fieldLatCode)) { $arSelect[] = $fieldLatCode; }

            // Фильтр по стадиям (разрешения отключены согласно исходнику) (уже включены)
            $arFilter = [
				'CHECK_PERMISSIONS' => 'N',
                'STAGE_ID'          => $arDealStages,
            ];

            // Сортировка по VISIT_DATE (UF_CRM_1670850308849) — от первого к последнему (ASC)
            $arOrder = [
                $visitFieldCode => 'ASC',
                'ID'            => 'ASC', // стабильность порядка при одинаковой дате
            ];

            $dbRes = \CCrmDeal::GetListEx(
                $arOrder,
                $arFilter,
                false,
                false,
                $arSelect
            );

            while ($deal = $dbRes->Fetch()) {
                // Пост-фильтр: оставляем только сделки, где есть оба координатных поля
                $hasLon = !empty($fieldLonCode) && array_key_exists($fieldLonCode, $deal) && $deal[$fieldLonCode] !== '' && $deal[$fieldLonCode] !== null;
                $hasLat = !empty($fieldLatCode) && array_key_exists($fieldLatCode, $deal) && $deal[$fieldLatCode] !== '' && $deal[$fieldLatCode] !== null;

                if (!$hasLon || !$hasLat) {
                    continue;
                }

                $resultDeal = [
                    'ID'         => $deal['ID'],
                    'TITLE'      => $deal['TITLE'],
                    'LON'        => $deal[$fieldLonCode],
                    'LAT'        => $deal[$fieldLatCode],
                    'VISIT_DATE' => $deal[$visitFieldCode] ?? null,
					'COMPANY_ID' => $deal['COMPANY_ID'],
                ];

                $deals[] = $resultDeal;
            }
        }
    }

    // Выход из системы
    $USER->Logout();

    return $deals ?: [];
}


    public static function get($sessid = false, $filters = [])
    {

        global $USER;

        // Стартуем сессию и пытаемся авторизовать пользователя по $sessid
        session_write_close(); // Закрываем текущую сессию, если она есть
        session_id($sessid);   // Устанавливаем ID сессии
        session_start();       // Стартуем сессию с указанным ID
        
        $context = json_decode($_SESSION['SESS_AUTH']['CONTEXT'], 1);

        if (!$context['userId'])
            return [-2];
       
        $USER->Authorize($context['userId']);
        $deals = [];
        if ($filters['%SEARCH_CONTENT']) {
            $filters['SEARCH_CONTENT'] = $filters['%SEARCH_CONTENT'];
            unset($filters['%SEARCH_CONTENT']);
        }

		if ($filters['tg']) {
			unset($filters['tg']);
			$filters['ASSIGNED_BY_ID'] = $USER->GetID();
		}

        $filters[] = ['!UF_UNLOAD_DOCS' => null];

        // Получаем список сделок
        $dbRes = \CCrmDeal::GetListEx(
            ['ID' => 'ASC'], // Сортировка
            $filters,        // Фильтр
            false,           // Группировка
	        false,           // Пагинация
            ['ID', 'CATEGORY_ID', 'TITLE', 'CONTACT_ID', 'STAGE_ID', 'UF_UNLOAD_DOCS', 'UF_CRM_1670850308849', 'COMPANY_ID'], // Поля для выборки
        );
        
        while ($deal = $dbRes->fetch()) {
            $deal['VIEW'][] = $deal['TITLE'];
            if(isset($deal['UF_CRM_1670850308849']) && $deal['UF_CRM_1670850308849'] !== '')
                $deal['VIEW'][] = $deal['UF_CRM_1670850308849'];
            $deals[$deal['ID']] = $deal;
        }

        // Получаем координаты пунктов выдачи
        $idPoints = array_column($deals, 'UF_UNLOAD_DOCS');

        $arPoints = [];
        $elements = \CIBlockElement::GetList([],['ID' => $idPoints, 'IBLOCK_ID' => 206],false,false,['ID','PROPERTY_LATITUDE','PROPERTY_LONGITUDE']);
        while($element = $elements->fetch()) {
            if(strlen($element['PROPERTY_LATITUDE_VALUE']) > 1 && strlen($element['PROPERTY_LONGITUDE_VALUE']) > 1)
				$arPoints[$element['ID']] = [
					'LATITUDE' => $element['PROPERTY_LATITUDE_VALUE'],
					'LONGITUDE' => $element['PROPERTY_LONGITUDE_VALUE'],
				];
        }

        $arDeals = [];
        foreach ($deals as $deal){
            if(is_array($arPoints[$deal['UF_UNLOAD_DOCS']])) {
                $deal['UF_LATITUDE'] = $arPoints[$deal['UF_UNLOAD_DOCS']]['LATITUDE'];
                $deal['UF_LONGITUDE'] = $arPoints[$deal['UF_UNLOAD_DOCS']]['LONGITUDE'];
                $arDeals[] = $deal;
            }
        }
        $deals = $arDeals;

        
        // Получаем данные контактов
        $contactIds = array_column($deals, 'CONTACT_ID');
        if (!empty($contactIds)) {
            $contactFilter = ['ID' => $contactIds];
            $dbContacts = \CCrmContact::GetListEx(
                ['ID' => 'ASC'],
                $contactFilter,
                false,
                false,
                ['ID', 'FULL_NAME'] // Убираем PHONE, т.к. будем отдельно вытягивать множественные поля
            );
        
            $contacts = [];
            while ($contact = $dbContacts->Fetch()) {
                $contacts[$contact['ID']] = $contact;
            }

            // Получаем телефоны контактов
            $dbPhones = \CCrmFieldMulti::GetList(
                ['ID' => 'ASC'],
                [
                    'ENTITY_ID' => 'CONTACT',
                    'ELEMENT_ID' => $contactIds,
                    'TYPE_ID' => 'PHONE'
                ]
            );

            while ($phone = $dbPhones->Fetch()) {
                $contacts[$phone['ELEMENT_ID']]['CRM_DEAL_CONTACT_PHONE'][] = $phone['VALUE'];
            }

            // Присваиваем данные контактов сделкам
            foreach ($deals as &$deal) {
                if (isset($contacts[$deal['CONTACT_ID']])) {
                    $deal['VIEW'][] = $contacts[$deal['CONTACT_ID']]['FULL_NAME'];
                    $deal['VIEW'][] = implode(', ', $contacts[$deal['CONTACT_ID']]['CRM_DEAL_CONTACT_PHONE'] ?? []);
                }
            }
            unset($deal);
        }
        
        // Получаем данные статусов через StatusTable
        $statusRes = StatusTable::getList([
            'filter' => [
                'ENTITY_ID' => ['DEAL_STAGE', "DEAL_STAGE_{$filters['CATEGORY_ID']}"], // Добавьте другие ENTITY_ID, если необходимо
            ],
            'select' => ['STATUS_ID', 'COLOR'],
            'order' => ['STATUS_ID' => 'ASC']
        ]);
    
        $statuses = [];
        while ($status = $statusRes->fetch()) {
            $statuses[$status['STATUS_ID']] = $status['COLOR'];
        }

        foreach ($deals as &$deal) {
            if (isset($statuses[$deal['STAGE_ID']])) {
                $deal['COLOR'] = $statuses[$deal['STAGE_ID']];
            }
        }

        unset($deal);
        
        foreach ($deals as &$arr) {
            $arr['URL'] = 'https://' . explode(':', $_SERVER['HTTP_HOST'])[0] . "/crm/deal/details/{$arr['ID']}/";
        }
    
        $USER->Logout();
        session_write_close(); // Закрываем текущую сессию, если она есть

        return array_values($deals) ?? [];
    }
}
