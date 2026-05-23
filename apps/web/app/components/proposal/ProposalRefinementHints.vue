<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'

const props = defineProps<{ proposal: ActionProposal }>()

const { t } = useI18n()

const MAX_HINTS = 5

interface Hint {
  key: string
  label: string
  source: 'missing' | 'ambiguity' | 'capability'
}

function humanize(value: string): string {
  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, c => c.toUpperCase())
}

const isVisibleStatus = computed(() =>
  props.proposal.status === 'draft' || props.proposal.status === 'ready',
)

const hints = computed<Hint[]>(() => {
  if (!isVisibleStatus.value) return []

  const out: Hint[] = []
  const seen = new Set<string>()

  function push(hint: Hint) {
    const dedupe = hint.label.toLowerCase()
    if (seen.has(dedupe)) return
    seen.add(dedupe)
    out.push(hint)
  }

  for (const m of props.proposal.missing ?? []) {
    if (!m.required) continue
    push({
      key: `missing:${m.key}`,
      label: t('proposal.refinement_hints.add_missing', { field: m.label }),
      source: 'missing',
    })
  }

  const hasBlockingAmbiguity = (props.proposal.ambiguities ?? []).some(
    a => a.blocking && a.selected_candidate_id === null,
  )
  if (hasBlockingAmbiguity && props.proposal.capabilities?.supports_ambiguity_resolution) {
    push({
      key: 'ambiguity',
      label: t('proposal.refinement_hints.resolve_ambiguity'),
      source: 'ambiguity',
    })
  }

  for (const r of props.proposal.capabilities?.refinements ?? []) {
    push({
      key: `capability:${r.key}`,
      label: r.label || humanize(r.key),
      source: 'capability',
    })
  }

  return out.slice(0, MAX_HINTS)
})
</script>

<template>
  <div
    v-if="hints.length"
    class="mx-4 mb-4 rounded-lg border border-border bg-surface-raised px-3.5 py-3"
  >
    <p class="mb-2 text-xs font-medium text-muted">
      {{ $t('proposal.refinement_hints.title') }}
    </p>
    <ul class="flex flex-col gap-1">
      <li
        v-for="hint in hints"
        :key="hint.key"
        class="flex items-start gap-2 text-xs text-text-muted"
      >
        <span class="mt-0.5 shrink-0 text-muted/60">·</span>
        <span class="leading-relaxed">{{ hint.label }}</span>
      </li>
    </ul>
  </div>
</template>
