<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuth } from './composables/useAuth'

const { refreshSession, status } = useAuth()
onMounted(refreshSession)
</script>

<template>
  <main>
    <header>
      <RouterLink class="brand" to="/" aria-label="Chico Prince, início">CHICO PRINCE<span>®</span></RouterLink>
      <nav aria-label="Navegação principal">
        <RouterLink to="/">Catálogo</RouterLink>
        <RouterLink to="/conta">Minha conta</RouterLink>
        <RouterLink v-if="status === 'authenticated'" to="/admin/products">Gerenciar produtos</RouterLink>
        <RouterLink v-if="status === 'guest'" to="/login">Entrar</RouterLink>
      </nav>
    </header>
    <RouterView />
    <footer>Chico Prince · Ambiente de desenvolvimento</footer>
  </main>
</template>
