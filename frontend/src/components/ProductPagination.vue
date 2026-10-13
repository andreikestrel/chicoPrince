<script setup lang="ts">
import type { ProductPage } from '../types/products'
defineProps<{ meta: ProductPage['meta']; disabled?: boolean }>()
defineEmits<{ change: [page: number] }>()
</script>
<template>
  <nav v-if="meta.last_page > 1" class="pagination" aria-label="Paginação de produtos">
    <button class="secondary" :disabled="disabled || meta.current_page <= 1" @click="$emit('change', meta.current_page - 1)">Anterior</button>
    <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
    <button class="secondary" :disabled="disabled || meta.current_page >= meta.last_page" @click="$emit('change', meta.current_page + 1)">Próxima</button>
  </nav>
</template>
