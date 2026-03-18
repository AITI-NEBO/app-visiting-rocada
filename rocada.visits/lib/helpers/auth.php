<?php
/**
 * Авторизация через B24 access_token
 * rocada.visits / lib/helpers/auth.php
 *
 * PWA отправляет: Authorization: Bearer <b24_access_token>
 * Мы валидируем токен через REST API и получаем user_id.
 */

use Bitrix\Main\Config\Option;

/**
 * Middleware: читает Authorization: Bearer <token>,
 * проверяет его через B24 REST API, возвращает user_id.
 * При ошибке — 401 + exit.
 */
function requireAuth(): int
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($header) && function_exists('getallheaders')) {
        $all    = getallheaders();
        $header = $all['Authorization'] ?? $all['authorization'] ?? '';
    }

    if (empty($header) || !str_starts_with($header, 'Bearer ')) {
        pwaSendError('Unauthorized: missing or malformed token', 401);
    }

    $token = trim(substr($header, 7));
    if (empty($token)) {
        pwaSendError('Unauthorized: empty token', 401);
    }

    // Проверяем: может быть B24 access_token → валидируем через REST API
    $userId = validateB24Token($token);
    if ($userId) {
        return $userId;
    }

    pwaSendError('Unauthorized: invalid or expired token', 401);
    return 0; // never reached
}

/**
 * Валидация B24 access_token через REST API user.current
 * Работает для on-premise: вызывает REST API на этом же портале
 */
function validateB24Token(string $token): ?int
{
    $b24Base = Option::get('rocada.visits', 'b24_base_url', '');
    if (empty($b24Base)) {
        // Определяем адрес портала автоматически
        $b24Base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                 . '://' . $_SERVER['HTTP_HOST'];
    }

    $url = rtrim($b24Base, '/') . '/rest/user.current.json?auth=' . urlencode($token);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || empty($body)) {
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['result']['ID'])) {
        return null;
    }

    return (int)$data['result']['ID'];
}
