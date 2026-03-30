<?php
/**
 * JSON response helpers for rocada.visits
 * Префикс pwa_ чтобы не конфликтовать с Bitrix sendError()
 */

function pwaSendJson($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(
        ['success' => true, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function pwaSendError(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(
        ['success' => false, 'error' => $message],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

/**
 * Читает и декодирует тело JSON-запроса (php://input)
 */
function pwaGetJsonData(): array
{
    static $cached = null;
    if ($cached !== null) return $cached;
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    $cached = is_array($decoded) ? $decoded : [];
    return $cached;
}
