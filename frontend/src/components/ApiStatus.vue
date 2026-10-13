<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '../lib/api'

const state = ref<'loading' | 'success' | 'error'>('loading')

async function checkConnection() {
  state.value = 'loading'

  try {
    const response = await api.get('/health')
    const result = response.data
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
