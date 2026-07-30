import { createRouter, createWebHistory } from 'vue-router'

import AppLayout from '@/layouts/AppLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

import HomePage from '@/pages/HomePage.vue'
import CrosswordListPage from '@/pages/CrosswordListPage.vue'
import CrosswordPage from '@/pages/CrosswordPage.vue'
import CrosswordCreator from '@/pages/CrosswordCreator.vue'
import ProfilePage from '@/pages/ProfilePage.vue'

import LoginPage from '@/pages/LoginPage.vue'
import RegisterPage from '@/pages/RegisterPage.vue'

import NotFoundPage from '@/pages/NotFoundPage.vue'

const routes = [
  {
    path: '/',
    component: AppLayout,
    children: [
      { path: '', name: 'home', component: HomePage },
      { path: 'crosswordlist', name: 'crosswordlist', component: CrosswordListPage },
      { path: 'crossword/:id', name: 'crossword', component: CrosswordPage, props: true },
      { path: 'create', name: 'crosswordcreator', component: CrosswordCreator, meta: { requiresAuth: true } },
      { path: 'profile', name: 'profile', component: ProfilePage, meta: { requiresAuth: true } }
    ],
  },
  {
    path: '/',
    component: AuthLayout,
    children: [
      { path: 'login', name: 'login', component: LoginPage },
      { path: 'register', name: 'register', component: RegisterPage },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'notfound',
    component: NotFoundPage
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const token = localStorage.getItem('token')

  // Bejelentkezést igénylő oldalak esetén, ha nincs token, a bejelentkezési oldalra irányítjuk a felhasználót
  if (to.meta.requiresAuth && !token) {
    return {
      name: 'login',
      query: {
        redirect: to.fullPath,
      },
    }
  }

  // Bejelentkezett felhasználót a főoldalra navigáljuk, ha a bejelentkezési vagy regisztrációs oldalra próbál navigálni
  if (token && (to.name === 'login' || to.name === 'register')) {
    return { name: 'home' }
  }

  // Ha nem adunk vissza semmit, a navigáció folytatódik az eredeti céloldalra
})

export default router
