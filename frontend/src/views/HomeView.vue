<script setup lang="ts">
import { useProducts } from '../composables/useProducts'
import { useRoute, useRouter } from 'vue-router'
import ProductPagination from '../components/ProductPagination.vue'
import ProductCard from '../components/ProductCard.vue'
import ProductFilters from '../components/ProductFilters.vue'

const route = useRoute()
const router = useRouter()
const { result, loading, error, fetchProducts } = useProducts()

function applyFilters(query: Record<string, string>) {
  router.push({ name: 'home', query })
}
function changePage(page: number) {
  router.push({ name: 'home', query: { ...route.query, page: String(page) } })
}
</script>

<template>
  <section class="catalog-intro" aria-labelledby="catalog-title">
    <p class="eyebrow">Encontre o seu próximo favorito</p>
    <h1 id="catalog-title">Pequenas escolhas.<br />Muita personalidade.</h1>
    <p class="description">Explore nosso catálogo e encontre algo que combine com você.</p>
  </section>
  <ProductFilters :query="route.query" @apply="applyFilters" @clear="applyFilters({})" />
  <section class="catalog-results" aria-label="Produtos" :aria-busy="loading">
    <p v-if="loading && !result" class="catalog-message" role="status">Carregando produtos…</p>
    <div v-else-if="error" class="catalog-message" role="alert">
      <h2>Não foi possível carregar os produtos</h2>
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
