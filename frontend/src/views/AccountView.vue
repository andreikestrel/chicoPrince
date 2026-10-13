<script setup lang="ts">
import { ref } from 'vue'
import { useAuth } from '../composables/useAuth'

const { user, status, error, refreshSession, logout } = useAuth()
const busy = ref(false)
const actionError = ref('')

async function signOut() {
  busy.value = true
  actionError.value = ''
  try { await logout() } catch (cause) {
    actionError.value = cause instanceof Error ? cause.message : 'Não foi possível sair.'
  } finally { busy.value = false }
}
</script>

<template>
  <section class="intro" aria-labelledby="account-title">
    <p class="eyebrow">Seu espaço</p>
    <h1 id="account-title">Minha conta</h1>
    <div role="status" aria-live="polite">
      <p v-if="status === 'loading'">Consultando sua sessão…</p>
      <template v-else-if="status === 'authenticated' && user">
        <p>Olá, {{ user.name }}.</p>
        <p>{{ user.email }}</p>
      </template>
      <p v-else-if="status === 'guest'">Você está navegando como visitante.</p>
      <p v-else>{{ error }}</p>
    </div>
    <button v-if="status === 'authenticated'" :disabled="busy" @click="signOut">{{ busy ? 'Saindo…' : 'Sair da conta' }}</button>
    <RouterLink v-else-if="status === 'guest'" class="back-link" to="/login">Entrar na conta</RouterLink>
    <button v-else :disabled="status === 'loading'" @click="refreshSession">Verificar sessão</button>
    <p v-if="actionError" role="alert">{{ actionError }}</p>
  </section>
</template>
