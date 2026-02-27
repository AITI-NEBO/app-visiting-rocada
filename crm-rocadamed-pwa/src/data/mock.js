// Mock data for RocadaMed CRM PWA

export const currentUser = {
    id: 1,
    name: 'Алексей Петров',
    position: 'Выездной медицинский специалист',
    avatar: null,
    initials: 'АП',
    phone: '+7 (916) 234-56-78',
    email: 'a.petrov@rocadamed.ru',
    stats: {
        todayCompleted: 3,
        todayTotal: 7,
        weekCompleted: 18,
        weekTotal: 25,
        monthCompleted: 67,
        monthTotal: 80
    }
}

export const visits = [
    {
        id: 1001,
        title: 'Плановый осмотр — Иванова М.С.',
        client: 'Иванова Мария Сергеевна',
        address: 'ул. Ленина, 42, кв. 15',
        time: '09:00',
        endTime: '09:45',
        status: 'completed',
        latitude: 55.7558,
        longitude: 37.6173,
        phone: '+7 (903) 111-22-33',
        comments: [
            { author: 'Алексей Петров', text: 'Пациент принят. Назначения выданы.', date: '2026-02-18 09:40' }
        ],
        description: 'Плановый осмотр пациента после курса реабилитации. Проверить показатели давления и сахара.'
    },
    {
        id: 1002,
        title: 'Забор анализов — Сидоров К.А.',
        client: 'Сидоров Кирилл Андреевич',
        address: 'пр. Мира, 78, кв. 3',
        time: '10:00',
        endTime: '10:30',
        status: 'completed',
        latitude: 55.7612,
        longitude: 37.6328,
        phone: '+7 (905) 222-33-44',
        comments: [
            { author: 'Алексей Петров', text: 'Анализы взяты, отправлены в лабораторию.', date: '2026-02-18 10:25' }
        ],
        description: 'Забор крови на общий анализ и биохимию. Пациент с хроническим заболеванием.'
    },
    {
        id: 1003,
        title: 'Перевязка — Козлова А.В.',
        client: 'Козлова Анна Владимировна',
        address: 'ул. Гагарина, 15, кв. 42',
        time: '11:00',
        endTime: '11:40',
        status: 'completed',
        latitude: 55.7498,
        longitude: 37.6205,
        phone: '+7 (926) 333-44-55',
        comments: [
            { author: 'Алексей Петров', text: 'Перевязка выполнена. Рана заживает хорошо.', date: '2026-02-18 11:35' }
        ],
        description: 'Повторная перевязка после операции. Контроль состояния раны.'
    },
    {
        id: 1004,
        title: 'Инъекция — Морозов Д.И.',
        client: 'Морозов Дмитрий Игоревич',
        address: 'Бульвар Строителей, 5, кв. 88',
        time: '12:30',
        endTime: '13:00',
        status: 'in_progress',
        latitude: null,
        longitude: null,
        phone: '+7 (917) 444-55-66',
        comments: [],
        description: 'Плановая инъекция витамина B12. Пациент — пожилой человек, нужно проявить терпение.'
    },
    {
        id: 1005,
        title: 'Консультация — Новикова Е.П.',
        client: 'Новикова Елена Павловна',
        address: 'ул. Чехова, 33, кв. 7',
        time: '14:00',
        endTime: '14:45',
        status: 'pending',
        latitude: null,
        longitude: null,
        phone: '+7 (903) 555-66-77',
        comments: [],
        description: 'Первичная консультация по поводу болей в суставах. Собрать анамнез, провести осмотр.'
    },
    {
        id: 1006,
        title: 'Капельница — Волков С.М.',
        client: 'Волков Сергей Михайлович',
        address: 'пер. Садовый, 9, кв. 21',
        time: '15:30',
        endTime: '16:30',
        status: 'pending',
        latitude: null,
        longitude: null,
        phone: '+7 (916) 666-77-88',
        comments: [],
        description: 'Капельница с физраствором и витаминами. Длительность ~1 час.'
    },
    {
        id: 1007,
        title: 'Осмотр — Белова Т.Н.',
        client: 'Белова Татьяна Николаевна',
        address: 'ул. Пушкина, 101, кв. 56',
        time: '17:00',
        endTime: '17:30',
        status: 'pending',
        latitude: null,
        longitude: null,
        phone: '+7 (905) 777-88-99',
        comments: [],
        description: 'Контрольный осмотр после ОРВИ. Проверить лёгкие, горло.'
    }
]

export const visitsTomorrow = [
    {
        id: 2001,
        title: 'Плановый осмотр — Егоров В.К.',
        client: 'Егоров Валерий Константинович',
        address: 'ул. Лермонтова, 7, кв. 10',
        time: '09:00',
        endTime: '09:45',
        status: 'pending',
        latitude: null,
        longitude: null,
        phone: '+7 (916) 100-20-30',
        comments: [],
        description: 'Плановый осмотр. Контроль давления.'
    },
    {
        id: 2002,
        title: 'Забор анализов — Павлова И.С.',
        client: 'Павлова Ирина Сергеевна',
        address: 'ул. Толстого, 22, кв. 4',
        time: '10:30',
        endTime: '11:00',
        status: 'pending',
        latitude: null,
        longitude: null,
        phone: '+7 (903) 200-30-40',
        comments: [],
        description: 'Забор крови на гормоны щитовидной железы.'
    },
    {
        id: 2003,
        title: 'ЭКГ — Кузнецов А.А.',
        client: 'Кузнецов Андрей Алексеевич',
        address: 'пр. Победы, 55, кв. 31',
        time: '12:00',
        endTime: '12:30',
        status: 'pending',
        latitude: null,
        longitude: null,
        phone: '+7 (926) 300-40-50',
        comments: [],
        description: 'Выездное ЭКГ. Пациент с кардиологическим диагнозом.'
    },
    {
        id: 2004,
        title: 'Перевязка — Смирнова О.Д.',
        client: 'Смирнова Ольга Дмитриевна',
        address: 'ул. Горького, 12, кв. 67',
        time: '14:00',
        endTime: '14:30',
        status: 'pending',
        latitude: null,
        longitude: null,
        phone: '+7 (917) 400-50-60',
        comments: [],
        description: 'Послеоперационная перевязка. Снятие швов.'
    },
    {
        id: 2005,
        title: 'Консультация — Фёдоров Р.В.',
        client: 'Фёдоров Роман Васильевич',
        address: 'Бульвар Космонавтов, 3, кв. 15',
        time: '16:00',
        endTime: '16:45',
        status: 'pending',
        latitude: null,
        longitude: null,
        phone: '+7 (905) 500-60-70',
        comments: [],
        description: 'Повторная консультация. Обсуждение результатов анализов.'
    }
]

export function getStatusLabel(status) {
    const map = {
        completed: 'Выполнен',
        in_progress: 'В работе',
        pending: 'Ожидает'
    }
    return map[status] || status
}

export function getStatusColor(status) {
    const map = {
        completed: 'var(--color-success)',
        in_progress: 'var(--color-warning)',
        pending: 'var(--color-text-tertiary)'
    }
    return map[status] || 'var(--color-text-tertiary)'
}
