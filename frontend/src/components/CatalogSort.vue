<script setup lang="ts">
import { computed } from 'vue'
import type { LocationQuery } from 'vue-router'

const props = defineProps<{ query: LocationQuery }>()
const emit = defineEmits<{ change: [sort: string, direction: string] }>()
const scalar = (key: string, fallback: string) => {
  const value = props.query[key]
  return (Array.isArray(value) ? value[0] : value) || fallback
}
const value = computed(() => scalar('sort', 'name') + ':' + scalar('direction', 'asc'))
const options = [
  { value: 'name:asc', label: 'Nome: A → Z' },
  { value: 'name:desc', label: 'Nome: Z → A' },
  { value: 'price:asc', label: 'Preço: menor → maior' },
  { value: 'price:desc', label: 'Preço: maior → menor' },
]
function change(event: Event) {
  const [sort = 'name', direction = 'asc'] = (event.target as HTMLSelectElement).value.split(':')
  emit('change', sort, direction)
}
</script>

<template>
  <div class="catalog-sort">
    <label><span class="sort-label">Ordenar por</span><span class="sort-control">
      <svg class="sort-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 4v16m-4-4 4 4 4-4M16 20V4m-4 4 4-4 4 4" /></svg>
      <select :value="value" @change="change">
        <option v-if="!options.some(option => option.value === value)" :value="value" disabled>Ordenação atual</option>
        <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
      <svg class="sort-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg></span>
    </label>
  </div>
</template>
<style scoped>
.catalog-sort label { gap: 10px; }
.sort-label { color: #536454; font-size: 12px; }
.sort-control { position: relative; display: inline-flex; min-width: 0; }
.sort-control select { appearance: none; cursor: pointer; border: 1px solid #c7d0c2; border-radius: 999px; padding: 12px 38px; background: #fafaf4; color: #233f35; font-weight: 600; font-size: 13px; min-height: 44px; transition: border-color .15s, box-shadow .15s; }
.sort-control select:hover { border-color: #61745e; }
.sort-control select:focus-visible { outline: 2px solid #233f35; outline-offset: 3px; }
.sort-icon, .sort-chevron { position: absolute; top: 50%; transform: translateY(-50%); pointer-events: none; color: #536454; }
.sort-icon { left: 13px; }
.sort-chevron { right: 13px; }
@media (max-width: 480px) { .sort-control { flex: 1; } .sort-control select { width: 100%; } }
</style>