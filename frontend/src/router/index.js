import { createRouter, createWebHistory } from 'vue-router'

import AppLayout from '@/layouts/AppLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

import HomePage from '@/pages/HomePage.vue'
import CrosswordListPage from '@/pages/CrosswordListPage.vue'
import CrosswordPage from '@/pages/CrosswordPage.vue'
import CrosswordCreator from '@/pages/CrosswordCreator.vue'

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
      { path: 'create', name: 'crosswordcreator', component: CrosswordCreator },
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

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('auth_token')

  if (to.name === 'login' && token) {
    next({ name: 'home' })
  }

  if (to.matched.some(record => record.meta.requiresAuth) && !token) {
    next({ name: 'login' })
  } else {
    next()
  }
})

export default router
