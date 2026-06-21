<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'
import { formatFieldValue } from './mobileProposalFormatters'
import MobileTechnicalDetailsDrawer from './MobileTechnicalDetailsDrawer.vue'

const props = defineProps<{
  proposal: ActionProposal | null
}>()

// ── Safe derived data (no non-null assertions in templates) ───────

const sourceText = computed(() => props.proposal?.source_text ?? '')

const lastRefinement = computed(() => props.proposal?.last_refinement ?? null)

const refinementText = computed(() => lastRefinement.value?.text ?? '')

const refinementEffectiveText = computed(() => {
  const r = lastRefinement.value
  if (!r) return null
  return r.effective_text && r.effective_text !== r.text ? r.effective_text : null
})

const refinementSummary = computed(() => lastRefinement.value?.summary ?? '')

const refinementChanges = computed(() => lastRefinement.value?.changes ?? [])

const ambiguityOutcomeLabel = computed(() => {
  const outcome = lastRefinement.value?.ambiguity_outcome
  if (!outcome) return null
  const detail = outcome.label ?? outcome.ambiguity_key ?? 'Ambiguity'
  switch (outcome.kind) {
    case 'resolved':
      return `Ambiguity resolved: ${detail}`
    case 'narrowed':
      return outcome.from_count != null && outcome.to_count != null
        ? `Ambiguity narrowed: ${detail} candidates ${outcome.from_count} → ${outcome.to_count}`
        : `Ambiguity narrowed: ${detail}`
    case 'unresolved':
      return 'Ambiguity could not be resolved'
    case 'inapplicable':
      return 'Clarification did not affect ambiguities'
    default:
      return null
  }
})

interface LifecycleEvent {
  label: string
  value: string
}

const lifecycleTimestamps = computed<LifecycleEvent[]>(() => {
  const p = props.proposal
  if (!p) return []
  const items: LifecycleEvent[] = []
  if (p.confirmed_at) items.push({ label: 'Confirmed', value: p.confirmed_at })
  if (p.executed_at) items.push({ label: 'Executed', value: p.executed_at })
  if (p.failed_at) items.push({ label: 'Failed', value: p.failed_at })
  return items
})

const hasRefinement = computed(() => lastRefinement.value !== null)

const hasHistoryData = computed(() =>
  !!sourceText.value || hasRefinement.value || lifecycleTimestamps.value.length > 0,
)
</script>

<template>
  <div>
    <!-- Empty state -->
    <div v-if="!hasHistoryData" class="mx-4 mt-4 rounded-xl border border-border bg-surface px-4 py-6 text-center">
      <p class="text-sm font-medium text-text-muted">No history yet</p>
      <p class="mt-1 text-xs leading-relaxed text-muted">
        Refinement and lifecycle events will appear here as the proposal evolves.
      </p>
    </div>

    <!-- Original command -->
    <div v-if="sourceText" class="mx-4 mt-3 overflow-hidden rounded-xl border border-border bg-surface">
      <div class="border-b border-border px-4 py-2.5">
        <p class="text-[10px] font-medium uppercase tracking-widest text-muted/50">Original command</p>
      </div>
      <div class="px-4 py-3">
        <p class="text-sm italic leading-relaxed text-text-muted">"{{ sourceText }}"</p>
      </div>
    </div>

    <!-- Last refinement -->
    <div v-if="hasRefinement" class="mx-4 mt-3 overflow-hidden rounded-xl border border-border bg-surface">
      <div class="border-b border-border px-4 py-2.5">
        <p class="text-[10px] font-medium uppercase tracking-widest text-muted/50">Last refinement</p>
      </div>
      <div class="px-4 py-3 flex flex-col gap-3">
        <!-- Refinement text -->
        <div>
          <p class="text-xs italic leading-relaxed text-text-muted">
            "{{ refinementText }}"
          </p>
          <p
            v-if="refinementEffectiveText"
            class="mt-1 text-xs italic leading-relaxed text-muted"
          >
            Effective: "{{ refinementEffectiveText }}"
          </p>
        </div>

        <!-- Summary -->
        <p class="text-xs leading-relaxed text-muted">
          {{ refinementSummary }}
        </p>

        <!-- Ambiguity outcome -->
        <div
          v-if="ambiguityOutcomeLabel"
          class="rounded-md bg-surface-raised px-3 py-2 text-xs font-medium text-text-muted"
        >
          {{ ambiguityOutcomeLabel }}
        </div>

        <!-- Changes list -->
        <ul v-if="refinementChanges.length" class="flex flex-col gap-1.5">
          <li
            v-for="(change, i) in refinementChanges"
            :key="i"
            class="flex items-center gap-2 text-xs"
          >
            <span class="text-muted">{{ change.label }}</span>
            <span class="text-border-subtle">→</span>
            <span class="font-medium text-text-muted">{{ formatFieldValue(change.to) }}</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Lifecycle timestamps -->
    <div v-if="lifecycleTimestamps.length" class="mx-4 mt-3 overflow-hidden rounded-xl border border-border bg-surface">
      <div class="border-b border-border px-4 py-2.5">
        <p class="text-[10px] font-medium uppercase tracking-widest text-muted/50">Lifecycle</p>
      </div>
      <div class="px-4 py-3">
        <div class="flex flex-col gap-2">
          <div
            v-for="event in lifecycleTimestamps"
            :key="event.label"
            class="flex items-center justify-between gap-3"
          >
            <span class="text-xs text-muted">{{ event.label }}</span>
            <span class="shrink-0 text-right text-xs font-medium text-text-muted">{{ event.value }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Technical details -->
    <MobileTechnicalDetailsDrawer :proposal="proposal" />
  </div>
</template>
