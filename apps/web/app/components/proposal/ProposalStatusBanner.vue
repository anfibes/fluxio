<script setup lang="ts">
import type { ActionProposal, ActionProposalStatus } from '~/types/actions'

const props = defineProps<{ proposal: ActionProposal }>()

const statusConfig: Record<ActionProposalStatus, { label: string; pill: string }> = {
  draft:     { label: 'Draft',     pill: 'bg-amber-500/15 text-amber-400' },
  ready:     { label: 'Ready',     pill: 'bg-emerald-500/15 text-emerald-400' },
  confirmed: { label: 'Confirmed', pill: 'bg-blue-500/15 text-blue-400' },
  executed:  { label: 'Executed',  pill: 'bg-[var(--color-accent)]/15 text-[var(--color-accent)]' },
  failed:    { label: 'Failed',    pill: 'bg-red-500/15 text-red-400' },
}

const cfg = computed(() => statusConfig[props.proposal.status])
const intentLabel = computed(() => props.proposal.intent.replace(/_/g, ' '))
const confidencePct = computed(() => Math.round(props.proposal.confidence * 100))
</script>

<template>
  <div class="flex flex-col gap-3 border-b border-[var(--color-border)] p-4">
    <div class="flex items-start justify-between gap-2">
      <span class="text-sm font-semibold capitalize text-[var(--color-text)]">{{ intentLabel }}</span>
      <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="cfg.pill">
        {{ cfg.label }}
      </span>
    </div>

    <p class="text-xs italic leading-snug text-[var(--color-muted)]">
      "{{ proposal.source_text }}"
    </p>

    <div class="flex items-center gap-2">
      <div class="h-1 flex-1 overflow-hidden rounded-full bg-[var(--color-surface-raised)]">
        <div
          class="h-full rounded-full bg-[var(--color-accent)] transition-all"
          :style="{ width: `${confidencePct}%` }"
        />
      </div>
      <span class="tabular-nums text-xs text-[var(--color-muted)]">{{ confidencePct }}%</span>
    </div>
  </div>
</template>
