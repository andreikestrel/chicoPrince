<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import { api, ApiError } from '../lib/api'

const router = useRouter()
const route = useRoute()
const demoAccounts = ref<{ label: string; email: string; password: string }[]>([])
onMounted(async () => {
  try { demoAccounts.value = (await api.get('/demo-accounts')).data.data } catch { /* Fora da demonstração, a caixa não é exibida. */ }
})
const { status, login } = useAuth()
const email = ref('')
const password = ref('')
const busy = ref(false)
const message = ref('')
const details = ref<Record<string, string[]>>({})

let redirecting = false
watch(status, value => {
  if (value === 'authenticated' && !redirecting) {
    redirecting = true
    void router.replace(route.query.redirect === '/checkout' ? '/checkout' : { name: 'account' })
      .finally(() => { redirecting = false })
  }
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
    <aside v-if="demoAccounts.length" class="demo-accounts" aria-label="Contas de demonstração">
      <h2>Contas para testar</h2>
      <p>Ambiente demonstrativo do projeto.</p>
      <div v-for="account in demoAccounts" :key="account.email">
        <strong>{{ account.label }}</strong>
        <p>{{ account.email }}<br />Senha: <code>{{ account.password }}</code></p>
        <button type="button" class="secondary" :disabled="busy" @click="email = account.email; password = account.password">Usar {{ account.label.toLowerCase() }}</button>
      </div>
    </aside>
    <RouterLink class="back-link" to="/">Continuar explorando o catálogo</RouterLink>
  </section>
</template>
