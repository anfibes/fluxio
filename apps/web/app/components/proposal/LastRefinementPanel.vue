<script setup lang="ts">
import type {
  ActionProposalRefinement,
  ActionProposalRefinementChange,
  RefinementAmbiguityOutcome,
} from '~/types/actions'

const props = defineProps<{ refinement: ActionProposalRefinement }>()

const displayText = computed(() => props.refinement.effective_text ?? props.refinement.text)

// ── Local formatting helpers (frontend-only) ───────────────────────────────
// These render the backend's optional semantic_type metadata into clearer
// operational phrasing. They never assume the field is present: any change
// without a recognized semantic_type falls back to the original label → value
// rendering, so older payloads stay stable.

function formatValue(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (Array.isArray(value)) return value.length ? value.map(String).join(', ') : '—'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

function toArray(value: unknown): unknown[] {
  if (Array.isArray(value)) return value
  return value === null || value === undefined ? [] : [value]
}

/** Items present in `next` but not in `prev` (used for participant deltas). */
function added(prev: unknown, next: unknown): unknown[] {
  const before = toArray(prev)
  return toArray(next).filter((item) => !before.includes(item))
}

function addedParticipant(change: ActionProposalRefinementChange): string {
  const items = added(change.from, change.to)
  if (items.length) return items.map(String).join(', ')
  // Fallback: last item of the resulting list, or em dash when empty.
  const to = toArray(change.to)
  return to.length ? String(to[to.length - 1]) : '—'
}

function removedParticipant(change: ActionProposalRefinementChange): string {
  if (change.target !== null && change.target !== undefined && change.target !== '') {
    return String(change.target)
  }
  const items = added(change.to, change.from)
  return items.length ? items.map(String).join(', ') : '—'
}

function replacedParticipant(change: ActionProposalRefinementChange): { from: string, to: string } {
  const fromLabel = change.target !== null && change.target !== undefined && change.target !== ''
    ? String(change.target)
    : formatValue(added(change.to, change.from)[0] ?? null)
  const incoming = added(change.from, change.to)
  return { from: fromLabel, to: incoming.length ? String(incoming[0]) : '—' }
}

/** Clear, operationally-meaningful sentence for a recognized semantic_type, or null. */
function formatSemanticChange(change: ActionProposalRefinementChange): string | null {
  switch (change.semantic_type) {
    case 'shift_time': {
      const from = formatValue(change.from)
      const to = formatValue(change.to)
      return from !== '—' ? `Time shifted from ${from} to ${to}` : `Time shifted to ${to}`
    }
    case 'replace_time':
      return `Time changed to ${formatValue(change.to)}`
    case 'replace_date':
      return `Date changed to ${formatValue(change.to)}`
    case 'add_participant':
      return `Participant added: ${addedParticipant(change)}`
    case 'remove_participant':
      return `Participant removed: ${removedParticipant(change)}`
    case 'replace_participant': {
      const { from, to } = replacedParticipant(change)
      return `Participant replaced: ${from} → ${to}`
    }
    default:
      return null
  }
}

// Subtle category badge derived from semantic_type. Optional decoration only.
const SEMANTIC_BADGES: Record<string, string> = {
  shift_time: 'Time',
  replace_time: 'Time',
  replace_date: 'Date',
  add_participant: 'Participant',
  remove_participant: 'Participant',
  replace_participant: 'Participant',
}

function semanticBadge(change: ActionProposalRefinementChange): string | null {
  return change.semantic_type ? SEMANTIC_BADGES[change.semantic_type] ?? null : null
}

const decoratedChanges = computed(() =>
  props.refinement.changes.map((change) => ({
    change,
    message: formatSemanticChange(change),
    badge: semanticBadge(change),
  })),
)

// ── Ambiguity outcome facet (backend Phase 8E.1) ────────────────────────────
// Rendered distinctly from field changes and keyed off `kind` only — never by
// parsing the summary/warning prose. Null/absent when no clarification was driven.
const ambiguityOutcome = computed<RefinementAmbiguityOutcome | null>(
  () => props.refinement.ambiguity_outcome ?? null,
)

function formatAmbiguityOutcome(outcome: RefinementAmbiguityOutcome): string {
  const label = outcome.label ?? outcome.ambiguity_key ?? 'Ambiguity'
  switch (outcome.kind) {
    case 'narrowed':
      return outcome.from_count != null && outcome.to_count != null
        ? `Ambiguity narrowed: ${label} candidates ${outcome.from_count} → ${outcome.to_count}`
        : `Ambiguity narrowed: ${label}`
    case 'resolved':
      return `Ambiguity resolved: ${label}`
    case 'unresolved':
      return 'Ambiguity unresolved'
    case 'inapplicable':
      return 'Clarification could not be applied'
    default:
      return 'Ambiguity updated'
  }
}
</script>

<template>
  <div class="mx-4 mb-4 overflow-hidden rounded-xl border border-border bg-surface-raised">
    <div class="border-b border-border px-3.5 py-2">
      <p class="text-xs font-medium uppercase tracking-wide text-muted">Last update</p>
    </div>
    <div class="flex flex-col gap-2 px-3.5 py-3">
      <p class="text-xs italic leading-relaxed text-text-muted">"{{ displayText }}"</p>
      <p class="text-xs text-muted">{{ refinement.summary }}</p>
      <div
        v-if="ambiguityOutcome"
        class="flex items-center gap-1.5 text-xs"
      >
        <span
          class="rounded bg-surface px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted"
        >Ambiguity</span>
        <span class="font-medium text-text-muted">{{ formatAmbiguityOutcome(ambiguityOutcome) }}</span>
      </div>
      <ul v-if="decoratedChanges.length" class="flex flex-col gap-1 pt-0.5">
        <li
          v-for="(item, index) in decoratedChanges"
          :key="`${item.change.field}-${index}`"
          class="flex items-center gap-1.5 text-xs"
        >
          <template v-if="item.message">
            <span
              v-if="item.badge"
              class="rounded bg-surface px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted"
            >{{ item.badge }}</span>
            <span class="font-medium text-text-muted">{{ item.message }}</span>
          </template>
          <template v-else>
            <span class="text-muted">{{ item.change.label }}</span>
            <span class="text-border-subtle">→</span>
            <span class="font-medium text-text-muted">{{ formatValue(item.change.to) }}</span>
          </template>
        </li>
      </ul>
    </div>
  </div>
</template>
