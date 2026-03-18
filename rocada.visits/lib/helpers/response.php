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
