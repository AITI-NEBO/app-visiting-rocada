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
 *   2. EXIF, прочитанный в приложении (например HEIC, который сервер не читает);
 *   3. для снимка камерой — время и геолокация телефона в момент съёмки
 *      (браузеры телефонов обычно вырезают GPS из фото).
 * Если есть и метаданные фото, и данные телефона — они сверяются между собой.
 */

use Bitrix\Main\Loader;

const PWA_VISIT_PHOTO_MAX_SIZE      = 20 * 1024 * 1024; // 20 МБ
const PWA_VISIT_PHOTO_DEFAULT_RANGE = 100;              // метров
const PWA_VISIT_PHOTO_DEFAULT_TIME_DIFF = 120;          // минут
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
 * Запланированные дата и время визита из поля сделки.
 * Возвращает ['at' => DateTime, 'has_time' => bool] или null, если дата не указана.
 * Время 00:00 считаем «время не указано» — визит назначен на день.
 */
function pwaVisitPlannedAt($value): ?array
{
    $tz = new \DateTimeZone(date_default_timezone_get());
    if ($value instanceof \Bitrix\Main\Type\DateTime) {
        $at = (new \DateTime('@' . $value->getTimestamp()))->setTimezone($tz);
    } elseif ($value instanceof \Bitrix\Main\Type\Date) {
        $at = new \DateTime($value->format('Y-m-d') . ' 00:00:00', $tz);
    } elseif (is_string($value) && trim($value) !== '' && ($ts = strtotime($value)) !== false) {
        $at = (new \DateTime('@' . $ts))->setTimezone($tz);
    } else {
        return null;
    }
    return ['at' => $at, 'has_time' => $at->format('H:i') !== '00:00'];
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
    $result = ['taken_at' => null, 'has_offset' => false, 'lat' => null, 'lng' => null];
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
        // Старые версии PHP не знают тегов смещения и отдают их как UndefinedTag
        $offset = $exif['OffsetTimeOriginal'] ?? $exif['UndefinedTag:0x9011']
            ?? $exif['OffsetTime'] ?? $exif['UndefinedTag:0x9010'] ?? '';
        $hasOffset = (bool)preg_match('/^[+-]\d{2}:\d{2}$/', (string)$offset);
        $dt = $hasOffset
            ? \DateTime::createFromFormat('Y:m:d H:i:sP', $raw . $offset)
            : \DateTime::createFromFormat('Y:m:d H:i:s', $raw);
        if ($dt) {
            $result['taken_at']   = $dt;
            $result['has_offset'] = $hasOffset;
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
function pwaCheckVisitPhoto(
    string $path,
    array $clientMeta,
    ?array $point,
    int $maxDistance,
    ?array $planned = null,
    int $maxTimeDiffMin = PWA_VISIT_PHOTO_DEFAULT_TIME_DIFF
): array {
    $tz     = new \DateTimeZone(date_default_timezone_get());
    $source = ($clientMeta['source'] ?? '') === 'camera' ? 'camera' : 'gallery';
    $exif   = pwaReadPhotoExif($path);

    $parseDate = static function ($v) use ($tz): ?\DateTime {
        if (empty($v)) {
            return null;
        }
        try {
            return (new \DateTime((string)$v))->setTimezone($tz);
        } catch (\Exception $e) {
            return null;
        }
    };
    $coords = static function ($lat, $lng): ?array {
        if (!is_numeric($lat) || !is_numeric($lng) || ((float)$lat == 0 && (float)$lng == 0)) {
            return null;
        }
        return ['lat' => (float)$lat, 'lng' => (float)$lng];
    };

    // Метаданные самого фото: EXIF на сервере → EXIF, прочитанный в приложении (например HEIC).
    // Дату без часового пояса приложение читает по времени телефона, а сервер — по Москве,
    // поэтому в этом случае точнее дата из приложения.
    $clientExifDate = $parseDate($clientMeta['exif_taken_at'] ?? null);
    $exifDate = $exif['taken_at'] && ($exif['has_offset'] || !$clientExifDate)
        ? $exif['taken_at']->setTimezone($tz)
        : $clientExifDate;
    $exifGeo  = $coords($exif['lat'], $exif['lng'])
        ?? $coords($clientMeta['exif_lat'] ?? null, $clientMeta['exif_lng'] ?? null);

    // Данные телефона в момент съёмки (только для снимка камерой в приложении)
    $deviceDate = $source === 'camera'
        ? $parseDate($clientMeta['device_taken_at'] ?? ($clientMeta['taken_at'] ?? null))
        : null;
    $deviceGeo  = null;
    $geoSource  = null;
    if ($source === 'camera') {
        $deviceGeo = $coords($clientMeta['device_lat'] ?? null, $clientMeta['device_lng'] ?? null);
        if ($deviceGeo) {
            $geoSource = 'device';
        } elseif (($clientMeta['geo_source'] ?? '') === 'checkin') {
            $deviceGeo = $coords($clientMeta['lat'] ?? null, $clientMeta['lng'] ?? null);
            $geoSource = $deviceGeo ? 'checkin' : null;
        }
    }
    $accuracy = is_numeric($clientMeta['accuracy'] ?? null) ? (int)round((float)$clientMeta['accuracy']) : null;

    // Приоритет — метаданные фото, их нельзя подменить из приложения
    $takenAt = $exifDate ?? $deviceDate;
    $geo     = $exifGeo ?? $deviceGeo;
    if ($exifGeo) {
        $geoSource = 'exif';
    }

    $reasons  = [];
    $distance = null;

    // Дата и время съёмки сверяются с запланированными датой и временем визита.
    // Если у визита есть время — допускается отклонение ±$maxTimeDiffMin минут,
    // если только дата (или отклонение 0) — фото должно быть снято в этот день.
    // Если дата визита не заполнена — фото должно быть снято сегодня.
    if (!$takenAt) {
        $reasons[] = 'нет даты съёмки';
    } elseif ($planned && $planned['has_time'] && $maxTimeDiffMin > 0) {
        $diffMin = (int)round(abs($takenAt->getTimestamp() - $planned['at']->getTimestamp()) / 60);
        if ($diffMin > $maxTimeDiffMin) {
            $reasons[] = 'фото снято ' . $takenAt->format('d.m.Y H:i')
                . ', а визит запланирован на ' . $planned['at']->format('d.m.Y H:i')
                . ' (разница ' . pwaFormatMinutes($diffMin) . ', допустимо ±' . pwaFormatMinutes($maxTimeDiffMin) . ')';
        }
    } else {
        $visitDay = $planned ? $planned['at'] : new \DateTime('now', $tz);
        if ($takenAt->format('Y-m-d') !== $visitDay->format('Y-m-d')) {
            $reasons[] = 'фото снято ' . $takenAt->format('d.m.Y') . ', а визит '
                . ($planned ? 'запланирован на ' : 'проводится ') . $visitDay->format('d.m.Y');
        }
    }

    // Снимок камерой: дата в метаданных должна совпадать со временем съёмки в приложении.
    // Сверяем только то, что прочитано по одним часам и в одном часовом поясе:
    // EXIF из приложения или EXIF с явным смещением — иначе получим ложную разницу в часы.
    $exifDateForCompare = $clientExifDate ?? ($exif['has_offset'] ? $exifDate : null);
    if ($exifDateForCompare && $deviceDate
        && abs($exifDateForCompare->getTimestamp() - $deviceDate->getTimestamp()) > 15 * 60) {
        $reasons[] = 'дата в метаданных фото (' . $exifDateForCompare->format('d.m.Y H:i')
            . ') не совпадает со временем съёмки в приложении (' . $deviceDate->format('d.m.Y H:i') . ')';
    }

    if (!$geo) {
        $geoError  = mb_substr(trim((string)($clientMeta['geo_error'] ?? '')), 0, 150);
        $reasons[] = 'нет геолокации съёмки' . ($geoError !== '' ? " ($geoError)" : '');
    } elseif (!$point) {
        $reasons[] = 'у точки визита не заполнены координаты';
    } else {
        $distance = pwaDistanceMeters($geo['lat'], $geo['lng'], $point['lat'], $point['lng']);
        if ($distance > $maxDistance) {
            $reasons[] = "фото снято в {$distance} м от точки визита (допустимо {$maxDistance} м)";
        }
    }

    // Снимок камерой: место в метаданных фото должно совпадать с геолокацией телефона
    if ($exifGeo && $deviceGeo && $geoSource === 'exif') {
        $gap = pwaDistanceMeters($exifGeo['lat'], $exifGeo['lng'], $deviceGeo['lat'], $deviceGeo['lng']);
        if ($gap > $maxDistance) {
            $reasons[] = "место в метаданных фото и геолокация телефона расходятся на {$gap} м";
        }
    }

    return [
        'ok'         => empty($reasons),
        'reasons'    => $reasons,
        'source'     => $source,
        'taken_at'   => $takenAt,
        'lat'        => $geo['lat'] ?? null,
        'lng'        => $geo['lng'] ?? null,
        'geo_source' => $geoSource,
        'accuracy'   => $geoSource === 'device' ? $accuracy : null,
        'has_exif'   => $exifDate !== null || $exifGeo !== null,
        'distance_m' => $distance,
    ];
}

function pwaFormatMinutes(int $min): string
{
    if ($min < 60) {
        return "$min мин";
    }
    $h = intdiv($min, 60);
    $m = $min % 60;
    return $m ? "$h ч $m мин" : "$h ч";
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
    $geoSources = [
        'exif'    => 'по метаданным фото',
        'device'  => 'по геолокации телефона в момент съёмки',
        'checkin' => 'по отметке на шаге геолокации',
    ];
    if (!empty($check['geo_source'])) {
        $lines[] = 'Место съёмки: ' . $geoSources[$check['geo_source']]
            . (!empty($check['accuracy']) ? " (точность ±{$check['accuracy']} м)" : '');
    }
    if (!$check['has_exif']) {
        $lines[] = 'Метаданных в фото нет';
    }
    if ($check['distance_m'] !== null) {
        $lines[] = "Расстояние до точки: {$check['distance_m']} м";
    }
    $lines[] = $check['ok']
        ? '✅ Дата и геолокация фото подтверждены'
        : '⚠️ Неверная геолокация: ' . implode('; ', $check['reasons']);
    return implode("\n", $lines);
}
