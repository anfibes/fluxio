<script setup lang="ts">
import type { ContextItem } from '~/components/context/ContextList.vue'

const tabs = ['Tasks', 'Leads', 'Recent'] as const
type Tab = typeof tabs[number]

const active = ref<Tab>('Tasks')

const { items: leadItems, loading: leadsLoading, error: leadsError, refresh: refreshLeads } = useLeads()
const { items: taskItems, loading: tasksLoading, error: tasksError, refresh: refreshTasks } = useTasks()

const items = computed<ContextItem[]>(() => {
    if (active.value === 'Tasks') {
        return [...taskItems.value]
    }

    if (active.value === 'Leads') {
        return [...leadItems.value]
    }

    return []
})

const loading = computed<boolean>(() => {
  if (active.value === 'Tasks') return tasksLoading.value
  if (active.value === 'Leads') return leadsLoading.value
  return false
})

const error = computed<string | null>(() => {
  if (active.value === 'Tasks') return tasksError.value
  if (active.value === 'Leads') return leadsError.value
  return null
})

function retry() {
  if (active.value === 'Tasks') refreshTasks()
  if (active.value === 'Leads') refreshLeads()
}
</script>

<template>
  <div class="flex flex-col overflow-hidden rounded-xl border border-border bg-surface">
    <div class="flex border-b border-border">
      <button
        v-for="tab in tabs"
        :key="tab"
        type="button"
        class="flex-1 py-2.5 text-xs font-medium transition-colors"
        :class="active === tab
          ? 'border-b-2 border-accent text-accent -mb-px'
          : 'text-muted hover:text-text-muted'"
        @click="active = tab"
      >
        {{ tab }}
      </button>
    </div>
    <div class="px-4 py-1">
      <!-- Loading -->
      <div v-if="loading" class="py-6 text-center text-xs text-muted">
        Loading…
      </div>

      <!-- Error -->
      <div v-else-if="error" class="flex flex-col items-center gap-2 py-4">
        <p class="text-xs text-red-400">{{ error }}</p>
        <button
          type="button"
          class="rounded px-2 py-1 text-xs text-muted transition-colors hover:text-text-muted border border-border"
          @click="retry"
        >
          Retry
        </button>
      </div>

      <!-- Empty / data -->
      <ContextList v-else :items="items" />
    </div>
  </div>
</template>
