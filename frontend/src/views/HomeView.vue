<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import CatalogSort from '../components/CatalogSort.vue'
import CouponNotice from '../components/CouponNotice.vue'
import CatalogCarousel from '../components/CatalogCarousel.vue'
import { useCategories, catalogLocation } from '../composables/useCategories'
import { useProducts } from '../composables/useProducts'
import { useRoute, useRouter, type LocationQuery } from 'vue-router'
import ProductPagination from '../components/ProductPagination.vue'
import ProductCard from '../components/ProductCard.vue'
import ProductFilters from '../components/ProductFilters.vue'

const route = useRoute()
const router = useRouter()
const hasSearch = computed(() => {
  const value = route.query.search
  return Boolean((Array.isArray(value) ? value[0] : value)?.trim())
})
const { categories, load } = useCategories()
onMounted(() => load(true))
const filters = computed<LocationQuery>(() => {
  const query: LocationQuery = { sort: 'name', direction: 'asc', ...route.query }
  if (route.params.categorySlug) {
    delete query.category
    query.category_slug = String(route.params.categorySlug)
  }
  return query
})
const filterForm = computed(() => ({ sort: 'name', direction: 'asc', ...route.query, ...(route.params.categorySlug ? { category: categories.value.find(category => category.slug === route.params.categorySlug)?.name ?? String(route.params.categorySlug) } : {}) }))
const { result, loading, error, fetchProducts } = useProducts(ref(true), filters)

async function applyFilters(query: Record<string, string>) {
  await load()
  router.push(catalogLocation(query))
}
function changeSort(sort: string, direction: string) {
  const query = { ...route.query, sort, direction }
  delete (query as LocationQuery).page
  router.push({
    name: route.params.categorySlug ? 'category' : 'home',
    params: route.params.categorySlug ? { categorySlug: route.params.categorySlug } : {},
    query,
  })
}
function changePage(page: number) {
  router.push({ name: route.params.categorySlug ? 'category' : 'home', params: route.params.categorySlug ? { categorySlug: route.params.categorySlug } : {}, query: { ...route.query, page: String(page) } })
}
</script>

<template>
  <section v-if="!hasSearch" class="catalog-intro" aria-labelledby="catalog-title">
    <h1 id="catalog-title">Vista sua própria história.</h1>
    <p class="description">Todo príncipe e toda princesa têm seu estilo.<br />O seu começa aqui.</p>
    <p class="description">Explore nosso catálogo e encontre peças dignas da sua personalidade.</p>
  </section>
  <CouponNotice />
  <CatalogCarousel v-if="!hasSearch" />
  <ProductFilters :query="filterForm" :show-sorting="false" @apply="applyFilters" @clear="applyFilters({})" />
  <CatalogSort :query="route.query" @change="changeSort" />
  <section class="catalog-results" aria-label="Produtos" :aria-busy="loading">
    <p v-if="loading && !result" class="catalog-message" role="status">Carregando produtos…</p>
    <div v-else-if="error" class="catalog-message" role="alert">
      <h2>{{ error.status === 404 ? 'Categoria não encontrada.' : 'Não foi possível carregar os produtos' }}</h2>
      <p>{{ error.message }}</p>
      <ul v-if="Object.keys(error.details).length"><li v-for="(messages, field) in error.details" :key="field">{{ messages.join(' ') }}</li></ul>
      <button @click="fetchProducts">Tentar novamente</button>
    </div>
    <template v-else-if="result">
      <p v-if="loading" role="status">Atualizando produtos…</p>
      <p class="result-count" role="status">{{ result.meta.from !== null ? `${result.meta.from}–${result.meta.to} de ${result.meta.total} produtos` : `${result.meta.total} produtos encontrados` }}</p>
      <div v-if="result.data.length" class="product-grid" :aria-busy="loading">
        <ProductCard v-for="product in result.data" :key="product.id" :product="product" />
      </div>
      <div v-else class="catalog-message">
        <h2>{{ result.meta.total ? 'Esta página não tem produtos.' : 'Nenhum produto encontrado.' }}</h2>
        <p>Experimente ajustar os filtros ou voltar ao início do catálogo.</p>
        <button v-if="result.meta.total" @click="changePage(1)">Voltar à primeira página</button>
        <button v-else @click="applyFilters({})">Ver todos os produtos</button>
      </div>
      <ProductPagination v-if="result.data.length" :meta="result.meta" :disabled="loading" @change="changePage" />
    </template>
  </section>
</template>
