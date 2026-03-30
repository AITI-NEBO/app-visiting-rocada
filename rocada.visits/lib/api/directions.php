<?php
/**
 * Контроллер направлений
 * rocada.visits / lib/api/directions.php
 *
 * GET /api/directions — список направлений доступных текущему пользователю
 */

function handleDirections(array $params): void
{
    if ($params['method'] !== 'GET') {
        pwaSendError('Method Not Allowed', 405);
    }

    $userId   = requireAuth();
    $moduleId = $params['moduleId'];

    $all  = getAllDirections($moduleId);
    $visible = array_values(array_filter($all, function ($d) use ($userId) {
        $allowed = $d['allowed_users'] ?? [];
        return empty($allowed) || in_array($userId, array_map('intval', $allowed));
    }));

    // Возвращаем публичные поля: id, name, icon, completion_type, result_statuses (без stage)
    pwaSendJson(array_map(function ($d) {
        $statuses = array_map(fn($st) => [
            'id'            => $st['id'],
            'name'          => $st['name'] ?? '',
            'color'         => $st['color'] ?? '#0066ff',
            'photo_fields'  => $st['photo_fields'] ?? [],
            'is_successful' => (bool)($st['is_successful'] ?? false),
        ], $d['result_statuses'] ?? []);

        return [
            'id'              => $d['id'],
            'name'            => $d['name'],
            'icon'            => $d['icon'] ?? 'briefcase',
            'completion_type' => $d['completion_type'] ?? 'sales',
            'result_statuses' => $statuses,
        ];
    }, $visible));
}
