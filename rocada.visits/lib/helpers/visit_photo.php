<?php
/**
 * Проверка фото входной группы при завершении визита
 * rocada.visits / lib/helpers/visit_photo.php
 *
 * Фото подтверждает визит, если:
 *   - дата съёмки совпадает с датой проведения визита (сегодня);
 *   - место съёмки не дальше допустимого расстояния от точки визита.
 *
 * Источники даты и места съёмки (по приоритету):
 *   1. EXIF файла, прочитанный на сервере (если есть расширение exif);
 *   2. данные из приложения: EXIF, прочитанный на телефоне (например HEIC),
 *      а для снимка камерой — время и геолокация телефона в момент съёмки.
 */

use Bitrix\Main\Loader;

const PWA_VISIT_PHOTO_MAX_SIZE      = 20 * 1024 * 1024; // 20 МБ
const PWA_VISIT_PHOTO_DEFAULT_RANGE = 100;              // метров
const PWA_UNLOAD_POINTS_IBLOCK_ID   = 206;

/**
 * Проверяет загруженный файл фото, возвращает текст ошибки или null.
 */
function pwaValidateVisitPhoto(?array $file): ?string
{
    if (empty($file) || empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return 'Без фото входной группы визит завершить нельзя';
    }
    if ($file['size'] > PWA_VISIT_PHOTO_MAX_SIZE) {
        return 'Фото больше 20 МБ';
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'], true)) {
        return "Недопустимый тип файла: $mime";
    }
    return null;
}

/**
 * Координаты точки визита (пункт разгрузки из инфоблока 206).
 */
function pwaGetUnloadPointCoords(int $pointId): ?array
{
    if ($pointId <= 0 || !Loader::includeModule('iblock')) {
        return null;
    }
    $el = \CIBlockElement::GetList(
        [],
        ['ID' => $pointId, 'IBLOCK_ID' => PWA_UNLOAD_POINTS_IBLOCK_ID],
        false,
        false,
        ['ID', 'PROPERTY_LATITUDE', 'PROPERTY_LONGITUDE']
    )->Fetch();

    $lat = trim((string)($el['PROPERTY_LATITUDE_VALUE'] ?? ''));
    $lng = trim((string)($el['PROPERTY_LONGITUDE_VALUE'] ?? ''));
    if ($lat === '' || $lng === '' || !is_numeric($lat) || !is_numeric($lng)) {
        return null;
    }
    return ['lat' => (float)$lat, 'lng' => (float)$lng];
}

/**
 * Расстояние между двумя точками в метрах (формула гаверсинусов).
 */
function pwaDistanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): int
{
    $r    = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return (int)round($r * 2 * asin(min(1, sqrt($a))));
}

/**
 * Дата и координаты съёмки из EXIF файла (чтение на сервере).
 * Возвращает ['taken_at' => ?DateTime, 'lat' => ?float, 'lng' => ?float].
 */
function pwaReadPhotoExif(string $path): array
{
    $result = ['taken_at' => null, 'lat' => null, 'lng' => null];
    if (!function_exists('exif_read_data')) {
        return $result;
    }
    $exif = @exif_read_data($path);
    if (!is_array($exif)) {
        return $result;
    }

    $raw = $exif['DateTimeOriginal'] ?? $exif['DateTimeDigitized'] ?? null;
    if (is_string($raw) && $raw !== '') {
        // EXIF хранит локальное время телефона; смещение (если есть) — отдельным тегом
        $offset = $exif['OffsetTimeOriginal'] ?? $exif['OffsetTime'] ?? '';
        $dt = preg_match('/^[+-]\d{2}:\d{2}$/', (string)$offset)
            ? \DateTime::createFromFormat('Y:m:d H:i:sP', $raw . $offset)
            : \DateTime::createFromFormat('Y:m:d H:i:s', $raw);
        if ($dt) {
            $result['taken_at'] = $dt;
        }
    }

    if (!empty($exif['GPSLatitude']) && !empty($exif['GPSLongitude'])) {
        $lat = pwaExifGpsToDecimal($exif['GPSLatitude'], $exif['GPSLatitudeRef'] ?? 'N');
        $lng = pwaExifGpsToDecimal($exif['GPSLongitude'], $exif['GPSLongitudeRef'] ?? 'E');
        if ($lat !== null && $lng !== null && !($lat == 0 && $lng == 0)) {
            $result['lat'] = $lat;
            $result['lng'] = $lng;
        }
    }

    return $result;
}

function pwaExifGpsToDecimal($parts, string $ref): ?float
{
    if (!is_array($parts) || count($parts) < 3) {
        return null;
    }
    $toFloat = static function ($v): ?float {
        if (is_numeric($v)) {
            return (float)$v;
        }
        if (is_string($v) && preg_match('#^(-?\d+(?:\.\d+)?)/(\d+(?:\.\d+)?)$#', $v, $m)) {
            return (float)$m[2] == 0.0 ? null : (float)$m[1] / (float)$m[2];
        }
        return null;
    };
    [$d, $m, $s] = array_map($toFloat, array_slice(array_values($parts), 0, 3));
    if ($d === null || $m === null || $s === null) {
        return null;
    }
    $val = $d + $m / 60 + $s / 3600;
    return in_array(strtoupper($ref), ['S', 'W'], true) ? -$val : $val;
}

/**
 * Проверка фото визита.
 *
 * @param string     $path        Путь к загруженному файлу
 * @param array      $clientMeta  Данные из приложения: source (camera|gallery), taken_at (ISO 8601), lat, lng
 * @param array|null $point       Координаты точки визита ['lat','lng'] или null
 * @param int        $maxDistance Допустимое расстояние, м
 * @return array [
 *   'ok'         => bool,      true — дата и место подтверждены
 *   'reasons'    => string[],  почему не подтверждено
 *   'source'     => string,    camera|gallery
 *   'taken_at'   => ?DateTime,
 *   'lat', 'lng' => ?float,
 *   'distance_m' => ?int,
 * ]
 */
function pwaCheckVisitPhoto(string $path, array $clientMeta, ?array $point, int $maxDistance): array
{
    $tz     = new \DateTimeZone(date_default_timezone_get());
    $source = ($clientMeta['source'] ?? '') === 'camera' ? 'camera' : 'gallery';
    $exif   = pwaReadPhotoExif($path);

    // Дата съёмки: EXIF на сервере → данные приложения
    $takenAt = $exif['taken_at'];
    if (!$takenAt && !empty($clientMeta['taken_at'])) {
        try {
            $takenAt = new \DateTime((string)$clientMeta['taken_at']);
        } catch (\Exception $e) {
            $takenAt = null;
        }
    }
    if ($takenAt) {
        $takenAt->setTimezone($tz);
    }

    // Место съёмки: EXIF на сервере → данные приложения
    $lat = $exif['lat'];
    $lng = $exif['lng'];
    if ($lat === null && isset($clientMeta['lat'], $clientMeta['lng'])
        && is_numeric($clientMeta['lat']) && is_numeric($clientMeta['lng'])) {
        $lat = (float)$clientMeta['lat'];
        $lng = (float)$clientMeta['lng'];
    }

    $reasons  = [];
    $distance = null;

    $today = (new \DateTime('now', $tz))->format('Y-m-d');
    if (!$takenAt) {
        $reasons[] = 'нет даты съёмки';
    } elseif ($takenAt->format('Y-m-d') !== $today) {
        $reasons[] = 'фото снято ' . $takenAt->format('d.m.Y') . ', а визит проводится ' . date('d.m.Y');
    }

    if ($lat === null || $lng === null) {
        $reasons[] = 'нет геолокации съёмки';
    } elseif (!$point) {
        $reasons[] = 'у точки визита не заполнены координаты';
    } else {
        $distance = pwaDistanceMeters($lat, $lng, $point['lat'], $point['lng']);
        if ($distance > $maxDistance) {
            $reasons[] = "фото снято в {$distance} м от точки визита (допустимо {$maxDistance} м)";
        }
    }

    return [
        'ok'         => empty($reasons),
        'reasons'    => $reasons,
        'source'     => $source,
        'taken_at'   => $takenAt,
        'lat'        => $lat,
        'lng'        => $lng,
        'distance_m' => $distance,
    ];
}

/**
 * Фото в формате REST для файлов: [имя, содержимое в base64].
 */
function pwaVisitPhotoRestFile(array $file, int $dealId): array
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext  = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'image/heic' => 'heic', 'image/heif' => 'heif',
    ][$mime] ?? 'jpg';

    return [
        "visit_{$dealId}_" . date('Ymd_His') . ".$ext",
        base64_encode(file_get_contents($file['tmp_name'])),
    ];
}

/**
 * Значение файлового UF-поля для crm.deal.update.
 * Множественное поле: новое фото добавляется к уже загруженным.
 */
function pwaDealFileFieldValue(string $fieldCode, array $restFile, array $currentValue): array
{
    $uf = \CUserTypeEntity::GetList([], ['ENTITY_ID' => 'CRM_DEAL', 'FIELD_NAME' => $fieldCode])->Fetch();
    if (($uf['MULTIPLE'] ?? 'N') !== 'Y') {
        return ['fileData' => $restFile];
    }

    $value = [];
    foreach (array_filter($currentValue) as $fileId) {
        $value[] = ['id' => (int)$fileId];
    }
    $value[] = ['fileData' => $restFile];
    return $value;
}

/**
 * Текст для таймлайна сделки по результату проверки фото.
 */
function pwaVisitPhotoCheckText(array $check): string
{
    $lines   = [];
    $lines[] = '📷 Фото входной группы: ' . ($check['source'] === 'camera' ? 'снято камерой в приложении' : 'загружено из галереи');
    if ($check['taken_at']) {
        $lines[] = 'Дата съёмки: ' . $check['taken_at']->format('d.m.Y H:i');
    }
    if ($check['distance_m'] !== null) {
        $lines[] = "Расстояние до точки: {$check['distance_m']} м";
    }
    $lines[] = $check['ok']
        ? '✅ Дата и геолокация фото подтверждены'
        : '⚠️ Неверная геолокация: ' . implode('; ', $check['reasons']);
    return implode("\n", $lines);
}
