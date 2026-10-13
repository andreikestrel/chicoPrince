<script setup lang="ts">
import { ref, watch } from 'vue'
import type { Product } from '../types/products'

const props = defineProps<{ product: Product }>()
const imageFailed = ref(false)
watch(() => props.product.image, () => { imageFailed.value = false })
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
</script>

<template>
  <article class="product-card">
    <div class="product-image">
      <img v-if="product.image && !imageFailed" :src="product.image" :alt="product.name" loading="lazy" @error="imageFailed = true" />
      <div v-else class="image-placeholder">
        <span aria-hidden="true">CP</span>
        <small>Imagem indisponível</small>
      </div>
    </div>
    <div class="product-info">
      <p class="product-category">{{ product.category }}</p>
      <h2>{{ product.name }}</h2>
      <p class="product-price">{{ currency.format(Number(product.price)) }}</p>
      <p class="product-stock" :class="{ error: product.stock === 0 }">{{ product.stock > 0 ? 'Em estoque' : 'Sem estoque' }}</p>
    </div>
  </article>
</template>