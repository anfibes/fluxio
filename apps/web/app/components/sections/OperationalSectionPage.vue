<script setup lang="ts">
import type { ContextItem } from '~/components/context/ContextList.vue'

// Shared shell for read-only operational sections (Leads, Tasks).
// Owns the page structure and the loading/error/empty states; pages
// keep ownership of auth and data fetching.
defineProps<{
  title: string
  subtitle?: string
  items: readonly ContextItem[]
  loading: boolean
  error: string | null
  emptyLabel: string
}>()

const emit = defineEmits<{
  retry: []
}>()
</script>

<template>
  <div class="h-full overflow-y-auto">
    <div class="mx-auto flex max-w-3xl flex-col gap-3 px-4 py-4 lg:px-6 lg:py-6">
      <header class="flex flex-col gap-0.5">
        <h1 class="text-sm font-semibold text-text">
          {{ title }}
        </h1>
        <p v-if="subtitle" class="text-xs text-muted">
          {{ subtitle }}
        </p>
      </header>

      <div class="rounded-xl border border-border bg-surface px-4 py-1">
        <div v-if="loading" class="py-6 text-center text-xs text-muted">
          {{ $t('common.loading') }}
        </div>

        <div v-else-if="error" class="flex flex-col items-center gap-2 py-4">
          <p class="text-xs text-red-400">{{ error }}</p>
          <button
            type="button"
            class="rounded border border-border px-2 py-1 text-xs text-muted transition-colors hover:text-text-muted"
            @click="emit('retry')"
          >
            {{ $t('common.retry') }}
          </button>
        </div>

        <ContextList v-else :items="items" :empty-label="emptyLabel" />
      </div>
    </div>
  </div>
</template>
