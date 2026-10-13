import { computed, readonly, ref, watch } from 'vue'
import { api, ApiError } from '../lib/api'
import type { Product, ProductVariant } from '../types/products'

interface CartItem { id: number; variantId: number; quantity: number; product?: Product; variant?: ProductVariant; unavailable?: boolean }
const key = 'chico-prince-cart-v2'
const items = ref<CartItem[]>([])
const warning = ref('')
try {
  const saved: unknown = JSON.parse(localStorage.getItem(key) ?? '[]')
  if (Array.isArray(saved)) {
    const seen = new Set<number>()
    items.value = saved.filter(item => {
      if (!item || !Number.isSafeInteger(item.id) || item.id < 1 || !Number.isSafeInteger(item.variantId) || item.variantId < 1 || !Number.isInteger(item.quantity) || item.quantity < 1 || item.quantity > 99 || seen.has(item.variantId)) return false
      seen.add(item.variantId)
      return true
    }).slice(0, 100).map(({ id, variantId, quantity }) => ({ id, variantId, quantity }))
  }
} catch { warning.value = 'Não foi possível recuperar o carrinho deste navegador.' }
watch(items, () => {
  try { localStorage.setItem(key, JSON.stringify(items.value.map(({ id, variantId, quantity }) => ({ id, variantId, quantity })))) }
  catch { warning.value = 'Seu navegador não permitiu salvar o carrinho. Os itens permanecem disponíveis nesta aba.' }
}, { deep: true })

export const cents = (price: string) => {
  const [whole = '0', fraction = ''] = price.split('.')
  return Number(whole) * 100 + Number(fraction.padEnd(2, '0').slice(0, 2))
}
export const money = (value: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value / 100)
const count = computed(() => items.value.reduce((sum, item) => sum + item.quantity, 0))
const total = computed(() => items.value.reduce((sum, item) => sum + (item.product ? cents(item.product.price) * item.quantity : 0), 0))
const ready = computed(() => items.value.length > 0 && items.value.every(item => item.product && !item.unavailable && item.variant && item.quantity <= item.variant.stock))
const loading = ref(false)
const drawerOpen = ref(false)
function openDrawer() { drawerOpen.value = true; void refresh() }
function closeDrawer() { drawerOpen.value = false }
let pending: Promise<void> | null = null

function add(product: Product, variant: ProductVariant) {
  const item = items.value.find(item => item.variantId === variant.id)
  if ((item?.quantity ?? 0) >= Math.min(variant.stock, 99)) return 'Quantidade máxima disponível atingida.'
  if (!item && items.value.length >= 100) return 'O carrinho permite até 100 produtos diferentes.'
  if (item) { item.quantity++; item.product = product; item.variant = variant; item.unavailable = false }
  else items.value.push({ id: product.id, variantId: variant.id, quantity: 1, product, variant })
  return ''
}
function quantity(id: number, value: number) {
  const item = items.value.find(item => item.variantId === id)
  if (item && Number.isInteger(value) && value >= 1 && value <= Math.min(item.variant?.stock ?? 0, 99)) item.quantity = value
}
function remove(id: number) { items.value = items.value.filter(item => item.variantId !== id) }
function clear() { items.value = [] }
function consume(purchased: { variant_id: number; quantity: number }[]) {
  for (const row of purchased) {
    const item = items.value.find(item => item.variantId === row.variant_id)
    if (item) item.quantity = Math.max(0, item.quantity - row.quantity)
  }
  items.value = items.value.filter(item => item.quantity > 0)
}
async function refresh() {
  if (pending) return pending
  loading.value = true
  warning.value = ''
  pending = (async () => {
    const snapshot = [...items.value]
    // Pequenos lotes limitam concorrência sem serializar todos os produtos.
    for (let start = 0; start < snapshot.length; start += 4) {
      await Promise.all(snapshot.slice(start, start + 4).map(async item => {
        try {
          const { data } = await api.get<{ data: Product }>(`/products/${item.id}`)
          if (item.product && item.product.price !== data.data.price) warning.value = 'Alguns preços foram atualizados. Confira o resumo antes de continuar.'
          item.product = data.data
          item.variant = data.data.variants?.find(variant => variant.id === item.variantId)
          item.unavailable = !item.variant
        } catch (error) {
          item.unavailable = true
          if (!(error instanceof ApiError && error.status === 404)) warning.value = 'Não foi possível atualizar todos os produtos. Tente novamente.'
        }
      }))
    }
  })().finally(() => { loading.value = false; pending = null })
  return pending
}
export function useCart() {
  return { drawerOpen: readonly(drawerOpen), openDrawer, closeDrawer, items: readonly(items), count, total, ready, loading: readonly(loading), warning: readonly(warning), add, quantity, remove, clear, consume, refresh }
}