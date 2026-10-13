<script setup lang="ts">
import { onMounted, useId } from 'vue'
import { useCategories } from '../composables/useCategories'
defineProps<{ required?: boolean; invalid?: boolean }>()
const model = defineModel<string>({ required: true })
const { categories, error, load } = useCategories()
const id = useId()
onMounted(() => load())
</script>
<template>
  <label>Categoria
    <input v-model="model" :list="id" :required="required" :aria-invalid="invalid || undefined" maxlength="100" placeholder="Escolha ou digite uma categoria" autocomplete="off" />
    <datalist :id="id"><option v-for="category in categories" :key="category.name" :value="category.name" /></datalist>
  </label>
  <small v-if="error" role="status">{{ error }} Você ainda pode digitar o nome.</small>
</template>