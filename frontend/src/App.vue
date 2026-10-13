<script setup lang="ts">
import { nextTick, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuth } from './composables/useAuth'

const { refreshSession, status } = useAuth()
const route = useRoute()
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
    <footer>Chico Prince · Catálogo de produtos</footer>
  </div>
</template>
