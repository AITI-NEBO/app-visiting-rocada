<?php
/**
 * Контроллер загрузки файлов
 * rocada.visits / lib/api/visits_files.php
 *
 * POST /api/visits/{id}/files
 * multipart/form-data: files[] + type=photo|voice|document
 */

use Bitrix\Crm\DealTable;
use Bitrix\Crm\Timeline\CommentEntry;

function handleVisitsFiles(array $params): void
{
    if ($params['method'] !== 'POST') {
        pwaSendError('Method Not Allowed', 405);
    }

    $userId = requireAuth();
    $dealId = $params['id'];

    if (!$dealId) {
        pwaSendError('ID визита обязателен', 422);
    }

    $deal = DealTable::getList([
        'filter' => ['=ID' => $dealId, '=ASSIGNED_BY_ID' => $userId],
        'select' => ['ID', 'TITLE'],
    ])->fetch();

    if (!$deal) {
        pwaSendError('Визит не найден или нет доступа', 404);
    }

    $raw = $_FILES['files'] ?? null;
    if (empty($raw) || empty($raw['tmp_name'])) {
        pwaSendError('Файлы не переданы (поле files[])', 422);
    }

    $files = normalizeFilesArr($raw);
    $type  = $params['get']['type'] ?? $params['body']['type'] ?? 'photo';
    $allowed = allowedMimes($type);
    $maxSize = 20 * 1024 * 1024; // 20 МБ

    $savedIds = [];
    foreach ($files as $f) {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($f['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            pwaSendError("Недопустимый тип файла: $mime", 422);
        }
        if ($f['size'] > $maxSize) {
            pwaSendError('Файл > 20 МБ: ' . $f['name'], 422);
        }
        $arr    = \CFile::MakeFileArray($f['tmp_name'], $f['name'], $mime);
        $fileId = \CFile::SaveFile($arr, 'crm/deal');
        if ($fileId) {
            $savedIds[] = $fileId;
        }
    }

    if (empty($savedIds)) {
        pwaSendError('Ни один файл не сохранён', 500);
    }

    $typeLabel = match ($type) {
        'photo'    => 'Фото',
        'voice'    => 'Голосовая заметка',
        'document' => 'Документ',
        default    => 'Файл',
    };

    CommentEntry::create([
        'TEXT'      => "$typeLabel из PWA (" . count($savedIds) . ' шт.) — ' . $deal['TITLE'],
        'BINDINGS'  => [['ENTITY_TYPE_ID' => \CCrmOwnerType::Deal, 'ENTITY_ID' => $dealId]],
        'AUTHOR_ID' => $userId,
        'FILES'     => array_map(fn($id) => ['FILE_ID' => $id], $savedIds),
    ]);

    pwaSendJson([
        'deal_id'  => $dealId,
        'file_ids' => $savedIds,
        'count'    => count($savedIds),
        'message'  => 'Файлы загружены',
    ], 201);
}

function normalizeFilesArr(array $f): array
{
    if (!is_array($f['name'])) {
        return [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $name) {
        $out[] = [
            'name'     => $name,
            'type'     => $f['type'][$i],
            'tmp_name' => $f['tmp_name'][$i],
            'error'    => $f['error'][$i],
            'size'     => $f['size'][$i],
        ];
    }
    return $out;
}

function allowedMimes(string $type): array
{
    return match ($type) {
        'photo'    => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic'],
        'voice'    => ['audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/webm', 'audio/wav'],
        'document' => ['application/pdf', 'application/msword',
                       'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                       'application/vnd.ms-excel',
                       'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        default    => ['image/jpeg', 'image/png', 'audio/ogg', 'application/pdf'],
    };
}
