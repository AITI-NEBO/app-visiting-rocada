<?php
/**
 * Контроллер профиля
 * rocada.visits / lib/api/user.php
 *
 * GET /api/user/me
 */

function handleUser(array $params): void
{
    if ($params['action'] !== 'me' || $params['method'] !== 'GET') {
        pwaSendError('Not Found', 404);
    }
    $userId = requireAuth();
    $user   = getUserById($userId);
    $user ? pwaSendJson($user) : pwaSendError('Пользователь не найден', 404);
}
