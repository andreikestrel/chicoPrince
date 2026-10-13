<script setup lang="ts">
import { computed } from 'vue'
import type { LocationQuery } from 'vue-router'

const props = defineProps<{ query: LocationQuery }>()
const emit = defineEmits<{ change: [sort: string, direction: string] }>()
const scalar = (key: string, fallback: string) => {
  const value = props.query[key]
  return (Array.isArray(value) ? value[0] : value) || fallback
}
const value = computed(() => scalar('sort', 'id') + ':' + scalar('direction', 'asc'))
const options = [
  { value: 'id:asc', label: 'Cadastro' },
  { value: 'name:asc', label: 'Nome: A → Z' },
  { value: 'name:desc', label: 'Nome: Z → A' },
  { value: 'price:asc', label: 'Preço: menor → maior' },
  { value: 'price:desc', label: 'Preço: maior → menor' },
]
function change(event: Event) {
  const [sort = 'id', direction = 'asc'] = (event.target as HTMLSelectElement).value.split(':')
  emit('change', sort, direction)
}
</script>

<template>
  <div class="catalog-sort">
    <label>Ordenar por
      <select :value="value" @change="change">
        <option v-if="!options.some(option => option.value === value)" :value="value" disabled>Ordenação atual</option>
        <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
    </label>
  </div>
</template>