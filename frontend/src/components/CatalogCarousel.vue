<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { api } from '../lib/api'
import { catalogLocation, useCategories } from '../composables/useCategories'
import type { Banner } from '../types/banners'
const banners = ref<Banner[]>([])
const index = ref(0)
const loading = ref(false)
const error = ref('')
const failed = ref(false)
const { load: loadCategories, categories } = useCategories()
const available = computed(() => banners.value.filter(banner => categories.value.some(category => category.name === banner.category)))
const current = computed(() => available.value[index.value])
watch(current, () => { failed.value = false })
async function load() {
  loading.value = true
  error.value = ''
  try {
    const [response] = await Promise.all([api.get<{ data: Banner[] }>('/banners'), loadCategories()])
    banners.value = response.data.data
    index.value = 0
  } catch { error.value = 'Não foi possível carregar os destaques.' }
  finally { loading.value = false }
}
function move(direction: number) {
  if (available.value.length) index.value = (index.value + direction + available.value.length) % available.value.length
}
onMounted(load)
</script>
<template>
  <section v-if="current" class="catalog-carousel" aria-label="Destaques do catálogo" aria-roledescription="carrossel">
    <RouterLink class="banner-slide" :to="catalogLocation({ category: current.category })">
      <img v-if="!failed" :src="current.image" alt="" referrerpolicy="no-referrer" decoding="async" @error="failed = true" />
      <div class="banner-caption"><h2>{{ current.title }}</h2><span>Ver {{ current.category }} →</span></div>
    </RouterLink>
    <div v-if="available.length > 1" class="carousel-controls">
      <button type="button" class="secondary" aria-label="Banner anterior" @click="move(-1)">←</button>
      <span aria-live="polite">{{ index + 1 }} de {{ available.length }}</span>
      <button type="button" class="secondary" aria-label="Próximo banner" @click="move(1)">→</button>
    </div>
  </section>
  <p v-else-if="error" class="carousel-error" role="status">{{ error }} <button class="text-button" :disabled="loading" @click="load">Tentar novamente</button></p>
</template>