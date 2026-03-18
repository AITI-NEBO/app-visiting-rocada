// src/data/utils.js
// Утилитарные функции для отображения визитов (не зависят от API)

export function getStatusLabel(status) {
    const labels = {
        planned: 'Запланирован',
        in_progress: 'В процессе',
        completed: 'Завершён',
        cancelled: 'Отменён'
    }
    return labels[status] || status
}

export function getStatusColor(status) {
    const colors = {
        planned: 'var(--color-info)',
        in_progress: 'var(--color-warning)',
        completed: 'var(--color-success)',
        cancelled: 'var(--color-danger)'
    }
    return colors[status] || 'var(--color-text-secondary)'
}

export function getVisitTypeLabel(type) {
    const labels = {
        order: 'Сбор заказа',
        presentation: 'Презентация',
        consultation: 'Консультация',
        new_client: 'Новый клиент'
    }
    return labels[type] || type
}

export function getVisitTypeIcon(type) {
    const icons = {
        order: 'shopping_cart',
        presentation: 'slideshow',
        consultation: 'support_agent',
        new_client: 'person_add'
    }
    return icons[type] || 'event'
}

export function getResultStatusLabel(status) {
    const labels = {
        order_placed: 'Заказ оформлен',
        callback: 'Перезвонить позже',
        presentation_done: 'Презентация проведена',
        needs_approval: 'На согласовании',
        declined: 'Отказ',
        rescheduled: 'Перенесён'
    }
    return labels[status] || status
}

export const resultStatusOptions = [
    { value: 'order_placed', label: 'Заказ оформлен', icon: 'shopping_cart', color: 'var(--color-success)' },
    { value: 'callback', label: 'Перезвонить позже', icon: 'phone_callback', color: 'var(--color-warning)' },
    { value: 'presentation_done', label: 'Презентация проведена', icon: 'slideshow', color: 'var(--color-primary)' },
    { value: 'needs_approval', label: 'На согласовании', icon: 'pending', color: 'var(--color-info)' },
    { value: 'declined', label: 'Отказ', icon: 'cancel', color: 'var(--color-danger)' },
    { value: 'rescheduled', label: 'Визит перенесён', icon: 'event_repeat', color: 'var(--color-text-secondary)' }
]

export function formatCurrency(amount) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount || 0)
}

/**
 * Парсит Bitrix BBCode → HTML
 * Поддержка: [b], [i], [u], [s], [p], [url], [img], [list], [*], [quote], [code], [color], [size]
 */
export function parseBBCode(text) {
    if (!text) return ''

    let html = text
        // Экранируем HTML
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')

    // Форматирование текста
    html = html.replace(/\[b\]([\s\S]*?)\[\/b\]/gi, '<strong>$1</strong>')
    html = html.replace(/\[i\]([\s\S]*?)\[\/i\]/gi, '<em>$1</em>')
    html = html.replace(/\[u\]([\s\S]*?)\[\/u\]/gi, '<u>$1</u>')
    html = html.replace(/\[s\]([\s\S]*?)\[\/s\]/gi, '<s>$1</s>')

    // Параграфы
    html = html.replace(/\[p\]([\s\S]*?)\[\/p\]/gi, '<p>$1</p>')

    // Ссылки: [url=...]...[/url] и [url]...[/url]
    html = html.replace(/\[url=([^\]]+)\]([\s\S]*?)\[\/url\]/gi, '<a href="$1" target="_blank" rel="noopener">$2</a>')
    html = html.replace(/\[url\]([\s\S]*?)\[\/url\]/gi, '<a href="$1" target="_blank" rel="noopener">$1</a>')

    // Картинки
    html = html.replace(/\[img\]([\s\S]*?)\[\/img\]/gi, '<img src="$1" style="max-width:100%;border-radius:8px;margin:4px 0" />')

    // Цитаты
    html = html.replace(/\[quote\]([\s\S]*?)\[\/quote\]/gi, '<blockquote style="border-left:3px solid var(--color-primary);padding:4px 12px;margin:4px 0;color:var(--color-text-secondary)">$1</blockquote>')

    // Код
    html = html.replace(/\[code\]([\s\S]*?)\[\/code\]/gi, '<code style="background:var(--color-bg-elevated);padding:2px 6px;border-radius:4px;font-size:0.85em">$1</code>')

    // Цвет: [color=red]...[/color]
    html = html.replace(/\[color=([^\]]+)\]([\s\S]*?)\[\/color\]/gi, '<span style="color:$1">$2</span>')

    // Размер: [size=N]...[/size]
    html = html.replace(/\[size=(\d+)\]([\s\S]*?)\[\/size\]/gi, '<span style="font-size:$1px">$2</span>')

    // Списки
    html = html.replace(/\[list\]([\s\S]*?)\[\/list\]/gi, (_, content) => {
        const items = content.replace(/\[\*\]/g, '</li><li>').replace(/^<\/li>/, '')
        return '<ul style="padding-left:20px;margin:4px 0">' + items + '</li></ul>'
    })

    // Переносы строк → <br>
    html = html.replace(/\n/g, '<br>')

    return html
}
