<script setup lang="ts">
import { computed } from 'vue'


import { useRoute } from 'vue-router'
import ProductImage from './ProductImage.vue'
import type { Product } from '../types/products'

defineProps<{ product: Product }>()
const route = useRoute()

const catalogQuery = computed(() => ({ ...route.query, ...(route.params.categorySlug ? { category_slug: String(route.params.categorySlug) } : {}) }))
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
</script>

<template>
  <article class="product-card">
    <ProductImage :src="product.image" :alt="product.name" />
    <div class="product-info">
      <p class="product-category">{{ product.category }}</p>
      <h2><RouterLink :to="{ name: 'product', params: { id: product.id }, query: catalogQuery }">{{ product.name }}</RouterLink></h2>
      <p class="product-price">{{ currency.format(Number(product.price)) }}</p>
      <p class="product-stock" :class="{ error: product.stock === 0 }">{{ product.stock > 0 ? 'Em estoque' : 'Sem estoque' }}</p>

    </div>
  </article>
</template>
