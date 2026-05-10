<script setup lang="ts">
export interface ContextItem {
  id: string | number
  title: string
  subtitle?: string
  meta?: string
}

defineProps<{
  items: ContextItem[]
  emptyLabel?: string
}>()
</script>

<template>
  <div class="flex flex-col">
    <div v-if="!items.length" class="py-6 text-center text-xs text-muted">
      {{ emptyLabel ?? 'Nothing here yet.' }}
    </div>
    <div
      v-for="item in items"
      :key="item.id"
      class="flex items-start justify-between border-b border-border py-2.5 last:border-0"
    >
      <div class="flex min-w-0 flex-col gap-0.5">
        <span class="truncate text-sm text-text">{{ item.title }}</span>
        <span v-if="item.subtitle" class="text-xs text-muted">{{ item.subtitle }}</span>
      </div>
      <span v-if="item.meta" class="ml-4 mt-0.5 shrink-0 text-xs text-muted">{{ item.meta }}</span>
    </div>
  </div>
</template>
