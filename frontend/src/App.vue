<script setup lang="ts">
import CartDrawer from './components/CartDrawer.vue'
import { nextTick, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useCart } from './composables/useCart'
import { useAuth } from './composables/useAuth'

const { refreshSession, status } = useAuth()
const route = useRoute()
const { count, openDrawer } = useCart()
onMounted(refreshSession)
function focusContent() { document.getElementById('main-content')?.focus() }
watch(() => route.path, async () => {
  await nextTick()
  focusContent()
})
</script>

<template>
  <div class="app-shell">
    <a class="skip-link" href="#main-content" @click.prevent="focusContent">Pular para o conteúdo</a>
    <header>
      <RouterLink class="brand" to="/" aria-label="Chico Prince, início">CHICO PRINCE<span>®</span></RouterLink>
      <nav aria-label="Navegação principal">
        <RouterLink to="/">Catálogo</RouterLink>

        <RouterLink to="/conta">Minha conta</RouterLink>
        <RouterLink v-if="status === 'authenticated'" to="/admin/products">Gerenciar produtos</RouterLink>
        <RouterLink v-if="status === 'guest'" to="/login">Entrar</RouterLink>
      </nav>
    </header>
    <main id="main-content" tabindex="-1"><RouterView /></main>
        <button class="floating-cart" :aria-label="`Abrir carrinho, ${count} unidade(s)`" title="Abrir carrinho" @click="openDrawer">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h3l3 12h11l3-9H6 M8 15l-1 3h13" /><circle cx="9" cy="21" r="1" /><circle cx="19" cy="21" r="1" /></svg>
          <span class="cart-count" aria-hidden="true">{{ count }}</span>
        </button>
    <CartDrawer />
    <footer>Chico Prince · Catálogo de produtos</footer>
  </div>
</template>
