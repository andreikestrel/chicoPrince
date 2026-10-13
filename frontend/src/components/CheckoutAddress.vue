<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
export interface Address { recipient: string; cep: string; street: string; number: string; complement: string; district: string; city: string; state: string }
const address = defineModel<Address>({ required: true })
const props = defineProps<{ disabled?: boolean }>()
const loading = ref(false)
const message = ref('')
let controller: AbortController | undefined
watch(() => address.value.cep, () => { controller?.abort(); loading.value = false; message.value = '' })
onBeforeUnmount(() => controller?.abort())
async function lookup() {
  if (props.disabled) return
  const cep = address.value.cep.replace(/[^0-9]/g, '')
  if (!/^[0-9]{8}$/.test(cep)) { message.value = 'Informe um CEP com 8 dígitos.'; return }
  controller?.abort()
  const request = new AbortController()
  controller = request
  loading.value = true
  message.value = ''
  const timeout = setTimeout(() => request.abort('timeout'), 8000)
  try {
    const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`, { signal: request.signal, referrerPolicy: 'no-referrer' })
    if (!response.ok) throw new Error('service')
    const data = await response.json()
    if (request.signal.aborted) return
    if (data.erro) { message.value = 'CEP não encontrado. Confira o número ou preencha o endereço manualmente.'; return }
    if (typeof data.localidade !== 'string' || typeof data.uf !== 'string') throw new Error('service')
    address.value.street = data.logradouro ?? ''
    address.value.district = data.bairro ?? ''
    address.value.city = data.localidade
    address.value.state = data.uf
    message.value = 'Endereço encontrado. Confira os dados e informe o número.'
  } catch {
    if (!request.signal.aborted || request.signal.reason === 'timeout') message.value = 'Consulta indisponível. Tente novamente ou preencha o endereço manualmente.'
  } finally {
    clearTimeout(timeout)
    if (controller === request) loading.value = false
  }
}
</script>
<template>
  <fieldset :disabled="disabled">
    <legend><h2>Endereço de entrega</h2></legend>
    <div class="checkout-form">
      <label>Nome de quem recebe<input v-model="address.recipient" autocomplete="shipping name" maxlength="120" required /></label>
      <div class="input-action">
        <label>CEP<input v-model="address.cep" inputmode="numeric" autocomplete="shipping postal-code" placeholder="00000-000" pattern="[0-9]{5}-?[0-9]{3}" maxlength="9" required /></label>
        <button type="button" class="secondary" :disabled="loading" @click="lookup">{{ loading ? 'Consultando…' : 'Consultar' }}</button>
      </div>
      <p v-if="message" role="status">{{ message }}</p>
      <label>Rua<input v-model="address.street" autocomplete="shipping address-line1" maxlength="200" required /></label>
      <div class="address-grid">
        <label>Número<input v-model="address.number" maxlength="20" required /></label>
        <label>Complemento (opcional)<input v-model="address.complement" autocomplete="shipping address-line2" maxlength="100" /></label>
        <label>Bairro<input v-model="address.district" maxlength="100" required /></label>
        <label>Cidade<input v-model="address.city" autocomplete="shipping address-level2" maxlength="100" required /></label>
        <label>UF<input v-model="address.state" autocomplete="shipping address-level1" maxlength="2" pattern="[A-Za-z]{2}" required /></label>
      </div>
    </div>
  </fieldset>
</template>