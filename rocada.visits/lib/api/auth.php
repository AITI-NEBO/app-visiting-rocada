<?php
/**
 * Контроллер авторизации
 * rocada.visits / lib/api/auth.php
 *
 * POST /api/auth/login   — вход (логин + пароль Б24) → JWT
 * POST /api/auth/logout  — выход
 * GET  /api/auth/me      — данные пользователя по токену
 */

use Bitrix\Main\UserTable;
use Bitrix\Main\Config\Option;

function handleAuth(array $params): void
{
    switch ($params['action']) {
        case 'login':
            if ($params['method'] !== 'POST') {
                pwaSendError('Method Not Allowed', 405);
            }
            authLogin($params['body'], $params['moduleId']);
            break;

        case 'oauth':
            if ($params['method'] !== 'POST') {
                pwaSendError('Method Not Allowed', 405);
            }
            authOauth($params['body'], $params['moduleId']);
            break;

        case 'logout':
            // Stateless JWT: клиент просто удаляет токен
            pwaSendJson(['message' => 'Logged out']);
            break;

        case 'me':
            $userId = requireAuth();
            $user   = getUserById($userId);
            $user ? pwaSendJson(['user' => $user]) : pwaSendError('User not found', 404);
            break;

        default:
            pwaSendError('Not Found', 404);
    }
}

// ── Авторизация ───────────────────────────────────────────────────────────────
function authLogin(array $body, string $moduleId): void
{
    $login    = trim($body['login']    ?? '');
    $password = $body['password'] ?? '';

    if (empty($login) || empty($password)) {
        pwaSendError('login и password обязательны', 422);
    }

    // Стандартная авторизация Bitrix24
    $cUser  = new CUser();
    $result = $cUser->Login($login, $password);

    if ($result !== true) {
        pwaSendError(is_string($result) ? $result : 'Неверный логин или пароль', 401);
    }

    $userId = (int)$GLOBALS['USER']->GetID();

    // Проверяем доступ к PWA
    checkPwaAccess($userId, $moduleId);

    // Уведомление в Б24 (если включено в настройках)
    if (Option::get($moduleId, 'notify_auth', 'N') === 'Y') {
        try {
            (new \Bitrix\Sender\Integration\Im\Notification())
                ->addTo($userId)
                ->withMessage('RocadaMed PWA: Вы вошли в мобильное приложение')
                ->send();
        } catch (\Throwable $e) {
            // Не прерываем авторизацию при ошибке уведомления
        }
    }

    $token = jwtEncode(['user_id' => $userId], getPwaJwtSecret());
    $user  = getUserById($userId);

    pwaSendJson([
        'token' => $token,
        'user'  => $user,
    ]);
}

// ── OAuth2: обмен кода → access_token Б24 → JWT ───────────────────────────────
function authOauth(array $body, string $moduleId): void
{
    $code        = trim($body['code']         ?? '');
    $redirectUri = trim($body['redirect_uri'] ?? '');

    if (empty($code)) {
        pwaSendError('OAuth code обязателен', 422);
    }

    $clientId     = Option::get($moduleId, 'oauth_client_id',     '');
    $clientSecret = Option::get($moduleId, 'oauth_client_secret', '');
    $b24Base      = rtrim(Option::get($moduleId, 'b24_base_url', 'https://office.rocadatech.ru'), '/');

    if (empty($clientId) || empty($clientSecret)) {
        pwaSendError('OAuth не настроен: укажите oauth_client_id и oauth_client_secret в настройках модуля', 500);
    }

    // ① Код → access_token
    $tokenResp = httpPost($b24Base . '/oauth/token/', [
        'grant_type'    => 'authorization_code',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'code'          => $code,
        'redirect_uri'  => $redirectUri,
    ]);

    if (empty($tokenResp['access_token'])) {
        $desc = $tokenResp['error_description'] ?? ($tokenResp['error'] ?? 'Неизвестная ошибка');
        pwaSendError('OAuth ошибка: ' . $desc, 401);
    }

    // ② Профиль пользователя через REST
    $profile = httpGet($b24Base . '/rest/profile.json?auth=' . urlencode($tokenResp['access_token']));
    $email   = $profile['result']['EMAIL'] ?? '';

    if (empty($email)) {
        pwaSendError('OAuth: не удалось получить профиль пользователя', 401);
    }

    // ③ Найти пользователя Б24 по email
    $row = UserTable::getList([
        'filter' => ['=EMAIL' => $email, '=ACTIVE' => 'Y'],
        'select' => ['ID'],
    ])->fetch();

    if (!$row) {
        pwaSendError('Пользователь с email ' . $email . ' не найден в Битрикс24', 403);
    }

    $userId = (int)$row['ID'];
    checkPwaAccess($userId, $moduleId);

    pwaSendJson([
        'token' => jwtEncode(['user_id' => $userId], getPwaJwtSecret()),
        'user'  => getUserById($userId),
    ]);
}

function httpPost(string $url, array $data): array
{
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 10,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

function httpGet(string $url): array
{
    $ctx = stream_context_create([
        'http' => ['timeout' => 10],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

// ── Получение данных пользователя ─────────────────────────────────────────────
function getUserById(int $userId): ?array
{
    $row = UserTable::getList([
        'filter' => ['=ID' => $userId, '=ACTIVE' => 'Y'],
        'select' => [
            'ID', 'NAME', 'LAST_NAME', 'SECOND_NAME',
            'EMAIL', 'PERSONAL_PHONE', 'WORK_PHONE',
            'PERSONAL_PHOTO', 'WORK_POSITION',
        ],
    ])->fetch();

    if (!$row) {
        return null;
    }

    $fullName = trim(implode(' ', array_filter([
        $row['LAST_NAME']   ?? '',
        $row['NAME']        ?? '',
        $row['SECOND_NAME'] ?? '',
    ])));

    $photoUrl = null;
    if (!empty($row['PERSONAL_PHOTO'])) {
        $file     = \CFile::GetFileArray($row['PERSONAL_PHOTO']);
        $photoUrl = $file ? \CFile::GetFileSRC($file) : null;
    }

    return [
        'id'        => (int)$row['ID'],
        'fullName'  => $fullName,
        'firstName' => $row['NAME']           ?? '',
        'lastName'  => $row['LAST_NAME']      ?? '',
        'email'     => $row['EMAIL']          ?? '',
        'phone'     => $row['PERSONAL_PHONE'] ?: ($row['WORK_PHONE'] ?? ''),
        'position'  => $row['WORK_POSITION']  ?? '',
        'photo'     => $photoUrl,
    ];
}

// ── Проверка доступа к PWA ────────────────────────────────────────────────────
function checkPwaAccess(int $userId, string $moduleId): void
{
    $allowedRaw = Option::get($moduleId, 'pwa_allowed_users', '');
    if (empty($allowedRaw)) {
        return; // Доступ открыт всем
    }
    $allowedIds = json_decode($allowedRaw, true);
    if (is_array($allowedIds) && !in_array($userId, $allowedIds, false)) {
        pwaSendError('Доступ к приложению не предоставлен. Обратитесь к администратору.', 403);
    }
}
