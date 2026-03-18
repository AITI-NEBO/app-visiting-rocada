<?php
/**
 * Общие CRM-хелперы
 * rocada.visits / lib/helpers/crm.php
 * Префикс pwa чтобы не конфликтовать с Bitrix
 */

/**
 * Извлекает первое значение из CRM multi-field (телефон, email и т.д.)
 */
function pwaExtractFirstPhone(array $items): string
{
    foreach ($items as $item) {
        if (!empty($item['VALUE'])) {
            return $item['VALUE'];
        }
    }
    return '';
}

/**
 * Форматирует дату Bitrix → строку Y-m-d
 */
function pwaFormatDate($date): ?string
{
    if ($date instanceof \Bitrix\Main\Type\Date || $date instanceof \Bitrix\Main\Type\DateTime) {
        return $date->format('Y-m-d');
    }
    return $date ? (string)$date : null;
}

/**
 * Форматирует дату+время Bitrix → ISO 8601 с часовым поясом сервера
 * Пример: 2026-03-06T15:00:00+03:00
 * На фронте new Date() автоматически переведёт в локальный пояс
 */
function pwaFormatDateTime($date): ?string
{
    if ($date instanceof \Bitrix\Main\Type\DateTime) {
        return $date->format('c'); // ISO 8601: 2026-03-06T15:00:00+03:00
    }
    if ($date instanceof \Bitrix\Main\Type\Date) {
        return $date->format('Y-m-d') . 'T00:00:00' . date('P');
    }
    if (is_string($date) && !empty($date)) {
        // Попытка распарсить строку и вернуть ISO
        $ts = strtotime($date);
        return $ts ? date('c', $ts) : $date;
    }
    return null;
}
