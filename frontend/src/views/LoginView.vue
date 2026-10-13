<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import { ApiError } from '../lib/api'

const router = useRouter()
const { status, login } = useAuth()
const email = ref('')
const password = ref('')
const busy = ref(false)
const message = ref('')
const details = ref<Record<string, string[]>>({})

watch(status, value => {
  if (value === 'authenticated') router.replace({ name: 'account' })
}, { immediate: true })

async function signIn() {
  if (busy.value) return
  busy.value = true
  message.value = ''
  details.value = {}
  try {
    await login(email.value.trim(), password.value)
  } catch (cause) {
    if (cause instanceof ApiError) {
      message.value = cause.status === 401 ? 'Email ou senha incorretos.'
        : cause.status === 429 ? 'Muitas tentativas. Aguarde um minuto antes de tentar novamente.'
        : cause.status === 419 ? 'Sua sessão expirou. Tente entrar novamente.' : cause.message
      details.value = cause.details
    } else message.value = 'Não foi possível entrar. Tente novamente.'
  } finally {
    password.value = ''
    busy.value = false
  }
}
</script>

<template>
  <section class="login-section" aria-labelledby="login-title">
    <p class="eyebrow">Seu espaço</p>
    <h1 id="login-title">Entrar na conta</h1>
    <p v-if="status === 'loading'" role="status">Consultando sua sessão…</p>
    <form v-else-if="status !== 'authenticated'" class="login-form" :aria-busy="busy" @submit.prevent="signIn">
      <div v-if="message" class="error" role="alert">{{ message }}</div>
      <label for="login-email">Email</label>
      <input id="login-email" v-model="email" type="email" autocomplete="username" maxlength="255" required :disabled="busy" :aria-invalid="!!details.email" :aria-describedby="details.email ? 'email-error' : undefined" />
      <p v-if="details.email" id="email-error" class="error">{{ details.email.join(' ') }}</p>
      <label for="login-password">Senha</label>
      <input id="login-password" v-model="password" type="password" autocomplete="current-password" required :disabled="busy" :aria-invalid="!!details.password" :aria-describedby="details.password ? 'password-error' : undefined" />
      <p v-if="details.password" id="password-error" class="error">{{ details.password.join(' ') }}</p>
      <button type="submit" :disabled="busy">{{ busy ? 'Entrando…' : 'Entrar' }}</button>
    </form>
    <RouterLink class="back-link" to="/">Continuar explorando o catálogo</RouterLink>
  </section>
</template>
