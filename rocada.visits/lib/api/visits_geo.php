<?php
/**
 * Контроллер геолокации
 * rocada.visits / lib/api/visits_geo.php
 *
 * POST /api/visits/{id}/geo
 * Body: { "lat": 55.7558, "lng": 37.6173 }
 */

use Bitrix\Crm\DealTable;

function handleVisitsGeo(array $params): void
{
    if ($params['method'] !== 'POST') {
        pwaSendError('Method Not Allowed', 405);
    }

    $userId = requireAuth();
    $dealId = $params['id'];
    $body   = $params['body'];
    $mid    = $params['moduleId'];

    if (!$dealId) {
        pwaSendError('ID визита обязателен', 422);
    }

    $lat = $body['lat'] ?? null;
    $lng = $body['lng'] ?? null;

    if ($lat === null || $lng === null) {
        pwaSendError('lat и lng обязательны', 422);
    }

    $lat = (float)$lat;
    $lng = (float)$lng;

    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        pwaSendError('Некорректные координаты', 422);
    }

    $dirCfg   = getDirectionConfig($params['get']['direction'] ?? 'sales', $mid);
    $latField = $dirCfg['lat_field'] ?? '';
    $lngField = $dirCfg['lng_field'] ?? '';

    if (empty($latField) || empty($lngField)) {
        pwaSendError('Поля координат не настроены в модуле', 500);
    }

    // Проверка доступа к сделке
    $deal = DealTable::getList([
        'filter' => ['=ID' => $dealId, '=ASSIGNED_BY_ID' => $userId],
        'select' => ['ID'],
    ])->fetch();

    if (!$deal) {
        pwaSendError('Визит не найден или нет доступа', 404);
    }

    $res = DealTable::update($dealId, [
        $latField => $lat,
        $lngField => $lng,
    ]);

    if (!$res->isSuccess()) {
        pwaSendError(implode('; ', $res->getErrorMessages()), 500);
    }

    pwaSendJson([
        'deal_id' => $dealId,
        'lat'     => $lat,
        'lng'     => $lng,
        'message' => 'Геолокация сохранена',
    ]);
}
