import { createRouter, createWebHistory } from 'vue-router'
import HomeView from './views/HomeView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: HomeView, meta: { title: 'Catálogo' } },
    { path: '/produtos/:id', name: 'product', component: () => import('./views/ProductView.vue'), meta: { title: 'Detalhe do produto' } },
    { path: '/conta', name: 'account', component: () => import('./views/AccountView.vue'), meta: { title: 'Minha conta' } },
    { path: '/login', name: 'login', component: () => import('./views/LoginView.vue'), meta: { title: 'Entrar' } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('./views/NotFoundView.vue'), meta: { title: 'Página não encontrada' } },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.afterEach(to => { document.title = `${String(to.meta.title)} · Chico Prince` })
