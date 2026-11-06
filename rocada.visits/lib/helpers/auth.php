<?php
/**
 * Авторизация через B24 access_token
 * rocada.visits / lib/helpers/auth.php
 *
 * PWA отправляет: Authorization: Bearer <b24_access_token>
 * Мы валидируем токен через REST API и получаем user_id.
 */

use Bitrix\Main\Config\Option;

// Глобальное хранение access_token текущего пользователя
// Нужно для REST-запросов от имени пользователя (crm.deal.update и т.д.)
$GLOBALS['pwa_user_access_token'] = null;

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
        // Сохраняем токен для последующих REST-запросов от имени пользователя
        $GLOBALS['pwa_user_access_token'] = $token;

        // Глобальная проверка доступа к PWA (вкладка "Доступ" в настройках)
        $globalAllowed = json_decode(Option::get('rocada.visits', 'pwa_allowed_users', '[]'), true) ?? [];
        $globalAllowed = array_map('intval', $globalAllowed);
        if (!empty($globalAllowed) && !in_array($userId, $globalAllowed, true)) {
            pwaSendError('Доступ к приложению запрещён', 403);
        }
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

/**
 * Обновление сделки через REST API от имени авторизованного пользователя.
 * Использует crm.deal.update с access_token пользователя,
 * чтобы Битрикс24 фиксировал изменение в истории как действие этого пользователя.
 *
 * @param int   $dealId  ID сделки
 * @param array $fields  Поля для обновления (например ['STAGE_ID' => 'C1:WON'])
 * @return bool          true при успехе
 * @throws \RuntimeException при ошибке REST API
 */
function pwaUpdateDealViaRest(int $dealId, array $fields): bool
{
    return !empty(pwaCallRestAsUser('crm.deal.update', [
        'id'     => $dealId,
        'fields' => $fields,
    ]));
}

/**
 * Вызов метода REST API от имени авторизованного пользователя.
 *
 * @param string $method  Метод REST, например 'crm.timeline.comment.add'
 * @param array  $params  Параметры метода
 * @return mixed          Значение result из ответа
 * @throws \RuntimeException при ошибке REST API
 */
function pwaCallRestAsUser(string $method, array $params)
{
    $token = $GLOBALS['pwa_user_access_token'] ?? null;
    if (empty($token)) {
        throw new \RuntimeException("$method: access_token пользователя не найден. Вызовите requireAuth() перед этой функцией.");
    }

    $b24Base = Option::get('rocada.visits', 'b24_base_url', '');
    if (empty($b24Base)) {
        $b24Base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                 . '://' . $_SERVER['HTTP_HOST'];
    }

    $url = rtrim($b24Base, '/') . '/rest/' . $method . '.json';

    $postData = http_build_query(['auth' => $token] + $params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_RETURNTRANSFER => true,
        // Запросы с фото (base64) тяжелее обычных — даём больше времени
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!empty($curlError)) {
        throw new \RuntimeException('REST API curl error: ' . $curlError);
    }

    $data = json_decode($body, true);

    if ($httpCode !== 200 || !is_array($data)) {
        throw new \RuntimeException("REST API $method ошибка (HTTP $httpCode): " . ($body ?: 'пустой ответ'));
    }

    if (isset($data['error'])) {
        $errMsg = $data['error_description'] ?? $data['error'];
        throw new \RuntimeException("REST API $method: " . $errMsg);
    }

    return $data['result'] ?? null;
}
