import { readonly, ref } from 'vue'
import { api } from '../lib/api'
import type { LocationQuery, RouteLocationRaw } from 'vue-router'

interface Category { name: string; slug: string }
const categories = ref<Category[]>([])
const error = ref('')
const loading = ref(false)
let loaded = false
let pending: Promise<void> | null = null
async function load(force = false) {
  if (pending) return pending
  if (loaded && !force) return
  loading.value = true
  error.value = ''
  pending = api.get<{ data: Category[] }>('/categories')
    .then(({ data }) => { categories.value = data.data; loaded = true })
    .catch(() => { error.value = 'Não foi possível carregar as sugestões de categorias.' })
    .finally(() => { loading.value = false; pending = null })
  return pending
}
export function catalogLocation(query: LocationQuery = {}): RouteLocationRaw {
  const target = { ...query }
  if (typeof target.category_slug === 'string' && target.category_slug) {
    const slug = target.category_slug
    delete target.category_slug
    delete target.category
    return { name: 'category', params: { categorySlug: slug }, query: target }
  }
  const name = typeof target.category === 'string' ? target.category.trim() : ''
  const category = categories.value.find(category => category.name.toLocaleLowerCase() === name.toLocaleLowerCase())
  delete target.category_slug
  if (category) {
    delete target.category
    return { name: 'category', params: { categorySlug: category.slug }, query: target }
  }
  return { name: 'home', query: target }
}
export function useCategories() { return { categories: readonly(categories), error: readonly(error), loading: readonly(loading), load } }