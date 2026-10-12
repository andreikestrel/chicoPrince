<script setup lang="ts">
import { onMounted, ref } from 'vue'

const state = ref<'loading' | 'success' | 'error'>('loading')
const apiBase = (import.meta.env.VITE_API_BASE_URL || '/api').replace(/\/$/, '')

async function checkConnection() {
  state.value = 'loading'

  try {
    const response = await fetch(`${apiBase}/health`, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(10000),
      cache: 'no-store',
    })

    if (!response.ok) throw new Error('Service unavailable')

    const result = await response.json()
    if (result?.data?.status !== 'ok' || result?.data?.database !== 'ok') {
      throw new Error('Unexpected response')
    }

    state.value = 'success'
  } catch {
    state.value = 'error'
  }
}

onMounted(checkConnection)
</script>

<template>
  <section class="connection" aria-labelledby="connection-title" :aria-busy="state === 'loading'">
    <div>
      <h2 id="connection-title">Conexão com o serviço</h2>
      <p role="status" aria-live="polite" :class="state">
        <template v-if="state === 'loading'">Verificando conexão…</template>
        <template v-else-if="state === 'success'">Tudo conectado. Nosso serviço está disponível.</template>
        <template v-else>Não foi possível conectar. Tente novamente em instantes.</template>
      </p>
    </div>
    <button type="button" :disabled="state === 'loading'" @click="checkConnection">
      {{ state === 'loading' ? 'Verificando…' : 'Verificar novamente' }}
    </button>
  </section>
</template>
