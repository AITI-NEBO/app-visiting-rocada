import { createRouter, createWebHistory } from 'vue-router'

const routes = [
    {
        path: '/login',
        name: 'Login',
        component: () => import('../views/LoginView.vue')
    },
    {
        // OAuth2 callback — Б24 редиректит сюда с ?code=...
        path: '/auth/callback',
        name: 'AuthCallback',
        component: () => import('../views/AuthCallbackView.vue')
    },
    {
        path: '/',
        name: 'Dashboard',
        component: () => import('../views/DashboardView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/visits',
        name: 'Visits',
        component: () => import('../views/VisitsListView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/visits/:id',
        name: 'DealDetail',
        component: () => import('../views/DealDetailView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/visits/:id/geo',
        name: 'GeoConfirm',
        component: () => import('../views/GeoConfirmView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/visits/:id/comment',
        name: 'Comment',
        component: () => import('../views/CommentView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/visits/:id/result',
        name: 'VisitResult',
        component: () => import('../views/VisitResultView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/visits/:id/infopovod',
        name: 'Infopovod',
        component: () => import('../views/InfopovodView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/company/:id',
        name: 'Company',
        component: () => import('../views/CompanyView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/contact/:id',
        name: 'Contact',
        component: () => import('../views/ContactView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/map',
        name: 'Map',
        component: () => import('../views/MapView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/plan',
        name: 'PlanVisit',
        component: () => import('../views/PlanVisitView.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/profile',
        name: 'Profile',
        component: () => import('../views/ProfileView.vue'),
        meta: { requiresAuth: true }
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// ── Auth Guard ────────────────────────────────────────────────────────────────
// Проверяем наличие JWT-токена в localStorage (ключ из useAuth.js)
const TOKEN_KEY = 'rocadamed_token'

router.beforeEach((to, from, next) => {
    const isLoggedIn = !!localStorage.getItem(TOKEN_KEY)

    // /auth/callback — всегда пропускать
    if (to.name === 'AuthCallback') {
        return next()
    }

    // ── Авто-детект OAuth code на ЛЮБОЙ странице ──────────────────────────────
    // Б24 может вернуть ?code= как на /auth/callback так и на корне (зависит от настроек)
    if (to.query.code && to.name !== 'AuthCallback') {
        return next({ name: 'AuthCallback', query: to.query })
    }

    if (to.meta.requiresAuth && !isLoggedIn) {
        next({ name: 'Login', query: { redirect: to.fullPath } })
    } else if (to.name === 'Login' && isLoggedIn) {
        next('/')
    } else {
        next()
    }
})

export default router
