<script setup lang="ts">
import { computed } from 'vue'
import type { LocationQueryValue } from 'vue-router'

const props = defineProps<{
  column: string
  label: string
  sort: LocationQueryValue | LocationQueryValue[]
  direction: LocationQueryValue | LocationQueryValue[]
  disabled: boolean
}>()
defineEmits<{ sort: [column: string] }>()
const active = computed(() => props.sort === props.column)
const ascending = computed(() => props.direction === 'asc')
const action = computed(() => `Ordenar por ${props.label}: ${active.value && ascending.value ? 'decrescente' : 'crescente'}`)
</script>

<template>
  <th scope="col" :aria-sort="active ? (ascending ? 'ascending' : 'descending') : 'none'">
    <div class="sortable-heading">
      <span>{{ label }}</span>
      <button type="button" class="sort-button" :title="action" :aria-label="action" :disabled="disabled" @click="$emit('sort', column)">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="m4 9 4-4 4 4 M8 5v14" :class="{ 'sort-active': active && ascending }" />
          <path d="m12 15 4 4 4-4 M16 5v14" :class="{ 'sort-active': active && !ascending }" />
        </svg>
      </button>
    </div>
  </th>
</template>