<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { api, ApiError, prepareCsrf } from '../lib/api'
import CategoryInput from './CategoryInput.vue'
import ProductPagination from './ProductPagination.vue'
import { useCategories } from '../composables/useCategories'
import type { Banner } from '../types/banners'
import type { ProductPage } from '../types/products'

const rows = ref<Banner[]>([])
const meta = ref<ProductPage['meta']>()
const busy = ref(false)
const loading = ref(false)
const message = ref('')
const error = ref('')
const details = ref<Record<string, string[]>>({})
const editing = ref<number | null>(null)
const defaults = { title: '', image: '', category: '', position: 0, active: true }
const form = reactive({ ...defaults })
const { load: loadCategories } = useCategories()
onBeforeRouteLeave(() => !busy.value)
async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<{ data: Banner[]; meta: ProductPage['meta'] }>('/admin/banners', { params: { page } })
    rows.value = data.data; meta.value = data.meta
    if (!rows.value.length && page > 1) await load(page - 1)
  } catch (cause) { error.value = cause instanceof Error ? cause.message : 'Falha ao carregar banners.' }
  finally { loading.value = false }
}
function reset() { editing.value = null; Object.assign(form, defaults); details.value = {} }
function edit(banner: Banner) {
  editing.value = banner.id
  Object.assign(form, { title: banner.title, image: banner.image, category: banner.category, position: banner.position, active: banner.active })
  details.value = {}; message.value = ''
}
async function save() {
  if (busy.value) return
  busy.value = true; error.value = ''; message.value = ''; details.value = {}
  try {
    await prepareCsrf()
    if (editing.value) await api.put(`/admin/banners/${editing.value}`, { ...form })
    else await api.post('/admin/banners', { ...form })
    reset()
    message.value = 'Banner salvo. A página inicial exibirá os banners ativos por ordem.'
    await load(meta.value?.current_page ?? 1)
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Não foi possível salvar.'
    if (cause instanceof ApiError) details.value = cause.details
  } finally { busy.value = false }
}
async function remove(banner: Banner) {
  if (busy.value || !window.confirm(`Excluir o banner “${banner.title}”?`)) return
  busy.value = true; message.value = ''; error.value = ''
  try {
    await prepareCsrf(); await api.delete(`/admin/banners/${banner.id}`)
    if (editing.value === banner.id) reset()
    message.value = 'Banner excluído.'
    await load(meta.value?.current_page ?? 1)
  } catch (cause) { error.value = cause instanceof Error ? cause.message : 'Não foi possível excluir.' }
  finally { busy.value = false }
}
onMounted(() => { void load(); void loadCategories(true) })
</script>
<template>
  <details class="banner-manager">
    <summary>Gerenciar banners da página inicial</summary>
    <p>Escolha uma imagem por URL e uma categoria já cadastrada. O clique abre o catálogo filtrado. Até 20 banners ativos são exibidos, em ordem crescente.</p>
    <p v-if="message" role="status">{{ message }}</p>
    <div v-if="error" class="error" role="alert"><p>{{ error }}</p><ul><li v-for="(messages, field) in details" :key="field">{{ field }}: {{ messages.join(' ') }}</li></ul><button class="secondary" :disabled="busy || loading" @click="load(meta?.current_page ?? 1)">Atualizar lista</button></div>
    <form class="banner-form" @submit.prevent="save">
      <h2>{{ editing ? 'Editar banner' : 'Novo banner' }}</h2>
      <fieldset :disabled="busy">
        <label>Texto do banner<input v-model="form.title" maxlength="120" required /></label>
        <label>URL da imagem do banner<input v-model="form.image" type="url" maxlength="2048" required placeholder="https://…" /></label>
        <CategoryInput v-model="form.category" required />
        <label>Ordem de exibição<input v-model.number="form.position" type="number" min="0" max="9999" step="1" required /></label>
        <label class="checkbox-field"><input v-model="form.active" type="checkbox" /> Banner ativo</label>
        <div class="filter-actions"><button type="submit">{{ busy ? 'Salvando…' : 'Salvar banner' }}</button><button v-if="editing" type="button" class="secondary" @click="reset">Cancelar edição</button></div>
      </fieldset>
    </form>
    <p v-if="loading" role="status">Carregando banners…</p>
    <ul class="banner-list">
      <li v-for="banner in rows" :key="banner.id"><div><strong>{{ banner.title }}</strong><p>{{ banner.category }} · Ordem {{ banner.position }} · {{ banner.active ? 'Ativo' : 'Inativo' }}</p></div><div class="row-actions"><button class="secondary" :disabled="busy || loading" :aria-label="`Editar banner ${banner.title}`" @click="edit(banner)">Editar</button><button class="danger" :disabled="busy || loading" :aria-label="`Excluir banner ${banner.title}`" @click="remove(banner)">Excluir</button></div></li>
    </ul>
    <p v-if="!loading && !rows.length">Nenhum banner cadastrado.</p>
    <ProductPagination v-if="meta" :meta="meta" :disabled="loading || busy" @change="load" />
  </details>
</template>