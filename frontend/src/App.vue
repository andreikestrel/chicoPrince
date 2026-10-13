<script setup lang="ts">
import CartDrawer from './components/CartDrawer.vue'
import { nextTick, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useCart } from './composables/useCart'
import { useAuth } from './composables/useAuth'

const { refreshSession, status, user } = useAuth()
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
        <RouterLink v-if="status === 'authenticated' && user?.is_admin" to="/admin/products">Gerenciar produtos</RouterLink>
        <RouterLink v-if="status === 'guest'" to="/login">Entrar</RouterLink>
      </nav>
    </header>
    <main id="main-content" tabindex="-1"><RouterView /></main>
        <button class="floating-cart" :aria-label="`Abrir carrinho, ${count} unidade(s)`" title="Abrir carrinho" @click="openDrawer">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h3l3 12h11l3-9H6 M8 15l-1 3h13" /><circle cx="9" cy="21" r="1" /><circle cx="19" cy="21" r="1" /></svg>
          <span class="cart-count" aria-hidden="true">{{ count }}</span>
        </button>
    <CartDrawer />
    <footer class="site-footer">
      <span>Desenvolvido por Andrei Kestrel</span>
      <a href="https://github.com/andreikestrel" target="_blank" rel="noopener noreferrer" aria-label="GitHub de Andrei Kestrel" title="GitHub">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 .8a11.2 11.2 0 0 0-3.54 21.83c.56.1.76-.24.76-.54v-2.09c-3.12.68-3.78-1.33-3.78-1.33-.51-1.3-1.25-1.64-1.25-1.64-1.02-.7.08-.69.08-.69 1.13.08 1.73 1.16 1.73 1.16 1 .1 1.75 2.31 3.26 1.11.1-.73.39-1.23.71-1.52-2.49-.28-5.11-1.25-5.11-5.54 0-1.22.44-2.22 1.15-3-.12-.28-.5-1.42.11-2.96 0 0 .94-.3 3.08 1.15a10.7 10.7 0 0 1 5.6 0c2.14-1.45 3.08-1.15 3.08-1.15.61 1.54.23 2.68.11 2.96.72.78 1.15 1.78 1.15 3 0 4.3-2.62 5.26-5.12 5.54.4.35.76 1.03.76 2.08v2.91c0 .3.2.65.77.54A11.2 11.2 0 0 0 12 .8Z" /></svg>
      </a>
      <a href="https://www.linkedin.com/in/barbosaandrei/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn de Andrei Kestrel" title="LinkedIn">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.45 2H3.55C2.69 2 2 2.68 2 3.52v16.96c0 .84.69 1.52 1.55 1.52h16.9c.86 0 1.55-.68 1.55-1.52V3.52c0-.84-.69-1.52-1.55-1.52ZM7.93 18.75H4.98V9.2h2.95v9.55ZM6.46 7.9a1.71 1.71 0 1 1 0-3.42 1.71 1.71 0 0 1 0 3.42Zm12.29 10.85H15.8V14.1c0-1.11-.02-2.54-1.55-2.54-1.55 0-1.79 1.21-1.79 2.46v4.73H9.51V9.2h2.83v1.31h.04c.39-.75 1.36-1.55 2.79-1.55 2.98 0 3.58 1.96 3.58 4.51v5.28Z" /></svg>
      </a>
    </footer>
  </div>
</template>

<style scoped>
.site-footer { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: center; justify-content: center; text-align: center; padding: 24px 76px; }
.site-footer a { color: inherit; font-size: inherit; display: inline-flex; align-items: center; justify-content: center; min-width: 44px; min-height: 44px; }
.site-footer a svg { width: 1em; height: 1em; }
</style>