<?php
/**
 * rocada.visits / lib/api/deals.php
 * GET /api/deals/search?q=... — поиск сделок по ID или названию компании
 *
 * Логика аналогична rocada.telegram case 'find_deals'
 */

use Bitrix\Crm\DealTable;
use Bitrix\Crm\CompanyTable;
use Bitrix\Main\Loader;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

function handleDeals(array $params): void
{
    if ($params['method'] !== 'GET') {
        pwaSendError('Method Not Allowed', 405);
    }

    requireAuth();

    $action = $params['action'] ?? $params['get']['action'] ?? '';

    if ($action === 'search') {
        searchDeals($params);
    } else {
        pwaSendError('Unknown action', 404);
    }
}

/**
 * Поиск сделок по ID или названию компании
 * Как в rocada.telegram case 'find_deals'
 */
function searchDeals(array $params): void
{
    Loader::includeModule('crm');

    $query = trim($params['get']['q'] ?? '');
    if (empty($query)) {
        pwaSendError('Параметр q обязателен', 400);
    }

    $dealSelect = ['ID', 'TITLE', 'STAGE_ID', 'COMPANY_ID', 'ASSIGNED_BY_ID'];
    $foundDeals = [];

    // 1. Если запрос числовой — ищем по ID сделки
    if (is_numeric($query)) {
        $deal = DealTable::getList([
            'filter' => ['=ID' => (int)$query],
            'select' => $dealSelect,
        ])->fetch();
        if ($deal) {
            $foundDeals[] = $deal;
        }
    }

    // 2. Если по ID не нашли ИЛИ запрос не числовой — ищем по названию компании
    if (empty($foundDeals)) {
        $companyRes = CompanyTable::getList([
            'filter' => ['%TITLE' => $query],
            'select' => ['ID'],
            'limit'  => 50,
        ]);
        $companyIds = [];
        while ($company = $companyRes->fetch()) {
            $companyIds[] = $company['ID'];
        }

        if (!empty($companyIds)) {
            $dealListRes = DealTable::getList([
                'filter' => [
                    '@COMPANY_ID' => $companyIds,
                    '!CLOSED'    => 'Y',   // Только открытые сделки
                ],
                'select' => $dealSelect,
                'order'  => ['ID' => 'DESC'],
                'limit'  => 20,
            ]);
            while ($deal = $dealListRes->fetch()) {
                $foundDeals[] = $deal;
            }
        }
    }

    // 3. Если ничего не нашли — пробуем поиск по названию самой сделки
    if (empty($foundDeals)) {
        $dealListRes = DealTable::getList([
            'filter' => [
                '%TITLE'  => $query,
                '!CLOSED' => 'Y',
            ],
            'select' => $dealSelect,
            'order'  => ['ID' => 'DESC'],
            'limit'  => 20,
        ]);
        while ($deal = $dealListRes->fetch()) {
            $foundDeals[] = $deal;
        }
    }

    pwaSendJson(['deals' => $foundDeals]);
}
