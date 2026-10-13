import { createRouter, createWebHistory } from 'vue-router'
import HomeView from './views/HomeView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: HomeView, meta: { title: 'Catálogo' } },
    { path: '/admin/products', name: 'admin-products', component: () => import('./views/AdminProductsView.vue'), meta: { title: 'Gerenciar produtos' } },
    { path: '/admin/products/new', name: 'product-create', component: () => import('./views/ProductFormView.vue'), meta: { title: 'Criar produto' } },
    { path: '/admin/products/:id/edit', name: 'product-edit', component: () => import('./views/ProductFormView.vue'), meta: { title: 'Editar produto' } },
    { path: '/produtos/novo', redirect: { name: 'product-create' } },
    { path: '/produtos/:id/editar', redirect: to => ({ name: 'product-edit', params: to.params, query: to.query }) },
    { path: '/produtos/:id', name: 'product', component: () => import('./views/ProductView.vue'), meta: { title: 'Detalhe do produto' } },
    { path: '/checkout', name: 'checkout', component: () => import('./views/CheckoutView.vue'), meta: { title: 'Checkout' } },
    { path: '/conta', name: 'account', component: () => import('./views/AccountView.vue'), meta: { title: 'Minha conta' } },
    { path: '/login', name: 'login', component: () => import('./views/LoginView.vue'), meta: { title: 'Entrar' } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('./views/NotFoundView.vue'), meta: { title: 'Página não encontrada' } },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.afterEach(to => { document.title = `${String(to.meta.title)} · Chico Prince` })
