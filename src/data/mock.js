// ============================================
// RocadaMed — Мок-данные торгового представителя
// ============================================

export const currentUser = {
    id: 1,
    name: 'Алексей Петров',
    initials: 'АП',
    role: 'Торговый представитель',
    region: 'Казань, Приволжский район',
    phone: '+7 (917) 265-34-89',
    email: 'a.petrov@rocadamed.ru',
    department: 'Отдел продаж',
    avatar: null,
    stats: {
        today: { visits: 6, completed: 2, orders: 3, revenue: 87500 },
        week: { visits: 28, completed: 22, orders: 15, revenue: 412000 },
        month: { visits: 112, completed: 98, orders: 64, revenue: 1850000 }
    }
}

// Визиты на сегодня
export const visits = [
    {
        id: 1,
        title: 'Стоматология "Дента Плюс"',
        client: 'Иванова Марина Сергеевна',
        clientRole: 'Главный врач',
        address: 'ул. Баумана, 42, Казань',
        phone: '+7 (843) 555-12-34',
        time: '09:00',
        timeEnd: '09:45',
        status: 'completed',
        type: 'order',
        infoReason: 'Плановый визит по графику. Клиент ранее запрашивал информацию по новым композитам GC.',
        description: 'Презентация новой линейки композитных материалов GC Essentia. Обсуждение текущего заказа расходных материалов.',
        products: [
            { name: 'GC Essentia (набор)', price: 12800 },
            { name: 'Перчатки нитриловые (10 уп.)', price: 4500 },
            { name: 'Слепочная масса Impregum', price: 15200 }
        ],
        orderAmount: 32500,
        isApproximate: true,
        lpr: {
            name: 'Иванова Марина Сергеевна',
            role: 'Главный врач / Владелец',
            phone: '+7 (843) 555-12-34',
            email: 'ivanova@dentaplus.ru',
            telegram: '@ivanova_denta'
        },
        geoSent: true,
        geoLat: 55.7887,
        geoLng: 49.1221,
        result: {
            status: 'order_placed',
            text: 'Клиент оформил заказ на 32 500 ₽. Заинтересован в образцах Elipar для тестирования. Запланировать визит через 2 недели.',
            photos: [],
            voiceNote: null,
            completedAt: '09:40'
        },
        comments: [
            { id: 1, author: 'Алексей Петров', text: 'Клиент заинтересован в GC Essentia. Оформил заказ на ~32 500 ₽. Запросили образцы Elipar.', time: '09:40' }
        ]
    },
    {
        id: 2,
        title: 'Клиника "Белая Орхидея"',
        client: 'Сафиуллин Ринат Маратович',
        clientRole: 'Владелец клиники',
        address: 'пр. Ямашева, 103, Казань',
        phone: '+7 (843) 222-45-67',
        time: '10:30',
        timeEnd: '11:15',
        status: 'completed',
        type: 'presentation',
        infoReason: 'Клиент рассматривает обновление оборудования. Назначена презентация KaVo.',
        description: 'Презентация стоматологической установки KaVo ESTETICA E50. Обсуждение условий рассрочки и сервисного обслуживания.',
        products: [
            { name: 'KaVo ESTETICA E50 (установка)', price: 1250000 },
            { name: 'Автоклав Euronda E9 Next', price: 320000 }
        ],
        orderAmount: 0,
        isApproximate: false,
        lpr: {
            name: 'Сафиуллин Ринат Маратович',
            role: 'Владелец / Генеральный директор',
            phone: '+7 (843) 222-45-67',
            email: 'safiullin@whiteorhidea.ru',
            telegram: '@rinat_safiullin'
        },
        geoSent: true,
        geoLat: 55.8204,
        geoLng: 49.1373,
        result: {
            status: 'callback',
            text: 'Провёл презентацию установки KaVo. Клиент рассматривает покупку, просит расчёт по рассрочке на 12 мес. Перезвонить через 3 дня.',
            photos: [],
            voiceNote: null,
            completedAt: '11:10'
        },
        comments: [
            { id: 1, author: 'Алексей Петров', text: 'Провёл презентацию установки KaVo. Клиент рассматривает покупку, просит расчёт по рассрочке на 12 мес.', time: '11:10' }
        ]
    },
    {
        id: 3,
        title: 'Стоматология "МедиСмайл"',
        client: 'Галлямова Айгуль Рустемовна',
        clientRole: 'Закупщик',
        address: 'ул. Декабристов, 85А, Казань',
        phone: '+7 (843) 267-89-01',
        time: '12:00',
        timeEnd: '12:30',
        status: 'in_progress',
        type: 'order',
        infoReason: 'Акция месяца: скидка 15% на пломбировочные материалы Filtek. Клиент — постоянный покупатель.',
        description: 'Сбор заказа на расходные материалы. Презентация акции месяца — скидка 15% на пломбировочные материалы Filtek.',
        products: [
            { name: '3M Filtek Z550 (20 шт.)', price: 28000 },
            { name: 'Бонд Adper Single Bond', price: 8700 },
            { name: 'Иглы карпульные (50 уп.)', price: 8500 }
        ],
        orderAmount: 45200,
        isApproximate: true,
        lpr: {
            name: 'Абдуллина Резеда Фаритовна',
            role: 'Главный врач',
            phone: '+7 (843) 267-89-02',
            email: 'abdullina@medismile.ru',
            telegram: null
        },
        geoSent: false,
        geoLat: 55.7935,
        geoLng: 49.1147,
        result: null,
        comments: []
    },
    {
        id: 4,
        title: 'Центр имплантации "Рокада Мед"',
        client: 'Хасанов Артур Рамилевич',
        clientRole: 'Заведующий отделением',
        address: 'ул. Вишневского, 29/48, Казань',
        phone: '+7 (843) 528-40-20',
        time: '14:00',
        timeEnd: '14:45',
        status: 'planned',
        type: 'consultation',
        infoReason: 'Запрос от клиента: подбор имплантационной системы для нового кабинета.',
        description: 'Консультация по подбору имплантационной системы. Сравнение Osstem и Straumann. Демонстрация каталога и условий поставки.',
        products: [
            { name: 'Osstem TS III SA (набор)', price: 45000 },
            { name: 'Формирователь десны Osstem', price: 3200 }
        ],
        orderAmount: 48200,
        isApproximate: true,
        lpr: {
            name: 'Хасанов Артур Рамилевич',
            role: 'Заведующий отделением',
            phone: '+7 (843) 528-40-20',
            email: 'khasanov@rocadamed.ru',
            telegram: '@artur_khasanov'
        },
        geoSent: false,
        geoLat: 55.7856,
        geoLng: 49.1289,
        result: null,
        comments: []
    },
    {
        id: 5,
        title: 'Зуботехническая лаборатория "Арт-Дент"',
        client: 'Нуриев Камиль Фаридович',
        clientRole: 'Директор лаборатории',
        address: 'ул. Горького, 15, Казань',
        phone: '+7 (843) 590-33-44',
        time: '15:30',
        timeEnd: '16:00',
        status: 'planned',
        type: 'order',
        infoReason: 'Плановая поставка зуботехнических материалов. Клиент запросил новые диски для фрезеровки.',
        description: 'Поставка зуботехнических материалов. Обсуждение потребности в дисках для фрезеровки циркония и расходниках CAD/CAM.',
        products: [
            { name: 'Диски циркония Katana UTML (5 шт.)', price: 42000 },
            { name: 'PMMA диски (10 шт.)', price: 18000 },
            { name: 'Фрезы VHF (набор)', price: 18000 }
        ],
        orderAmount: 78000,
        isApproximate: true,
        lpr: {
            name: 'Нуриев Камиль Фаридович',
            role: 'Директор / Владелец',
            phone: '+7 (843) 590-33-44',
            email: 'nuriev@artdent-lab.ru',
            telegram: '@kamil_nuriev'
        },
        geoSent: false,
        geoLat: 55.7912,
        geoLng: 49.1198,
        result: null,
        comments: []
    },
    {
        id: 6,
        title: 'Детская стоматология "Зубрёнок"',
        client: 'Шарипова Лилия Ильдаровна',
        clientRole: 'Главный врач',
        address: 'ул. Чистопольская, 72, Казань',
        phone: '+7 (843) 212-55-66',
        time: '16:30',
        timeEnd: '17:00',
        status: 'planned',
        type: 'new_client',
        infoReason: 'Новый клиент! Получен контакт от маркетинга. Первичное знакомство и оценка потребностей.',
        description: 'Первый визит к новому клиенту. Знакомство, презентация компании Рокада Мед, каталога и условий сотрудничества. Оценка потребностей клиники.',
        products: [],
        orderAmount: 0,
        isApproximate: false,
        lpr: {
            name: 'Шарипова Лилия Ильдаровна',
            role: 'Главный врач / Владелец',
            phone: '+7 (843) 212-55-66',
            email: 'sharipova@zubrenok.ru',
            telegram: '@lilia_sharipova'
        },
        geoSent: false,
        geoLat: 55.8312,
        geoLng: 49.1451,
        result: null,
        comments: []
    }
]

// Визиты на завтра
export const visitsTomorrow = [
    {
        id: 7,
        title: 'Стоматология "Казанская"',
        client: 'Абдуллин Тимур Ильясович',
        clientRole: 'Управляющий',
        address: 'ул. Островского, 57, Казань',
        phone: '+7 (843) 290-11-22',
        time: '09:30',
        timeEnd: '10:15',
        status: 'planned',
        type: 'order',
        infoReason: 'Плановый визит. Подтверждение крупного заказа на оборудование для нового кабинета.',
        description: 'Подтверждение крупного заказа на оборудование и расходные материалы для нового кабинета.',
        products: [
            { name: 'Planmeca Compact i5', price: 1100000 },
            { name: 'Радиовизиограф Planmeca ProSensor', price: 280000 }
        ],
        orderAmount: 1380000,
        isApproximate: true,
        lpr: {
            name: 'Абдуллин Тимур Ильясович',
            role: 'Управляющий',
            phone: '+7 (843) 290-11-22',
            email: 'abdullin@kazanskaya-dent.ru',
            telegram: null
        },
        geoSent: false,
        geoLat: 55.7968,
        geoLng: 49.1082,
        result: null,
        comments: []
    },
    {
        id: 8,
        title: 'Клиника "Здоровая улыбка"',
        client: 'Фёдорова Елена Андреевна',
        clientRole: 'Главный врач',
        address: 'ул. Сибирский тракт, 31, Казань',
        phone: '+7 (843) 267-78-90',
        time: '11:00',
        timeEnd: '11:30',
        status: 'planned',
        type: 'presentation',
        infoReason: 'Демонстрация нового интраорального сканера 3Shape TRIOS 4. Клиент ранее интересовался.',
        description: 'Демонстрация интраорального сканера 3Shape TRIOS. Обучение персонала работе с цифровыми слепками.',
        products: [
            { name: '3Shape TRIOS 4', price: 950000 },
            { name: 'Калибровочный набор', price: 15000 }
        ],
        orderAmount: 0,
        isApproximate: false,
        lpr: {
            name: 'Фёдорова Елена Андреевна',
            role: 'Главный врач',
            phone: '+7 (843) 267-78-90',
            email: 'fedorova@zdorovaya-ulybka.ru',
            telegram: '@elena_fedorova'
        },
        geoSent: false,
        geoLat: 55.8145,
        geoLng: 49.1689,
        result: null,
        comments: []
    },
    {
        id: 9,
        title: 'Ортодонтический центр "Брекет"',
        client: 'Загидуллина Регина Ильгизовна',
        clientRole: 'Ортодонт',
        address: 'ул. Назарбаева, 12, Казань',
        phone: '+7 (843) 238-99-00',
        time: '13:00',
        timeEnd: '13:45',
        status: 'planned',
        type: 'order',
        infoReason: 'Поставка ортодонтических материалов. Обсуждение перехода на элайнеры Star Smile.',
        description: 'Поставка ортодонтических материалов.',
        products: [
            { name: 'Брекеты Damon Q2 (5 наборов)', price: 75000 },
            { name: 'Дуги NiTi (20 уп.)', price: 12000 },
            { name: 'Элайнеры Star Smile (стартовый набор)', price: 35000 }
        ],
        orderAmount: 122000,
        isApproximate: true,
        lpr: {
            name: 'Загидуллина Регина Ильгизовна',
            role: 'Ортодонт / Основатель клиники',
            phone: '+7 (843) 238-99-00',
            email: 'zagidullina@breket-kazan.ru',
            telegram: '@regina_ortho'
        },
        geoSent: false,
        geoLat: 55.7945,
        geoLng: 49.1067,
        result: null,
        comments: []
    },
    {
        id: 10,
        title: 'Стоматология "Премиум Дент"',
        client: 'Мухаметзянов Айрат Фанисович',
        clientRole: 'Владелец сети клиник',
        address: 'пр. Победы, 141, Казань',
        phone: '+7 (843) 555-00-11',
        time: '15:00',
        timeEnd: '16:00',
        status: 'planned',
        type: 'presentation',
        infoReason: 'Стратегическая встреча. Обсуждение годового контракта для сети из 4 клиник.',
        description: 'Обсуждение годового контракта на поставку расходных материалов для сети из 4 клиник. Индивидуальные условия и скидки.',
        products: [],
        orderAmount: 0,
        isApproximate: false,
        lpr: {
            name: 'Мухаметзянов Айрат Фанисович',
            role: 'Генеральный директор / Владелец',
            phone: '+7 (843) 555-00-11',
            email: 'mukhametzyanov@premiumdent.ru',
            telegram: '@airat_premium'
        },
        geoSent: false,
        geoLat: 55.8234,
        geoLng: 49.0812,
        result: null,
        comments: []
    }
]

// Все клиенты менеджера (закреплённая база)
export const allClients = [
    // Из визитов
    { id: 101, name: 'Стоматология "Дента Плюс"', address: 'ул. Баумана, 42, Казань', contact: 'Иванова М.С.', phone: '+7 (843) 555-12-34', lat: 55.7887, lng: 49.1221, lastVisit: '19 фев', hasPlannedVisit: true },
    { id: 102, name: 'Клиника "Белая Орхидея"', address: 'пр. Ямашева, 103, Казань', contact: 'Сафиуллин Р.М.', phone: '+7 (843) 222-45-67', lat: 55.8204, lng: 49.1373, lastVisit: '19 фев', hasPlannedVisit: true },
    { id: 103, name: 'Стоматология "МедиСмайл"', address: 'ул. Декабристов, 85А, Казань', contact: 'Галлямова А.Р.', phone: '+7 (843) 267-89-01', lat: 55.7935, lng: 49.1147, lastVisit: '19 фев', hasPlannedVisit: true },
    { id: 104, name: 'Центр имплантации "Рокада Мед"', address: 'ул. Вишневского, 29/48, Казань', contact: 'Хасанов А.Р.', phone: '+7 (843) 528-40-20', lat: 55.7856, lng: 49.1289, lastVisit: '19 фев', hasPlannedVisit: true },
    { id: 105, name: 'Лаборатория "Арт-Дент"', address: 'ул. Горького, 15, Казань', contact: 'Нуриев К.Ф.', phone: '+7 (843) 590-33-44', lat: 55.7912, lng: 49.1198, lastVisit: '19 фев', hasPlannedVisit: true },
    { id: 106, name: 'Детская стоматология "Зубрёнок"', address: 'ул. Чистопольская, 72, Казань', contact: 'Шарипова Л.И.', phone: '+7 (843) 212-55-66', lat: 55.8312, lng: 49.1451, lastVisit: 'Новый', hasPlannedVisit: true },
    // Дополнительные клиенты без визитов сегодня
    { id: 107, name: 'Стоматология "Миллидент"', address: 'ул. Академика Парина, 6, Казань', contact: 'Сагдеев Р.Р.', phone: '+7 (843) 273-44-55', lat: 55.8089, lng: 49.1556, lastVisit: '15 фев', hasPlannedVisit: false },
    { id: 108, name: 'Клиника "СтомаСервис"', address: 'ул. Татарстан, 20, Казань', contact: 'Хакимова Д.И.', phone: '+7 (843) 247-66-77', lat: 55.7845, lng: 49.1345, lastVisit: '12 фев', hasPlannedVisit: false },
    { id: 109, name: 'Стоматология "32 Жемчужины"', address: 'ул. Муштари, 11, Казань', contact: 'Валиуллина Г.А.', phone: '+7 (843) 236-88-99', lat: 55.7901, lng: 49.1234, lastVisit: '10 фев', hasPlannedVisit: false },
    { id: 110, name: 'Клиника "ДентаВита"', address: 'ул. Карла Маркса, 50, Казань', contact: 'Загиров А.Н.', phone: '+7 (843) 292-11-33', lat: 55.7978, lng: 49.1156, lastVisit: '8 фев', hasPlannedVisit: false },
    { id: 111, name: 'Стоматология "Эстетик"', address: 'ул. Лево-Булачная, 30, Казань', contact: 'Нигматуллина Р.Ф.', phone: '+7 (843) 253-22-44', lat: 55.7856, lng: 49.1089, lastVisit: '5 фев', hasPlannedVisit: false },
    { id: 112, name: 'Семейная стоматология "Династия"', address: 'ул. Фучика, 88, Казань', contact: 'Гимадиев И.Р.', phone: '+7 (843) 274-33-55', lat: 55.8167, lng: 49.1623, lastVisit: '3 фев', hasPlannedVisit: false },
]

// Вспомогательные функции

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
    }).format(amount)
}
