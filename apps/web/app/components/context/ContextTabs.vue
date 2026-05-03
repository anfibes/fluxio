<script setup lang="ts">
import type { ContextItem } from '~/components/context/ContextList.vue'

const tabs = ['Tasks', 'Leads', 'Recent'] as const
type Tab = typeof tabs[number]

const active = ref<Tab>('Tasks')

const mockTasks: ContextItem[] = [
  { id: 1, title: 'Follow-up call with Acme Corp', subtitle: 'High priority', meta: 'Fri' },
  { id: 2, title: 'Send proposal to TechStart', subtitle: 'Pending review', meta: 'Mon' },
  { id: 3, title: 'Update CRM records', subtitle: 'Assigned to you', meta: 'Tue' },
]

const mockLeads: ContextItem[] = [
  { id: 1, title: 'Sarah Chen', subtitle: 'TechStart Inc.', meta: 'New' },
  { id: 2, title: 'Marco Rossi', subtitle: 'Acme Corp', meta: 'Active' },
  { id: 3, title: 'Priya Nair', subtitle: 'Freelance', meta: 'Warm' },
]

const mockRecent: ContextItem[] = [
  { id: 1, title: 'Task created — Demo with TechStart', meta: '2m ago' },
  { id: 2, title: 'Lead updated — Sarah Chen', meta: '1h ago' },
  { id: 3, title: 'Task completed — Send invoice', meta: '3h ago' },
]

const items = computed<ContextItem[]>(() => {
  if (active.value === 'Tasks') return mockTasks
  if (active.value === 'Leads') return mockLeads
  return mockRecent
})
</script>

<template>
  <div class="flex flex-col overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)]">
    <div class="flex border-b border-[var(--color-border)]">
      <button
        v-for="tab in tabs"
        :key="tab"
        type="button"
        class="flex-1 py-2.5 text-xs font-medium transition-colors"
        :class="active === tab
          ? 'border-b-2 border-[var(--color-accent)] text-[var(--color-accent)] -mb-px'
          : 'text-[var(--color-muted)] hover:text-[var(--color-text-muted)]'"
        @click="active = tab"
      >
        {{ tab }}
      </button>
    </div>
    <div class="px-4 py-1">
      <ContextList :items="items" />
    </div>
  </div>
</template>
