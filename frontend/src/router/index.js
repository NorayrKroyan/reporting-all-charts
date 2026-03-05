import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '../layouts/AppLayout.vue'
import DailyCharts from '../pages/reports/DailyCharts.vue'

export default createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            component: AppLayout,
            children: [
                { path: '', redirect: '/reports/daily-charts' },
                { path: '/reports/daily-charts', component: DailyCharts },
            ],
        },
    ],
})