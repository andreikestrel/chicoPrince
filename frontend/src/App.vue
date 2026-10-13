<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuth } from './composables/useAuth'

const { refreshSession } = useAuth()
onMounted(refreshSession)
</script>

<template>
  <main>
    <header>
      <RouterLink class="brand" to="/" aria-label="Chico Prince, início">CHICO PRINCE<span>®</span></RouterLink>
      <nav aria-label="Navegação principal">
        <RouterLink to="/">Início</RouterLink>
        <RouterLink to="/conta">Minha conta</RouterLink>
      </nav>
    </header>
    <RouterView />
    <footer>Chico Prince · Ambiente de desenvolvimento</footer>
  </main>
</template>
