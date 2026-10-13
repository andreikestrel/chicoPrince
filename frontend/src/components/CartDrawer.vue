<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import CartItems from './CartItems.vue'
import { useCart, money } from '../composables/useCart'
const { drawerOpen, closeDrawer, items, count, total, ready, loading, warning, refresh } = useCart()
const dialog = ref<HTMLDialogElement>()
const route = useRoute()
let previousOverflow = ''
watch(drawerOpen, async open => {
  await nextTick()
  if (open) {
    previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    dialog.value?.showModal()
  } else {
    dialog.value?.close()
    document.body.style.overflow = previousOverflow
  }
})
watch(() => route.fullPath, closeDrawer)
onBeforeUnmount(() => { if (drawerOpen.value) document.body.style.overflow = previousOverflow })
</script>
<template>
  <dialog ref="dialog" class="cart-drawer" aria-labelledby="drawer-title" @cancel.prevent="closeDrawer" @click="event => { if (event.target === dialog) closeDrawer() }">
    <div class="drawer-content">
      <div class="drawer-heading"><h2 id="drawer-title">Seu carrinho ({{ count }})</h2><button autofocus type="button" class="secondary" aria-label="Fechar carrinho" @click="closeDrawer">×</button></div>
      <p v-if="loading" role="status">Atualizando produtos…</p>
      <p v-if="warning" class="error" role="alert">{{ warning }}</p>
      <p v-if="!items.length">Seu carrinho está vazio.</p>
      <CartItems v-else />
      <div v-if="items.length" class="drawer-summary">
        <p class="summary-line"><span>Subtotal</span><strong>{{ loading ? 'Atualizando…' : money(total) }}</strong></p>

        <RouterLink v-if="ready && !loading" class="button-link" to="/checkout" @click="closeDrawer">Continuar para checkout</RouterLink>
      </div>
      <button v-if="items.length" class="secondary" :disabled="loading" @click="refresh">Atualizar carrinho</button>
      <button class="secondary" @click="closeDrawer">Continuar comprando</button>
    </div>
  </dialog>
</template>