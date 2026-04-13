<?php
/**
 * rocada.visits / lib/api/visits_plan.php
 * POST /api/visits/{id}/plan          — сохранить запланированный визит
 * GET  /api/visits/{id}/unload-points — список точек разгрузки по сделке
 *
 * Логика скопирована из rocada.telegram/lib/app/new-visit/index.php (case 'get_unload_points' + case 'plan_visit')
 */

use Bitrix\Crm\DealTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// ---------------------------------------------------------------------------
// GET /api/visits/{id}/unload-points
// Возвращает пункты разгрузки для компании сделки (иблок 206, PROPERTY_1817 = company_id)
// Как в rocada.telegram case 'get_unload_points'
// ---------------------------------------------------------------------------
function handleVisitsUnloadPoints(array $params): void
{
    if ($params['method'] !== 'GET') {
        pwaSendError('Method Not Allowed', 405);
    }

    $dealId = (int)($params['id'] ?? 0);
    if ($dealId <= 0) {
        pwaSendError('Missing deal ID', 400);
    }

    requireAuth(); // проверяет Bearer token

    if (!Loader::includeModule('crm') || !Loader::includeModule('iblock')) {
        pwaSendError('Modules CRM or Iblock not installed', 500);
    }

    // Получаем COMPANY_ID из сделки (как в telegram-боте)
    $deal = DealTable::getList([
        'filter' => ['=ID' => $dealId],
        'select' => ['COMPANY_ID'],
    ])->fetch();

    $companyIds = [];
    if (!empty($deal['COMPANY_ID'])) {
        $companyIds[] = (int)$deal['COMPANY_ID'];
    }

    if (empty($companyIds)) {
        pwaSendJson(['points' => [], 'message' => 'Для этой сделки не найдена связанная компания.']);
        return;
    }

    $points = [];
    $res = \CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID'    => 206,      // иблок "Пункты разгрузки" — как в rocada.telegram
            'PROPERTY_1817' => $companyIds  // свойство "Компания"
        ],
        false,
        false,
        ['ID', 'NAME']
    );
    while ($item = $res->fetch()) {
        $points[] = ['id' => (int)$item['ID'], 'name' => $item['NAME']];
    }

    pwaSendJson(['points' => $points]);
}

// ---------------------------------------------------------------------------
// POST /api/visits/{id}/plan
// Сохраняет запланированный визит — обновляет поля сделки:
//   UF_UNLOAD_DOCS  = point_id
//   {visit_date_field} = дата/время в формате dd.mm.yyyy HH:ii:ss
// Как в rocada.telegram case 'plan_visit'
// ---------------------------------------------------------------------------
function handleVisitsPlan(array $params): void
{
    if ($params['method'] !== 'POST') {
        pwaSendError('Method Not Allowed', 405);
    }

    $dealId = (int)($params['id'] ?? 0);
    if ($dealId <= 0) {
        pwaSendError('Missing deal ID', 400);
    }

    requireAuth(); // проверяет Bearer token

    $body      = $params['body'] ?? [];
    $pointId   = (int)($body['point_id'] ?? 0);
    $visitDate = trim($body['visit_date'] ?? '');
    $visitTime = trim($body['visit_time'] ?? '');

    if ($pointId <= 0) {
        pwaSendError('Некорректный ID адреса', 400);
    }
    if (empty($visitDate) || empty($visitTime)) {
        pwaSendError('Дата и время визита обязательны', 400);
    }

    // Поле даты — берём из rocada.visits, резерв — из rocada.telegram, как в оригинале
    $moduleId       = $params['moduleId'] ?? 'rocada.visits';
    $visitDateField = Option::get($moduleId, 'visit_date_field', '');
    if (empty($visitDateField)) {
        $visitDateField = Option::get('rocada.telegram', 'visit_date_field', 'UF_CRM_1670850308849');
    }

    // Отключаем проверку на уже запланированный визит
    // В логике бизнеса: если визит уже был запланирован (например, на месяц вперёд), 
    // менеджер должен иметь возможность перенести его на сегодня, чтобы закрыть его.

    // Форматируем дату
    $visitDateTime = '';
    try {
        $dt            = new \DateTime("{$visitDate} {$visitTime}");
        $visitDateTime = $dt->format('d.m.Y H:i:s');
    } catch (\Exception $e) {
        pwaSendError('Не удалось отформатировать дату и время', 400);
    }

    // Получаем текущие данные сделки, чтобы узнать CATEGORY_ID
    $dealCurrent = DealTable::getList([
        'filter' => ['=ID' => $dealId],
        'select' => ['CATEGORY_ID', 'STAGE_ID']
    ])->fetch();

    $categoryId = (int)($dealCurrent['CATEGORY_ID'] ?? 0);
    $targetStageId = '';

    // Ищем подходящее направление по воронке сделки, чтобы узнать рабочую стадию (stages_today)
    require_once __DIR__ . '/../helpers/directions.php';
    $directions = getAllDirections($moduleId);
    $matchedDir = $directions[0] ?? null; // по умолчанию первое направление

    foreach ($directions as $dir) {
        $pipelines = array_map('intval', $dir['pipelines'] ?? []);
        if (in_array($categoryId, $pipelines, true)) {
            $matchedDir = $dir;
            break;
        }
    }

    if ($matchedDir) {
        $todayStr = date('Y-m-d');
        $tomorrowStr = date('Y-m-d', strtotime('+1 day'));
        $visitStr = '';
        try {
            $dtObj = new \DateTime("{$visitDate} {$visitTime}");
            $visitStr = $dtObj->format('Y-m-d');
        } catch (\Exception $e) {}

        if ($visitStr === $todayStr && !empty($matchedDir['stages_today'])) {
            $targetStageId = $matchedDir['stages_today'][0];
        } elseif ($visitStr === $tomorrowStr && !empty($matchedDir['stages_tomorrow'])) {
            $targetStageId = $matchedDir['stages_tomorrow'][0];
        } elseif ($visitStr > $tomorrowStr && !empty($matchedDir['stages_planned'])) {
            $targetStageId = $matchedDir['stages_planned'][0];
        } elseif (!empty($matchedDir['stages_today'])) {
            // fallback
            $targetStageId = $matchedDir['stages_today'][0];
        }
    }

    // Собираем поля для обновления
    $updateFields = [
        'UF_UNLOAD_DOCS' => $pointId,
        $visitDateField  => $visitDateTime,
    ];

    if (!empty($targetStageId) && $dealCurrent['STAGE_ID'] !== $targetStageId) {
        $updateFields['STAGE_ID'] = $targetStageId;
    }

    if (!Loader::includeModule('crm')) {
        pwaSendError('Module CRM not installed', 500);
    }

    // Обновляем сделку, двигая стадию и записывая новые данные
    $updateResult = DealTable::update($dealId, $updateFields);

    if ($updateResult->isSuccess()) {
        pwaSendJson(['deal_id' => $dealId, 'message' => 'Визит успешно запланирован']);
    } else {
        $errors = implode(', ', $updateResult->getErrorMessages());
        pwaSendError('Ошибка планирования визита: ' . $errors, 400);
    }
}
