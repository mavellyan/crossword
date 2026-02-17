import { createRouter, createWebHistory } from 'vue-router'

import AppLayout from '@/layouts/AppLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

import HomePage from '@/pages/HomePage.vue'
import PuzzleListPage from '@/pages/PuzzleListPage.vue'
import PuzzlePage from '@/pages/PuzzlePage.vue'

import LoginPage from '@/pages/LoginPage.vue'
import RegisterPage from '@/pages/RegisterPage.vue'

import NotFoundPage from '@/pages/NotFoundPage.vue'

const routes = [
  {
    path: '/',
    component: AppLayout,
    children: [
      { path: '', name: 'home', component: HomePage },
      { path: 'puzzlelist', name: 'puzzlelist', component: PuzzleListPage },
      { path: 'puzzles/:id', name: 'puzzle', component: PuzzlePage, props: true },
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
  { path: '/:pathMatch(.*)*', name: 'notfound', component: NotFoundPage },
]

export default createRouter({
  history: createWebHistory(),
  routes,
})
