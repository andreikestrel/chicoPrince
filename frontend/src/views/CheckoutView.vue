<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import { api, ApiError, prepareCsrf } from '../lib/api'
import CheckoutAddress, { type Address } from '../components/CheckoutAddress.vue'
import CouponNotice from '../components/CouponNotice.vue'
import { useCart, money, cents } from '../composables/useCart'

const { items, total, loading, warning, ready, refresh, openDrawer, consume } = useCart()
const router = useRouter()
const { user } = useAuth()
const busy = ref(false)
const submitError = ref('')
const submitDetails = ref<Record<string, string[]>>({})
const completed = ref<{ id: number; total: number } | null>(null)
type Payload = { request_key: string; coupon: string | null; address: Address; items: {variant_id: number; quantity: number; unit_price: string}[] }
const pending = ref<Payload | null>(null)
let memoryKey: string = crypto.randomUUID()
async function finish() {
  if (busy.value || (!pending.value && (!ready.value || loading.value))) return
  busy.value = true
  submitError.value = ''
  submitDetails.value = {}
  const storageKey = 'chico-prince-order-key-' + user.value?.id
  if (!pending.value) {
    try {
      memoryKey = sessionStorage.getItem(storageKey) || memoryKey
      sessionStorage.setItem(storageKey, memoryKey)
    } catch { /* A tentativa mantém sua chave em memória. */ }
    pending.value = { request_key: memoryKey, coupon: appliedCoupon.value || null,
      address: { ...address.value, state: address.value.state.toUpperCase() },
      items: items.value.map(item => ({variant_id: item.variantId, quantity: item.quantity, unit_price: item.product!.price})) }
  }
  try {
    await prepareCsrf()
    const { data } = await api.post('/orders', pending.value)
    completed.value = data.data
    consume(pending.value.items)
    pending.value = null
    try { sessionStorage.removeItem(storageKey) } catch { /* Sem persistência disponível. */ }
    memoryKey = crypto.randomUUID()
  } catch (cause) {
    if (cause instanceof ApiError && cause.status === 401) {
      await router.replace({ name: 'login', query: { redirect: '/checkout' } })
    } else if (cause instanceof ApiError && cause.status === 422) {
      submitError.value = 'Confira os dados abaixo antes de finalizar.'
      submitDetails.value = cause.details
      pending.value = null
      memoryKey = crypto.randomUUID()
      try { sessionStorage.removeItem(storageKey) } catch { /* Sem persistência disponível. */ }
      await refresh()
    } else {
      submitError.value = 'Não foi possível confirmar a resposta. Tente novamente para verificar a mesma compra, sem duplicá-la.'
    }
  } finally { busy.value = false }
}
const address = ref<Address>({ recipient: '', cep: '', street: '', number: '', complement: '', district: '', city: '', state: '' })
const coupon = ref('')
const appliedCoupon = ref('')
const couponMessage = ref('')
const discount = computed(() => appliedCoupon.value ? Math.round(total.value / 10) : 0)

function applyCoupon() {
  const code = coupon.value.trim().toUpperCase()
  if (code !== 'PRIMEIRACOMPRA') {
    couponMessage.value = 'Cupom não encontrado. Confira o código informado.'
    return
  }
  appliedCoupon.value = code
  coupon.value = code
  couponMessage.value = 'Cupom aplicado à estimativa: 10% de desconto nos produtos.'
}
function removeCoupon() {
  appliedCoupon.value = ''
  coupon.value = ''
  couponMessage.value = 'Cupom removido.'
}
onMounted(refresh)
</script>

<template>
  <section class="shopping-view">
    <h1>Checkout</h1>
    <button v-if="!completed" class="secondary" :disabled="busy || !!pending" @click="openDrawer">Revisar carrinho</button>
    <div v-if="completed" class="purchase-summary" role="status">
      <h2>Compra concluída!</h2>
      <p>Pedido #{{ completed.id }} · {{ money(completed.total) }}</p>
      <p>Compra demonstrativa, sem cobrança financeira.</p>
      <RouterLink class="button-link" to="/">Continuar comprando</RouterLink>
    </div>
    <CouponNotice v-else />
    <p v-if="!completed && !items.length" class="catalog-message">Seu carrinho está vazio.</p>
    <p v-if="warning" class="error" role="alert">{{ warning }}</p>
    <div v-if="!completed && items.length" class="shopping-grid">
      <form id="checkout-form" class="checkout-form" @submit.prevent="finish">
        <CheckoutAddress v-model="address" :disabled="loading || busy || !!pending" />
      </form>
      <aside class="purchase-summary">
        <h2>Resumo da compra</h2>
        <ul class="checkout-items">
          <li v-for="item in items" :key="item.variantId">
            <strong>{{ item.product?.name ?? 'Atualizando produto…' }}</strong>
            <p>{{ item.variant?.color }} · {{ item.variant?.size }} · {{ item.quantity }} unidade(s)</p>
            <p v-if="item.product">{{ money(cents(item.product.price) * item.quantity) }}</p>
          </li>
        </ul>
        <p v-if="!ready && !loading" class="error">Há itens indisponíveis. Revise o carrinho para continuar.</p>
        <form class="coupon-form" @submit.prevent="applyCoupon">
          <label for="checkout-coupon">Cupom</label>
          <div class="input-action">
            <input id="checkout-coupon" v-model="coupon" maxlength="40" autocomplete="off" :disabled="loading || busy || !!pending || !!appliedCoupon" placeholder="Digite seu cupom" />
            <button v-if="!appliedCoupon" type="submit" :disabled="loading || busy || !!pending || !ready || !coupon.trim()">Aplicar</button>
            <button v-else type="button" class="secondary" :disabled="busy || !!pending" @click="removeCoupon">Remover</button>
          </div>
          <p v-if="couponMessage" role="status">{{ couponMessage }}</p>
        </form>
        <p class="summary-line"><span>Produtos</span><strong>{{ loading ? 'Atualizando…' : money(total) }}</strong></p>
        <p v-if="appliedCoupon" class="summary-line"><span>Desconto (10%)</span><strong>{{ loading ? 'Atualizando…' : '− ' + money(discount) }}</strong></p>
        <p class="summary-line"><span>Frete</span><span>Sem cobrança (demonstração)</span></p>
        <p class="summary-line"><strong>Total estimado</strong><strong>{{ loading ? 'Atualizando…' : money(total - discount) }}</strong></p>
                <p>Compra demonstrativa: sem pagamento real. O pedido será concluído e o estoque será atualizado.</p>
        <div v-if="submitError" class="error" role="alert"><p>{{ submitError }}</p><ul><li v-for="(messages, field) in submitDetails" :key="field">{{ messages.join(' ') }}</li></ul></div>
        <button type="submit" form="checkout-form" :disabled="busy || loading || (!ready && !pending)">{{ busy ? 'Finalizando…' : pending ? 'Verificar finalização' : 'Finalizar compra' }}</button>
      </aside>
    </div>
  </section>
</template>