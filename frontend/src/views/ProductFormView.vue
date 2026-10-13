<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, ApiError, prepareCsrf } from '../lib/api'
import { useAuth } from '../composables/useAuth'
import type { Product } from '../types/products'

const route = useRoute()
const router = useRouter()
const { status, refreshSession } = useAuth()
const isEdit = computed(() => route.params.id !== undefined)
const empty = { name: '', description: '', image: '', price: '', category: '', stock: '0' }
const form = reactive({ ...empty })
const loading = ref(false)
const busy = ref(false)
const loadError = ref('')
const message = ref('')
const errors = ref<Record<string, string[]>>({})
let controller: AbortController | undefined
let generation = 0

async function load() {
  generation++
  controller?.abort()
  const request = new AbortController()
  controller = request
  Object.assign(form, empty)
  loadError.value = ''
  message.value = ''
  errors.value = {}
  loading.value = false
  if (!isEdit.value || status.value !== 'authenticated') return
  loading.value = true
  try {
    const { data } = await api.get<{ data: Product }>(`/products/${encodeURIComponent(String(route.params.id))}`, { signal: request.signal })
    if (!request.signal.aborted) {
      const product = data.data
      Object.assign(form, { name: product.name, description: product.description, image: product.image ?? '', price: product.price, category: product.category, stock: String(product.stock) })
    }
  } catch (cause) {
    if (!request.signal.aborted) loadError.value = cause instanceof ApiError && cause.status === 404 ? 'Produto não encontrado.' : 'Não foi possível carregar o produto. Tente novamente.'
  } finally {
    if (!request.signal.aborted) loading.value = false
  }
}

async function save() {
  if (busy.value || loading.value || status.value !== 'authenticated') return
  const current = generation
  const editing = isEdit.value
  const id = String(route.params.id)
  const payload = { ...form, image: form.image.trim() || null }
  busy.value = true
  message.value = ''
  errors.value = {}
  try {
    await prepareCsrf()
    await (editing
      ? api.patch<{ data: Product }>(`/products/${encodeURIComponent(id)}`, payload)
      : api.post<{ data: Product }>('/products', payload))
    if (current === generation) await router.replace({ name: 'home' })
  } catch (cause) {
    if (current !== generation) return
    if (cause instanceof ApiError) {
      errors.value = cause.details
      message.value = cause.status === 401 ? 'Sua sessão expirou. Entre novamente para salvar.'
        : cause.status === 419 ? 'A sessão de segurança expirou. Tente salvar novamente.'
        : cause.status === 404 ? 'Este produto não existe mais.' : cause.message
      if (cause.status === 401) await refreshSession()
    } else message.value = 'Não foi possível salvar o produto.'
  } finally { busy.value = false }
}
watch([() => route.params.id, () => status.value === 'authenticated'], load, { immediate: true })
onBeforeUnmount(() => { generation++; controller?.abort() })
</script>

<template>
  <section class="editor-section" aria-labelledby="editor-title">
    <RouterLink class="back-link" to="/">← Voltar ao catálogo</RouterLink>
    <h1 id="editor-title">{{ isEdit ? 'Editar produto' : 'Criar produto' }}</h1>
    <p v-if="status === 'loading'" role="status">Consultando sua sessão…</p>
    <div v-else-if="status !== 'authenticated'">
      <p>Entre na sua conta para gerenciar produtos.</p>
      <RouterLink class="back-link" to="/login">Entrar na conta</RouterLink>
      <button v-if="status === 'error'" @click="refreshSession">Verificar sessão</button>
    </div>
    <p v-else-if="loading" role="status">Carregando produto…</p>
    <div v-else-if="loadError" role="alert"><p>{{ loadError }}</p><button @click="load">Tentar novamente</button></div>
    <template v-else>
      <form class="product-form" :aria-busy="busy" @submit.prevent="save">
        <div v-if="message" class="error" role="alert">{{ message }}</div>
        <fieldset :disabled="busy">
          <label for="product-name">Nome</label>
          <input id="product-name" v-model="form.name" required maxlength="255" :aria-invalid="!!errors.name" aria-describedby="error-name" />
          <p id="error-name" class="error">{{ errors.name?.join(' ') }}</p>
          <label for="product-description">Descrição</label>
          <textarea id="product-description" v-model="form.description" required rows="5" :aria-invalid="!!errors.description" aria-describedby="error-description" />
          <p id="error-description" class="error">{{ errors.description?.join(' ') }}</p>
          <label for="product-category">Categoria</label>
          <input id="product-category" v-model="form.category" required maxlength="100" :aria-invalid="!!errors.category" aria-describedby="error-category" />
          <p id="error-category" class="error">{{ errors.category?.join(' ') }}</p>
          <label for="product-price">Preço (R$)</label>
          <input id="product-price" v-model="form.price" type="number" min="0" max="99999999.99" step="0.01" required :aria-invalid="!!errors.price" aria-describedby="error-price" />
          <p id="error-price" class="error">{{ errors.price?.join(' ') }}</p>
          <label for="product-stock">Estoque</label>
          <input id="product-stock" v-model="form.stock" type="number" min="0" max="4294967295" step="1" required :aria-invalid="!!errors.stock" aria-describedby="error-stock" />
          <p id="error-stock" class="error">{{ errors.stock?.join(' ') }}</p>
          <label for="product-image">URL da imagem (opcional)</label>
          <input id="product-image" v-model="form.image" type="url" maxlength="2048" :aria-invalid="!!errors.image" aria-describedby="error-image" />
          <p id="error-image" class="error">{{ errors.image?.join(' ') }}</p>
          <button type="submit">{{ busy ? 'Salvando…' : isEdit ? 'Salvar alterações' : 'Criar produto' }}</button>
        </fieldset>
      </form>
    </template>
  </section>
</template>
