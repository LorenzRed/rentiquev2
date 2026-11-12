import { createRouter, createWebHistory } from '@ionic/vue-router';

import TabsPage from '../views/TabsPage.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: TabsPage,
      children: [
        { path: '', redirect: '/home' },
        { path: 'home', component: () => import('../views/HomePage.vue') },
        { path: 'shops', component: () => import('../views/ShopsPage.vue') },
        { path: 'map', component: () => import('../views/ShopsPage.vue') },
        { path: 'bookings', component: () => import('../views/BookingsPage.vue') },
        { path: 'profile', component: () => import('../views/ProfilePage.vue') },
      ],
    },
    { path: '/owner/apply', component: () => import('../views/ShopApplicationPage.vue') },
    { path: '/products', component: () => import('../views/ProductsPage.vue') },
    { path: '/workspace', component: () => import('../views/ManagementPage.vue') },
    { path: '/:pathMatch(.*)*', redirect: '/home' },
  ],
});
export default router;
