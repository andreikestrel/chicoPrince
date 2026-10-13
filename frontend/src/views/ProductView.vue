<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api, ApiError, prepareCsrf } from '../lib/api'
import { useAuth } from '../composables/useAuth'
import ProductImage from '../components/ProductImage.vue'
import type { Product } from '../types/products'

const route = useRoute()
const { status, refreshSession } = useAuth()
const deleting = ref(false)
const deleted = ref(false)
const deleteError = ref('')
const product = ref<Product | null>(null)
const loading = ref(true)
const error = ref<ApiError | null>(null)
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
let controller: AbortController | undefined

async function loadProduct() {
  deleted.value = false
  deleteError.value = ''
  controller?.abort()
  const request = new AbortController()
  controller = request
  loading.value = true
  error.value = null
  product.value = null
  try {
    const { data } = await api.get<{ data: Product }>(`/products/${encodeURIComponent(String(route.params.id))}`, { signal: request.signal })
    if (!request.signal.aborted) product.value = data.data
  } catch (cause) {
    if (!request.signal.aborted) error.value = cause instanceof ApiError ? cause : new ApiError('Não foi possível carregar o produto.', 0, 'unexpected_error')
  } finally {
    if (!request.signal.aborted) loading.value = false
  }
}
async function removeProduct() {
  if (!product.value || deleting.value) return
  const target = product.value
  if (!window.confirm(`Excluir “${target.name}”? Esta ação não pode ser desfeita.`)) return
  deleting.value = true
  deleteError.value = ''
  try {
    await prepareCsrf()
    await api.delete(`/products/${target.id}`)
    if (product.value?.id === target.id) deleted.value = true
  } catch (cause) {
    if (product.value?.id !== target.id) return
    deleteError.value = cause instanceof ApiError && cause.status === 401
      ? 'Sua sessão expirou. Entre novamente para excluir.'
      : cause instanceof ApiError && cause.status === 404 ? 'Este produto já foi removido.'
      : cause instanceof Error ? cause.message : 'Não foi possível excluir.'
    if (cause instanceof ApiError && cause.status === 401) await refreshSession()
  } finally { deleting.value = false }
}
watch(() => route.params.id, loadProduct, { immediate: true })
onBeforeUnmount(() => controller?.abort())
</script>

<template>
  <section class="product-detail" aria-label="Detalhe do produto" :aria-busy="loading">
    <RouterLink class="back-link" :to="{ name: 'home', query: route.query }">← Voltar ao catálogo</RouterLink>
    <p v-if="deleted" class="catalog-message" role="status">Produto excluído com sucesso.</p>
    <p v-else-if="loading" role="status">Carregando produto…</p>
    <div v-else-if="error" class="catalog-message" role="alert">
      <h1>{{ error.status === 404 ? 'Produto não encontrado' : 'Não foi possível carregar o produto' }}</h1>
      <p>{{ error.status === 404 ? 'Este produto não existe ou foi removido do catálogo.' : error.message }}</p>
      <button v-if="error.status !== 404" @click="loadProduct">Tentar novamente</button>
    </div>
    <div v-else-if="product" class="detail-grid">
      <ProductImage :src="product.image" :alt="product.name" />
      <div>
        <p class="eyebrow">{{ product.category }}</p>
        <h1>{{ product.name }}</h1>
        <p class="detail-price">{{ currency.format(Number(product.price)) }}</p>
        <p :class="{ error: product.stock === 0 }">{{ product.stock > 0 ? `Em estoque: ${product.stock} unidades` : 'Sem estoque' }}</p>
        <h2>Sobre o produto</h2>
        <p class="product-description">{{ product.description }}</p>
        <div v-if="status === 'authenticated'" class="filter-actions">
          <RouterLink v-if="!deleting" class="back-link" :to="{ name: 'product-edit', params: { id: product.id } }">Editar produto</RouterLink>
          <button class="danger" :disabled="deleting" @click="removeProduct">{{ deleting ? 'Excluindo…' : 'Excluir produto' }}</button>
        </div>
        <p v-if="deleteError" class="error" role="alert">{{ deleteError }}</p>
      </div>
    </div>
  </section>
</template>
