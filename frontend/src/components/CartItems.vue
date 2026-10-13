<script setup lang="ts">
import ProductImage from './ProductImage.vue'
import { useCart, cents, money } from '../composables/useCart'
const { items, loading, quantity, remove } = useCart()
</script>
<template>
      <div class="cart-items">
        <article v-for="item in items" :key="item.variantId" class="cart-item">
          <ProductImage :src="item.product?.image ?? null" :alt="item.product?.name ?? 'Produto'" />
          <div class="cart-item-info">
            <h2><RouterLink :to="`/produtos/${item.id}`">{{ item.product?.name ?? `Produto #${item.id}` }}</RouterLink></h2>
            <p>{{ item.product?.category }} · {{ item.variant?.color }} · {{ item.variant?.size }}</p>
            <p v-if="item.unavailable" class="error">Produto indisponível ou não foi possível consultá-lo. Remova ou tente atualizar.</p>
            <p v-else-if="item.variant && item.quantity > item.variant.stock" class="error">Estoque disponível: {{ item.variant.stock }}. Ajuste a quantidade ou remova o item.</p>
            <div class="quantity-control">
              <button class="secondary" :aria-label="`Diminuir quantidade de ${item.product?.name ?? item.id}, ${item.variant?.color ?? ''}, ${item.variant?.size ?? ''}`" :disabled="loading || item.quantity <= 1 || !item.variant?.stock" @click="quantity(item.variantId, Math.min(item.quantity - 1, item.variant?.stock ?? 0))">−</button>
              <span :aria-label="`Quantidade: ${item.quantity}`">{{ item.quantity }}</span>
              <button class="secondary" :aria-label="`Aumentar quantidade de ${item.product?.name ?? item.id}, ${item.variant?.color ?? ''}, ${item.variant?.size ?? ''}`" :disabled="loading || item.unavailable || item.quantity >= Math.min(item.variant?.stock ?? 0, 99)" @click="quantity(item.variantId, item.quantity + 1)">+</button>
            </div>
            <p v-if="item.product">{{ money(cents(item.product.price)) }} cada · <strong>{{ money(cents(item.product.price) * item.quantity) }}</strong></p>
          </div>
          <button class="cart-remove secondary" :aria-label="`Remover ${item.product?.name ?? item.id}, ${item.variant?.color ?? ''}, ${item.variant?.size ?? ''}`" title="Remover produto" :disabled="loading" @click="remove(item.variantId)"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6h18 M9 6V3h6v3 M5 6l1 15h12l1-15 M10 10v7 M14 10v7" /></svg></button>
        </article>
      </div>
</template>