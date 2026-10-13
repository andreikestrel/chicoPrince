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
const sizes = computed(() => [...new Set(props.product.variants?.map(variant => variant.size) ?? [])])
const variant = computed(() => props.product.variants?.find(variant => variant.color === color.value && variant.size === size.value))
watch(() => props.product.id, () => { color.value = ''; size.value = ''; error.value = '' })
watch(color, () => { size.value = ''; error.value = '' })
function sizeAvailable(option: string) {
  return props.product.variants?.some(item => item.color === color.value && item.size === option && item.stock > 0) ?? false
}
function colorAvailable(option: string) {
  return props.product.variants?.some(item => item.color === option && item.stock > 0) ?? false
}
function buy() {
  if (!variant.value) { error.value = 'Selecione a cor e o tamanho.'; return }
  error.value = cart.add(props.product, variant.value)
  if (!error.value) cart.openDrawer()
}
</script>
<template>
  <form v-if="colors.length" class="variant-selector" @submit.prevent="buy">
    <fieldset class="variant-options">
      <legend>Cor</legend>
      <div class="variant-buttons">
        <button v-for="option in colors" :key="option" type="button"
          class="variant-choice" :class="{ 'image-color': option.toUpperCase() === 'COR DA IMAGEM' }"
          :aria-label="option.toUpperCase() === 'COR DA IMAGEM' ? 'Cor da imagem' : option"
          :title="option.toUpperCase() === 'COR DA IMAGEM' ? 'Cor da imagem' : option"
          :aria-pressed="color === option" :disabled="!colorAvailable(option)" @click="color = option">
          <span v-if="option.toUpperCase() !== 'COR DA IMAGEM'">{{ option }}</span>
          <svg v-else viewBox="0 0 32 32" width="30" height="30" aria-hidden="true">
            <path d="M6 16 16 6" stroke="#a95d50" stroke-width="5" />
            <path d="M7 25 25 7" stroke="#6d8973" stroke-width="5" />
            <path d="M16 26 26 16" stroke="#dfb665" stroke-width="5" />
          </svg>
        </button>
      </div>
    </fieldset>
    <fieldset class="variant-options">
      <legend>Tamanho</legend>
      <div class="variant-buttons">
        <button v-for="option in sizes" :key="option" type="button" class="variant-choice"
          :aria-label="'Tamanho ' + option" :title="!color ? 'Selecione uma cor' : sizeAvailable(option) ? option : option + ' indisponível'"
          :aria-pressed="size === option" :disabled="!sizeAvailable(option)" @click="size = option">{{ option }}</button>
      </div>
    </fieldset>
    <p v-if="variant">{{ variant.stock ? `Disponível: ${variant.stock} unidade(s)` : 'Esta combinação está sem estoque.' }}</p>
    <button type="submit" :disabled="!variant || variant.stock < 1">Comprar</button>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
  </form>
  <p v-else>Compra indisponível: este produto ainda não possui opções de cor e tamanho.</p>
</template>
<style scoped>
.variant-options { border: 0; padding: 0; margin: 0; min-width: 0; }
.variant-options legend { margin-bottom: 8px; }
.variant-buttons { display: flex; flex-wrap: wrap; gap: 10px; }
.variant-choice { width: 48px; height: 48px; min-width: 48px; padding: 3px; border-radius: 50%; border: 1px solid #aab6a5; background: transparent; color: inherit; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; overflow-wrap: anywhere; }
.variant-choice[aria-pressed="true"] { border: 2px solid #233f35; box-shadow: 0 0 0 2px #233f3526; background: #e5ebdf; }
.variant-choice:disabled { opacity: .3; cursor: not-allowed; }
.variant-choice:focus-visible { outline: 3px solid #233f35; outline-offset: 3px; }
</style>