<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '../lib/api'
import ProductCard from '../components/ProductCard.vue'
import ProductFilters from '../components/ProductFilters.vue'
import type { ProductPage } from '../types/products'

const route = useRoute()
const router = useRouter()
const result = ref<ProductPage | null>(null)
const loading = ref(false)
const error = ref<ApiError | null>(null)
let controller: AbortController | undefined

async function fetchProducts() {
  controller?.abort()
  const request = new AbortController()
  controller = request
  loading.value = true
  error.value = null
  result.value = null
  const params: Record<string, string> = {}
  for (const key of ['search', 'category', 'min_price', 'max_price', 'in_stock', 'sort', 'direction', 'per_page', 'page']) {
    const value = route.query[key]
    const first = Array.isArray(value) ? value[0] : value
    if (first != null) params[key] = first
  }
  try {
    const response = await api.get<ProductPage>('/products', { params, signal: request.signal })
    if (!request.signal.aborted) result.value = response.data
  } catch (failure) {
    if (!request.signal.aborted) error.value = failure instanceof ApiError ? failure : new ApiError('Não foi possível carregar o catálogo.', 0, 'unexpected_error')
  } finally {
    if (!request.signal.aborted) loading.value = false
  }
}

function applyFilters(query: Record<string, string>) {
  router.push({ name: 'home', query })
}
function changePage(page: number) {
  router.push({ name: 'home', query: { ...route.query, page: String(page) } })
}
watch(() => route.query, fetchProducts, { immediate: true })
onBeforeUnmount(() => controller?.abort())
</script>

<template>
  <section class="catalog-intro" aria-labelledby="catalog-title">
    <p class="eyebrow">Encontre o seu próximo favorito</p>
    <h1 id="catalog-title">Pequenas escolhas.<br />Muita personalidade.</h1>
    <p class="description">Explore nosso catálogo e encontre algo que combine com você.</p>
  </section>
  <ProductFilters :query="route.query" @apply="applyFilters" @clear="applyFilters({})" />
  <section class="catalog-results" aria-label="Produtos" :aria-busy="loading">
    <p v-if="loading" class="catalog-message" role="status">Carregando produtos…</p>
    <div v-else-if="error" class="catalog-message" role="alert">
      <h2>Não foi possível carregar os produtos</h2>
      <p>{{ error.message }}</p>
      <ul v-if="Object.keys(error.details).length"><li v-for="(messages, field) in error.details" :key="field">{{ messages.join(' ') }}</li></ul>
      <button @click="fetchProducts">Tentar novamente</button>
    </div>
    <template v-else-if="result">
      <p class="result-count" role="status">{{ result.meta.total ? `${result.meta.from ?? 0}–${result.meta.to ?? 0} de ${result.meta.total} produtos` : '0 produtos' }}</p>
      <div v-if="result.data.length" class="product-grid">
        <ProductCard v-for="product in result.data" :key="product.id" :product="product" />
      </div>
      <div v-else class="catalog-message">
        <h2>{{ result.meta.total ? 'Esta página não tem produtos.' : 'Nenhum produto encontrado.' }}</h2>
        <p>Experimente ajustar os filtros ou voltar ao início do catálogo.</p>
        <button v-if="result.meta.total" @click="changePage(1)">Voltar à primeira página</button>
        <button v-else @click="applyFilters({})">Ver todos os produtos</button>
      </div>
      <nav v-if="result.meta.last_page > 1 && result.data.length" class="pagination" aria-label="Paginação do catálogo">
        <button class="secondary" :disabled="result.meta.current_page <= 1" @click="changePage(result.meta.current_page - 1)">Anterior</button>
        <span>Página {{ result.meta.current_page }} de {{ result.meta.last_page }}</span>
        <button class="secondary" :disabled="result.meta.current_page >= result.meta.last_page" @click="changePage(result.meta.current_page + 1)">Próxima</button>
      </nav>
    </template>
  </section>
</template>