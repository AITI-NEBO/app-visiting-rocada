<?php
/**
 * rocada.visits — API Entry Point (router.php)
 * Маршрутизация через ?route=api/visits/42 или PATH_INFO
 */

// ── ① CORS — ПЕРВЫМ, до любого PHP-кода ──────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── ② Перехват uncaught exceptions → JSON ────────────────────────────────────
set_exception_handler(function (Throwable $e) {
    // Убедимся что CORS всё ещё отправлен (на случай flush etc.)
    if (!headers_sent()) {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'file'    => basename($e->getFile()) . ':' . $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// ── ③ Bitrix Bootstrap ───────────────────────────────────────────────────────
define('NOT_CHECK_PERMISSIONS', true);
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('STOP_STATISTICS', true);
define('BX_SECURITY_SESSION_READONLY', true); // Не трогаем сессию, ускоряет запрос

// Буферизация вывода — перехватываем случайный HTML от Bitrix
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/bx_root.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// Сбрасываем любой HTML что мог выдать prolog (сессии, куки, редиректы)
ob_clean();

date_default_timezone_set('Europe/Moscow');

// ── ④ Загрузка модулей ────────────────────────────────────────────────────────
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

Loader::includeModule('crm');
Loader::includeModule('highloadblock');
// НЕ включаем 'rocada.visits' — это сам модуль, бесмысленный self-include

// ── ⑤ Обновляем CORS из настроек (если задан конкретный URL) ─────────────────
$moduleId = 'rocada.visits';
$pwaUrl   = Option::get($moduleId, 'pwa_url', '');
if (!empty($pwaUrl)) {
    header('Access-Control-Allow-Origin: ' . $pwaUrl);
}

// ── ⑥ Хелперы ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/directions.php';
require_once __DIR__ . '/helpers/crm.php';

// ── ⑦ Разбор маршрута ────────────────────────────────────────────────────────
$route = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $route = trim($_SERVER['PATH_INFO'], '/');
} elseif (!empty($_GET['route'])) {
    $route = trim($_GET['route'], '/');
}

$method   = strtoupper($_SERVER['REQUEST_METHOD']);
$segments = explode('/', $route);

if (count($segments) < 2 || $segments[0] !== 'api') {
    pwaSendError('Not Found', 404);
}

$resource = $segments[1] ?? '';
$id       = isset($segments[2]) && is_numeric($segments[2]) ? (int)$segments[2] : null;
$action   = $id !== null ? ($segments[3] ?? '') : ($segments[2] ?? '');

$params = [
    'method'   => $method,
    'resource' => $resource,
    'id'       => $id,
    'action'   => $action,
    'get'      => $_GET,
    'body'     => (array)(json_decode(file_get_contents('php://input'), true) ?? []),
    'files'    => $_FILES,
    'moduleId' => $moduleId,
];

// ── ⑧ Диспетчер ──────────────────────────────────────────────────────────────
switch ($resource) {
    case 'auth':
        require_once __DIR__ . '/api/auth.php';
        handleAuth($params);
        break;

    case 'user':
        require_once __DIR__ . '/api/user.php';
        handleUser($params);
        break;

    case 'directions':
        require_once __DIR__ . '/api/directions.php';
        handleDirections($params);
        break;

    case 'visits':
        if ($action === 'geo') {
            require_once __DIR__ . '/api/visits_geo.php';
            handleVisitsGeo($params);
        } elseif ($action === 'comment' || $action === 'comments') {
            require_once __DIR__ . '/api/visits_comment.php';
            handleVisitsComment($params);
        } elseif ($action === 'result') {
            require_once __DIR__ . '/api/visits_result.php';
            handleVisitsResult($params);
        } elseif ($action === 'files') {
            require_once __DIR__ . '/api/visits_files.php';
            handleVisitsFiles($params);
        } else {
            require_once __DIR__ . '/api/visits.php';
            handleVisits($params);
        }
        break;

    case 'clients':
        require_once __DIR__ . '/api/clients.php';
        handleClients($params);
        break;

    case 'stats':
        require_once __DIR__ . '/api/stats.php';
        handleStats($params);
        break;

    case 'config':
        require_once __DIR__ . '/api/config.php';
        handleConfig($params);
        break;

    default:
        pwaSendError('Not Found', 404);
}
