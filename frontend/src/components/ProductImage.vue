<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = defineProps<{ src: string | null; alt: string }>()
const failed = ref(false)
const safeSrc = computed(() => {
  if (!props.src) return null
  try {
    const url = new URL(props.src)
    return ['http:', 'https:'].includes(url.protocol) ? url.href : null
  } catch { return null }
})
watch(() => props.src, () => { failed.value = false })
</script>

<template>
  <div class="product-image">
    <img v-if="safeSrc && !failed" :src="safeSrc" :alt="alt" loading="lazy" decoding="async" referrerpolicy="no-referrer" @error="failed = true" />
    <div v-else class="image-placeholder">
      <span aria-hidden="true">CP</span>
      <small>Imagem indisponível</small>
    </div>
  </div>
</template>
