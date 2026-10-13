<script setup lang="ts">
import { onMounted, ref } from 'vue'
import CheckoutAddress, { type Address } from '../components/CheckoutAddress.vue'
import { useCart, money, cents } from '../composables/useCart'
const { items, total, loading, warning, ready, refresh, openDrawer } = useCart()
const address = ref<Address>({ recipient: '', cep: '', street: '', number: '', complement: '', district: '', city: '', state: '' })
onMounted(refresh)
</script>
<template>
  <section class="shopping-view">
    <h1>Checkout</h1><button class="secondary" @click="openDrawer">Revisar carrinho</button>
    <p v-if="!items.length" class="catalog-message">Seu carrinho está vazio.</p>
    <p v-if="warning" class="error" role="alert">{{ warning }}</p>
    <div v-if="items.length" class="shopping-grid">
      <form class="checkout-form" @submit.prevent><CheckoutAddress v-model="address" :disabled="loading" /></form>
      <aside class="purchase-summary"><h2>Resumo da compra</h2>
        <ul class="checkout-items"><li v-for="item in items" :key="item.variantId"><strong>{{ item.product?.name ?? 'Atualizando produto…' }}</strong><p>{{ item.variant?.color }} · {{ item.variant?.size }} · {{ item.quantity }} unidade(s)</p><p v-if="item.product">{{ money(cents(item.product.price) * item.quantity) }}</p></li></ul>
        <p v-if="!ready && !loading" class="error">Há itens indisponíveis. Revise o carrinho para continuar.</p><p class="summary-line"><span>Produtos</span><strong>{{ loading ? 'Atualizando…' : money(total) }}</strong></p><p>Finalização em preparação.</p></aside>
    </div>
  </section>
</template>