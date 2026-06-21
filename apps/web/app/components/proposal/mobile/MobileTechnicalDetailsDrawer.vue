<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'

const props = defineProps<{
  proposal: ActionProposal | null
}>()

const sections = computed(() => {
  const p = props.proposal
  if (!p) return []
  return [
    { label: 'Status',     value: p.status },
    { label: 'Intent',     value: p.intent ? p.intent.replace(/_/g, ' ') : '—' },
    { label: 'Proposal ID', value: p.id },
    { label: 'Confidence', value: typeof p.confidence === 'number' ? `${Math.round(p.confidence * 100)}%` : '—' },
  ]
})
</script>

<template>
  <details class="dev-drawer mx-4 mt-3 mb-4">
    <summary class="dev-drawer-summary">
      <span>Technical details</span>
      <span class="dev-drawer-chevron">▸</span>
    </summary>
    <div class="dev-drawer-body">
      <p class="mb-2 text-[10px] leading-relaxed text-muted/50">
        Metadata about this proposal. Not part of the primary workflow.
      </p>
      <dl class="flex flex-col gap-2">
        <div
          v-for="row in sections"
          :key="row.label"
          class="flex items-center justify-between"
        >
          <dt class="text-xs text-muted">{{ row.label }}</dt>
          <dd class="text-xs font-medium text-text-muted">{{ row.value }}</dd>
        </div>
      </dl>
    </div>
  </details>
</template>

<style scoped>
.dev-drawer {
  border-radius: 0.75rem;
  border: 1px solid var(--color-border);
  background-color: var(--color-surface);
  overflow: hidden;
}

.dev-drawer[open] {
  background-color: var(--color-surface-raised);
}

.dev-drawer-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.625rem 0.875rem;
  font-size: 0.6875rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-muted);
  cursor: pointer;
  user-select: none;
  list-style: none;
}

.dev-drawer-summary::-webkit-details-marker {
  display: none;
}

.dev-drawer-chevron {
  font-size: 0.625rem;
  transition: transform 0.15s ease;
}

.dev-drawer[open] .dev-drawer-chevron {
  transform: rotate(90deg);
}

.dev-drawer-body {
  padding: 0.5rem 0.875rem 0.75rem;
  border-top: 1px solid var(--color-border-subtle);
}
</style>
