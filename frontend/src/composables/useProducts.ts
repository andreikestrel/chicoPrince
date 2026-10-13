import { onBeforeUnmount, ref, watch, type Ref } from 'vue'
import { useRoute } from 'vue-router'
import { api, ApiError } from '../lib/api'
import type { ProductPage } from '../types/products'

export function useProducts(enabled: Ref<boolean> = ref(true)) {
  const route = useRoute()
  const routeName = route.name
  const result = ref<ProductPage | null>(null)
  const loading = ref(false)
  const error = ref<ApiError | null>(null)
  let controller: AbortController | undefined

  async function fetchProducts() {
    if (route.name !== routeName) return
    controller?.abort()
    if (!enabled.value) { result.value = null; loading.value = false; return }
    const request = new AbortController()
    controller = request
    loading.value = true
    error.value = null
    const params: Record<string, string> = {}
    for (const key of ['search', 'category', 'min_price', 'max_price', 'in_stock', 'sort', 'direction', 'per_page', 'page']) {
      const value = route.query[key]
      const first = Array.isArray(value) ? value[0] : value
      if (first != null) params[key] = first
    }
    try {
      const response = await api.get<ProductPage>('/products', { params, signal: request.signal })
      if (!request.signal.aborted) result.value = response.data
    } catch (failure) {
      if (!request.signal.aborted) error.value = failure instanceof ApiError ? failure : new ApiError('Não foi possível carregar os produtos.', 0, 'unexpected_error')
    } finally {
      if (!request.signal.aborted) loading.value = false
    }
  }
  watch([() => route.query, enabled], fetchProducts, { immediate: true })
  onBeforeUnmount(() => controller?.abort())
  return { result, loading, error, fetchProducts }
}
