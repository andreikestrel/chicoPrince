<script setup lang="ts">
import BannerManager from '../components/BannerManager.vue'
import { computed, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import { useProducts } from '../composables/useProducts'
import { api, ApiError, prepareCsrf } from '../lib/api'
import ProductFilters from '../components/ProductFilters.vue'
import ProductImage from '../components/ProductImage.vue'
import ProductPagination from '../components/ProductPagination.vue'
import SortableHeader from '../components/SortableHeader.vue'

const route = useRoute()
const router = useRouter()
const { status, refreshSession } = useAuth()
const authenticated = computed(() => status.value === 'authenticated')
const { result, loading, error, fetchProducts } = useProducts(authenticated)
const selected = ref<number[]>([])
const inspecting = ref(false)
const busy = ref(false)
const notice = ref('')
const failures = ref<string[]>([])
const rows = computed(() => result.value?.data ?? [])
const selectedProducts = computed(() => rows.value.filter(product => selected.value.includes(product.id)))
const allSelected = computed(() => rows.value.length > 0 && selectedProducts.value.length === rows.value.length)
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
const date = (value: string | null) => value ? new Date(value).toLocaleString('pt-BR') : '—'

watch(() => route.query, () => { selected.value = []; inspecting.value = false; notice.value = ''; failures.value = [] })
onBeforeRouteLeave(() => !busy.value)

function apply(query: Record<string, string>) {
  if (!busy.value) router.push({ name: 'admin-products', query })
}
function page(value: number) {
  if (!busy.value) router.push({ name: 'admin-products', query: { ...route.query, page: String(value) } })
}
const sort = computed(() => route.query.sort ?? 'id')
const direction = computed(() => route.query.direction ?? 'asc')
function orderBy(column: string) {
  if (busy.value || loading.value) return
  const query = { ...route.query, page: undefined, sort: column, direction: sort.value === column && direction.value === 'asc' ? 'desc' : 'asc' }
  router.push({ name: 'admin-products', query })
}
function selectPage() {
  selected.value = allSelected.value ? [] : rows.value.map(product => product.id)
}
async function remove(ids: number[]) {
  if (busy.value || loading.value || !ids.length) return
  const targets = rows.value.filter(product => ids.includes(product.id))
  if (!targets.length || !window.confirm(`Excluir ${targets.length} produto(s)?\n${targets.map(product => `#${product.id} — ${product.name}`).join('\n')}\nEsta ação não pode ser desfeita.`)) return
  busy.value = true
  failures.value = []
  notice.value = 'Excluindo produtos…'
  let removed = 0
  const remaining = new Set(targets.map(product => product.id))
  try {
    await prepareCsrf()
    // Serial para limitar carga; cada DELETE possui seu próprio resultado.
    for (const product of targets) {
      try {
        await api.delete(`/products/${product.id}`)
        removed++
        remaining.delete(product.id)
      } catch (cause) {
        if (cause instanceof ApiError && cause.status === 404) {
          remaining.delete(product.id)
          failures.value.push(`#${product.id}: já não existe no catálogo.`)
          continue
        }
        failures.value.push(`#${product.id}: ${cause instanceof Error ? cause.message : 'Falha ao excluir.'}`)
        if (cause instanceof ApiError && [401, 419].includes(cause.status)) {
          if (cause.status === 401) await refreshSession()
          break
        }
      }
    }
  } catch (cause) {
    failures.value.push(cause instanceof Error ? cause.message : 'Não foi possível preparar a exclusão.')
  } finally {
    notice.value = `${removed} produto(s) excluído(s).${remaining.size ? ` ${remaining.size} pendente(s); selecione Excluir selecionados para tentar novamente.` : ''}`
    await fetchProducts()
    selected.value = [...remaining].filter(id => rows.value.some(product => product.id === id))
    busy.value = false
  }
}
</script>

<template>
  <section class="admin-products" aria-labelledby="admin-title">
    <p class="eyebrow">Gerenciamento</p>
    <h1 id="admin-title">Produtos</h1>
    <p v-if="status === 'loading'" role="status">Consultando sua sessão…</p>
    <div v-else-if="!authenticated" class="catalog-message">
      <p>Entre na sua conta para gerenciar produtos.</p>
      <RouterLink to="/login">Entrar na conta</RouterLink>
      <button v-if="status === 'error'" @click="refreshSession">Verificar sessão</button>
    </div>
    <template v-else>
      <BannerManager />
      <RouterLink v-if="!busy" class="button-link" :to="{ name: 'product-create', query: route.query }">Criar produto</RouterLink>
      <fieldset class="filter-fieldset" :disabled="busy"><legend class="sr-only">Filtrar produtos</legend><ProductFilters :query="route.query" :show-sorting="false" @apply="apply" @clear="apply({})" /></fieldset>
      <p v-if="notice" class="catalog-message" role="status">{{ notice }}</p>
      <ul v-if="failures.length" class="error" role="alert"><li v-for="failure in failures" :key="failure">{{ failure }}</li></ul>
      <p v-if="loading" role="status">{{ result ? 'Atualizando produtos…' : 'Carregando produtos…' }}</p>
      <div v-if="error" class="catalog-message" role="alert">
        <p>{{ error.message }}</p>
        <ul><li v-for="(messages, field) in error.details" :key="field">{{ messages.join(' ') }}</li></ul>
        <button :disabled="busy" @click="fetchProducts">Tentar novamente</button>
      </div>
      <template v-else-if="result">
        <p role="status">{{ result.meta.total }} produto(s) encontrados. {{ selectedProducts.length }} selecionado(s) nesta página.</p>
        <div class="filter-actions">
          <button class="secondary" :disabled="!selectedProducts.length || busy || loading" @click="inspecting = !inspecting">{{ inspecting ? 'Fechar visualização' : 'Visualizar selecionados' }}</button>
          <button class="danger" :disabled="!selectedProducts.length || busy || loading" @click="remove(selected)">Excluir selecionados</button>
        </div>
        <p id="table-help">A seleção se limita à página atual. Role a tabela horizontalmente para ver todas as colunas.</p>
        <div class="table-scroll" role="region" aria-label="Tabela de produtos" aria-describedby="table-help" tabindex="0" :aria-busy="loading || busy">
          <table>
            <caption class="sr-only">Produtos cadastrados e ações de gerenciamento</caption>
            <thead><tr>
              <th scope="col"><input type="checkbox" aria-label="Selecionar todos desta página" :checked="allSelected" :indeterminate="selectedProducts.length > 0 && !allSelected" :disabled="busy || loading || !rows.length" @change="selectPage" /></th>
              <SortableHeader class="product-id-column" column="id" label="ID" :sort="sort" :direction="direction" :disabled="busy || loading" @sort="orderBy" /><th scope="col">Imagem</th><SortableHeader column="name" label="Nome" :sort="sort" :direction="direction" :disabled="busy || loading" @sort="orderBy" /><th scope="col">Descrição</th><SortableHeader column="price" label="Preço" :sort="sort" :direction="direction" :disabled="busy || loading" @sort="orderBy" /><SortableHeader column="category" label="Categoria" :sort="sort" :direction="direction" :disabled="busy || loading" @sort="orderBy" /><th scope="col">Estoque</th><SortableHeader column="created_at" label="Criado" :sort="sort" :direction="direction" :disabled="busy || loading" @sort="orderBy" /><SortableHeader column="updated_at" label="Atualizado" :sort="sort" :direction="direction" :disabled="busy || loading" @sort="orderBy" /><th scope="col" class="product-actions-column"><span class="sr-only">Ações</span></th>
            </tr></thead>
            <tbody><tr v-for="product in rows" :key="product.id">
              <td><input v-model="selected" type="checkbox" :value="product.id" :aria-label="`Selecionar ${product.name}`" :disabled="busy || loading" /></td>
              <td class="product-id-column">{{ product.id }}</td><td><ProductImage :src="product.image" :alt="product.name" /></td>
              <th scope="row">{{ product.name }}</th>
              <td><details><summary>Ler descrição</summary><p class="product-description">{{ product.description }}</p></details></td>
              <td class="nowrap">{{ currency.format(Number(product.price)) }}</td><td>{{ product.category }}</td><td>{{ product.stock }}</td>
              <td>{{ date(product.created_at) }}</td><td>{{ date(product.updated_at) }}</td>
              <td class="product-actions-column"><div class="row-actions"><RouterLink v-if="!busy && !loading" :to="{ name: 'product-edit', params: { id: product.id }, query: route.query }" class="icon-button" :aria-label="`Editar ${product.name}`" title="Editar produto">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m16 3 5 5-13 13H3v-5L16 3Z M13 6l5 5" /></svg>
              </RouterLink><button class="danger icon-button" title="Excluir produto" :aria-label="`Excluir ${product.name}`" :disabled="busy || loading" @click="remove([product.id])"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18 M9 6V3h6v3 M5 6l1 15h12l1-15 M10 10v7 M14 10v7" /></svg></button></div></td>
            </tr></tbody>
          </table>
        </div>
        <div v-if="!rows.length && !loading" class="catalog-message"><p>Nenhum produto nesta página.</p><button v-if="result.meta.total" @click="page(1)">Voltar à primeira página</button><button v-else @click="apply({})">Limpar filtros</button></div>
        <ProductPagination v-if="rows.length" :meta="result.meta" :disabled="busy || loading" @change="page" />
        <section v-if="inspecting && selectedProducts.length" class="selected-products" aria-label="Produtos selecionados">
          <article v-for="product in selectedProducts" :key="product.id" class="catalog-message">
            <ProductImage :src="product.image" :alt="product.name" /><h2>{{ product.name }}</h2>
            <p>{{ product.category }} · {{ currency.format(Number(product.price)) }} · Estoque: {{ product.stock }}</p>
            <p class="product-description">{{ product.description }}</p>
          </article>
        </section>
      </template>
    </template>
  </section>
</template>
