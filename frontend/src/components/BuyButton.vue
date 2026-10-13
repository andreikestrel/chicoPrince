<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useCart } from '../composables/useCart'
import type { Product } from '../types/products'
const props = defineProps<{ product: Product }>()
const cart = useCart()
const error = ref('')
const color = ref('')
const size = ref('')
const colors = computed(() => [...new Set(props.product.variants?.map(variant => variant.color) ?? [])])
const sizes = computed(() => [...new Set(props.product.variants?.filter(variant => variant.color === color.value).map(variant => variant.size) ?? [])])
const variant = computed(() => props.product.variants?.find(variant => variant.color === color.value && variant.size === size.value))
watch(() => props.product.id, () => { color.value = ''; size.value = ''; error.value = '' })
watch(color, () => { size.value = ''; error.value = '' })
function buy() {
  if (!variant.value) { error.value = 'Selecione a cor e o tamanho.'; return }
  error.value = cart.add(props.product, variant.value)
  if (!error.value) cart.openDrawer()
}
</script>
<template>
  <form v-if="colors.length" class="variant-selector" @submit.prevent="buy">
    <label>Cor<select v-model="color" required><option disabled value="">Selecione a cor</option><option v-for="option in colors" :key="option">{{ option }}</option></select></label>
    <label>Tamanho<select v-model="size" :disabled="!color" required><option disabled value="">Selecione o tamanho</option><option v-for="option in sizes" :key="option">{{ option }}</option></select></label>
    <p v-if="variant">{{ variant.stock ? `Disponível: ${variant.stock} unidade(s)` : 'Esta combinação está sem estoque.' }}</p>
    <button type="submit" :disabled="!variant || variant.stock < 1">Comprar</button>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
  </form>
  <p v-else>Compra indisponível: este produto ainda não possui opções de cor e tamanho.</p>
</template>