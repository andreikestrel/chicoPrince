<script setup lang="ts">
import { reactive, ref, useId, watch } from 'vue'
import type { LocationQuery } from 'vue-router'
import CategoryInput from './CategoryInput.vue'

const props = withDefaults(defineProps<{ query: LocationQuery; showSorting?: boolean }>(), { showSorting: true })
const emit = defineEmits<{ apply: [filters: Record<string, string>]; clear: [] }>()
const defaults = { search: '', category: '', min_price: '', max_price: '', in_stock: false, sort: 'id', direction: 'asc', per_page: '15' }
const form = reactive({ ...defaults })
const advanced = ref(false)
const id = useId()
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
    if (value && value !== String(defaults[key])) filters[key] = value
  }
  if (form.in_stock) filters.in_stock = '1'
  emit('apply', filters)
}
function clear() { Object.assign(form, defaults); emit('clear') }
</script>
<template>
  <form class="product-filters compact-filters" aria-label="Filtros do catálogo" @submit.prevent="apply">
    <div class="quick-search">
      <input v-model="form.search" aria-label="Buscar produtos por nome" type="search" maxlength="255" placeholder="O que você procura?" />
      <button type="submit">Buscar</button>
      <button type="button" class="secondary" :aria-expanded="advanced" :aria-controls="id" @click="advanced = !advanced">{{ advanced ? 'Fechar busca avançada' : 'Busca avançada' }}</button>
    </div>
    <div v-show="advanced" :id="id" class="advanced-filters">
      <div class="filter-grid">
        <CategoryInput v-model="form.category" />
        <label>Preço mínimo (R$)<input v-model="form.min_price" type="number" min="0" max="99999999.99" step="0.01" placeholder="0,00" /></label>
        <label>Preço máximo (R$)<input v-model="form.max_price" type="number" min="0" max="99999999.99" step="0.01" placeholder="Sem limite" /></label>
        <label v-if="showSorting">Ordenar por<select v-model="form.sort"><option value="id">Cadastro</option><option value="name">Nome</option><option value="price">Preço</option><option value="category">Categoria</option><option value="created_at">Data de criação</option><option value="updated_at">Data de atualização</option></select></label>
        <label v-if="showSorting">Ordem<select v-model="form.direction"><option value="asc">Crescente</option><option value="desc">Decrescente</option></select></label>
        <label>Itens por página<input v-model="form.per_page" type="number" min="1" max="100" step="1" required /></label>
      </div>
      <div class="filter-actions">
        <label class="checkbox-field"><input v-model="form.in_stock" type="checkbox" /> Apenas em estoque</label>
        <button type="submit">Aplicar filtros</button><button type="button" class="secondary" @click="clear">Limpar filtros</button>
      </div>
    </div>
    <p v-if="!advanced && (form.category || form.min_price || form.max_price || form.in_stock || form.sort !== 'id' || form.direction !== 'asc' || form.per_page !== '15')" class="active-filter-hint">Filtros ativos{{ form.category ? `: ${form.category}` : '' }}. <button type="button" class="text-button" @click="clear">Limpar</button></p>
  </form>
</template>