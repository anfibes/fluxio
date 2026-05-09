<script setup lang="ts">
import type { ActionProposal, ActionProposalStatus } from '~/types/actions'

const props = defineProps<{ proposal: ActionProposal }>()

const statusConfig: Record<ActionProposalStatus, { label: string; pill: string; dot: string }> = {
  draft:     { label: 'Draft',     pill: 'bg-amber-500/12 text-amber-400 ring-1 ring-amber-500/20',       dot: 'bg-amber-400' },
  ready:     { label: 'Ready',     pill: 'bg-emerald-500/12 text-emerald-400 ring-1 ring-emerald-500/20', dot: 'bg-emerald-400' },
  confirmed: { label: 'Confirmed', pill: 'bg-blue-500/12 text-blue-400 ring-1 ring-blue-500/20',          dot: 'bg-blue-400' },
  executed:  { label: 'Executed',  pill: 'bg-[var(--color-accent)]/12 text-[var(--color-accent)] ring-1 ring-[var(--color-accent)]/20', dot: 'bg-[var(--color-accent)]' },
  failed:    { label: 'Failed',    pill: 'bg-red-500/12 text-red-400 ring-1 ring-red-500/20',             dot: 'bg-red-400' },
}

const cfg = computed(() => statusConfig[props.proposal.status])
const intentLabel = computed(() => props.proposal.intent.replace(/_/g, ' '))
const confidencePct = computed(() => Math.round(props.proposal.confidence * 100))
</script>

<template>
  <div class="flex flex-col gap-3 border-b border-[var(--color-border)] p-4">
    <!-- Intent + status row -->
    <div class="flex items-start justify-between gap-3">
      <h3 class="text-sm font-semibold capitalize leading-snug text-[var(--color-text)]">
        {{ intentLabel }}
      </h3>
      <span
        class="flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="cfg.pill"
      >
        <span class="h-1.5 w-1.5 rounded-full" :class="cfg.dot" />
        {{ cfg.label }}
      </span>
    </div>

    <!-- Source text -->
    <div class="rounded-lg bg-[var(--color-surface-raised)] px-3 py-2">
      <p class="text-xs italic leading-relaxed text-[var(--color-text-muted)]">
        "{{ proposal.source_text }}"
      </p>
    </div>

    <!-- Confidence -->
    <div class="flex items-center gap-2.5">
      <div class="h-1 flex-1 overflow-hidden rounded-full bg-[var(--color-border)]">
        <div
          class="h-full rounded-full transition-all duration-500"
          :class="confidencePct >= 80 ? 'bg-emerald-500' : confidencePct >= 60 ? 'bg-amber-500' : 'bg-red-500'"
          :style="{ width: `${confidencePct}%` }"
        />
      </div>
      <span class="tabular-nums text-xs text-[var(--color-muted)]">{{ confidencePct }}%</span>
    </div>
  </div>
</template>
