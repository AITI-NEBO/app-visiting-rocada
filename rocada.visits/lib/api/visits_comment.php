<?php
/**
 * Контроллер комментариев
 * rocada.visits / lib/api/visits_comment.php
 *
 * GET  /api/visits/{id}/comments — список комментариев из Timeline
 * POST /api/visits/{id}/comment  — добавить комментарий
 * Body: { "text": "..." }
 */

use Bitrix\Crm\DealTable;
use Bitrix\Crm\Timeline\CommentEntry;
use Bitrix\Main\UserTable;

function handleVisitsComment(array $params): void
{
    $userId = requireAuth();
    $dealId = $params['id'];
    $method = $params['method'];

    if (!$dealId) {
        pwaSendError('ID визита обязателен', 422);
    }

    // Проверка доступа
    $deal = DealTable::getList([
        'filter' => ['=ID' => $dealId, '=ASSIGNED_BY_ID' => $userId],
        'select' => ['ID'],
    ])->fetch();

    if (!$deal) {
        pwaSendError('Визит не найден или нет доступа', 404);
    }

    if ($method === 'GET') {
        getComments($dealId);
    } elseif ($method === 'POST') {
        addComment($dealId, $userId, $params['body']);
    } else {
        pwaSendError('Method Not Allowed', 405);
    }
}

function getComments(int $dealId): void
{
    // Читаем из Timeline (комментарии к сделке)
    $db = \Bitrix\Crm\Timeline\TimelineTable::getList([
        'filter' => [
            'ASSOCIATED_ENTITY_TYPE_ID' => \CCrmOwnerType::Deal,
            'ASSOCIATED_ENTITY_ID'      => $dealId,
            'TYPE_ID'                   => 7,  // Comment type
        ],
        'select' => ['ID', 'CREATED', 'AUTHOR_ID', 'COMMENT'],
        'order'  => ['CREATED' => 'DESC'],
        'limit'  => 50,
    ])->fetchAll();

    $comments = [];
    foreach ($db as $row) {
        $authorName = '';
        if (!empty($row['AUTHOR_ID'])) {
            $a = UserTable::getList([
                'filter' => ['=ID' => $row['AUTHOR_ID']],
                'select' => ['NAME', 'LAST_NAME'],
            ])->fetch();
            if ($a) {
                $authorName = trim(($a['NAME'] ?? '') . ' ' . ($a['LAST_NAME'] ?? ''));
            }
        }
        $comments[] = [
            'id'          => (int)$row['ID'],
            'text'        => strip_tags($row['COMMENT'] ?? ''),
            'author_id'   => (int)($row['AUTHOR_ID'] ?? 0),
            'author_name' => $authorName,
            'created_at'  => $row['CREATED'] instanceof \Bitrix\Main\Type\DateTime
                ? $row['CREATED']->format('Y-m-d H:i:s')
                : (string)($row['CREATED'] ?? ''),
        ];
    }
    pwaSendJson($comments);
}


function addComment(int $dealId, int $userId, array $body): void
{
    $text = trim($body['text'] ?? '');
    if (empty($text)) {
        pwaSendError('Текст комментария обязателен', 422);
    }

    $commentId = CommentEntry::create([
        'TEXT'      => $text,
        'BINDINGS'  => [['ENTITY_TYPE_ID' => \CCrmOwnerType::Deal, 'ENTITY_ID' => $dealId]],
        'AUTHOR_ID' => $userId,
    ]);

    $commentId
        ? pwaSendJson(['id' => $commentId, 'message' => 'Комментарий добавлен'], 201)
        : pwaSendError('Ошибка создания комментария', 500);
}
