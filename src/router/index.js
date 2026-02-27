import { createRouter, createWebHistory } from 'vue-router'

const routes = [
    {
        path: '/login',
        name: 'Login',
        component: () => import('../views/LoginView.vue')
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
        path: '/map',
        name: 'Map',
        component: () => import('../views/MapView.vue'),
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

// Simulated auth guard
router.beforeEach((to, from, next) => {
    const isLoggedIn = localStorage.getItem('rocadamed_auth') === 'true'
    if (to.meta.requiresAuth && !isLoggedIn) {
        next('/login')
    } else if (to.path === '/login' && isLoggedIn) {
        next('/')
    } else {
        next()
    }
})

export default router
