// src/router/index.ts
import { createRouter, createWebHistory } from 'vue-router';
import MainRoutes from './MainRoutes';
import AuthRoutes from './AuthRoutes';

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/:pathMatch(.*)*',
      component: () => import('@/views/pages/Error404.vue')
    },
    MainRoutes,
    AuthRoutes,
  ]
});

router.beforeEach((to, from, next) => {
  const userJson = sessionStorage.getItem('user');
  const user = userJson ? JSON.parse(userJson) : null;
  const loginTime = user?.loginTime || 0;
  const now = Date.now();
  

  const sessionExpired = now - loginTime > 18_000_000;

  const isLoggedIn = !!user && !sessionExpired;
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth);

  if (sessionExpired && user) {
    sessionStorage.removeItem('user');
    localStorage.removeItem('user_id');
    return next({ name: 'Login' });
  }

  if (requiresAuth && !isLoggedIn) {
    return next({ name: 'Login' }); 
  }

  if (isLoggedIn && to.name === 'Login') {
    return next({ name: 'Dashboard' }); 
  }

  next(); 
});
