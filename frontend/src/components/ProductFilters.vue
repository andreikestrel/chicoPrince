<script setup lang="ts">
import { reactive, watch } from 'vue'
import type { LocationQuery } from 'vue-router'

const props = defineProps<{ query: LocationQuery }>()
const emit = defineEmits<{ apply: [filters: Record<string, string>]; clear: [] }>()
const defaults = { search: '', category: '', min_price: '', max_price: '', in_stock: false, sort: 'id', direction: 'asc', per_page: '15' }
const form = reactive({ ...defaults })
const scalar = (key: string, fallback = '') => {
  const value = props.query[key]
  return (Array.isArray(value) ? value[0] : value) ?? fallback
}
watch(() => props.query, () => {
  form.search = scalar('search')
  form.category = scalar('category')
  form.min_price = scalar('min_price')
  form.max_price = scalar('max_price')
  form.in_stock = scalar('in_stock') === '1'
  form.sort = scalar('sort', 'id')
  form.direction = scalar('direction', 'asc')
  form.per_page = scalar('per_page', '15')
}, { immediate: true })

function apply() {
  const filters: Record<string, string> = {}
  for (const key of ['search', 'category', 'min_price', 'max_price', 'sort', 'direction', 'per_page'] as const) {
    const value = String(form[key]).trim()
    if (value) filters[key] = value
  }
  if (form.in_stock) filters.in_stock = '1'
  emit('apply', filters)
}

function clear() {
  Object.assign(form, defaults)
  emit('clear')
}
</script>

<template>
  <form class="product-filters" aria-label="Filtros do catálogo" @submit.prevent="apply">
    <div class="filter-grid">
      <label class="search-field">Buscar por nome<input v-model="form.search" type="search" maxlength="255" placeholder="O que você procura?" /></label>
      <label>Categoria<input v-model="form.category" maxlength="100" placeholder="Nome da categoria" /></label>
      <label>Preço mínimo (R$)<input v-model="form.min_price" type="number" min="0" max="99999999.99" step="0.01" placeholder="0,00" /></label>
      <label>Preço máximo (R$)<input v-model="form.max_price" type="number" min="0" max="99999999.99" step="0.01" placeholder="Sem limite" /></label>
      <label>Ordenar por<select v-model="form.sort"><option value="id">Cadastro</option><option value="name">Nome</option><option value="price">Preço</option><option value="created_at">Data de criação</option></select></label>
      <label>Ordem<select v-model="form.direction"><option value="asc">Crescente</option><option value="desc">Decrescente</option></select></label>
      <label>Itens por página<input v-model="form.per_page" type="number" min="1" max="100" step="1" required /></label>
    </div>
    <div class="filter-actions">
      <label class="checkbox-field"><input v-model="form.in_stock" type="checkbox" /> Apenas em estoque</label>
      <button type="submit">Aplicar filtros</button>
      <button type="button" class="secondary" @click="clear">Limpar filtros</button>
    </div>
  </form>
</template>
