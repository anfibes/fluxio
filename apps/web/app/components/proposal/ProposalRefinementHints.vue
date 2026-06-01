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

const TERMINAL_STATUSES = ['confirmed', 'executed', 'failed'] as const

const requiredMissingFields = computed(
  () => (props.proposal.missing ?? []).filter(m => m.required),
)

const hasResolvableBlockingAmbiguity = computed(() => {
  // Trust `blocking` directly: the runtime clears it on resolution.
  const blocking = (props.proposal.ambiguities ?? []).some(a => a.blocking)
  return blocking && props.proposal.capabilities?.supports_ambiguity_resolution === true
})

const shouldRenderHints = computed(() => {
  if ((TERMINAL_STATUSES as readonly string[]).includes(props.proposal.status)) {
    return false
  }
  return props.proposal.status === 'draft'
    || requiredMissingFields.value.length > 0
    || hasResolvableBlockingAmbiguity.value
})

const hints = computed<Hint[]>(() => {
  if (!shouldRenderHints.value) return []

  const out: Hint[] = []
  const seen = new Set<string>()

  function push(hint: Hint) {
    const dedupe = hint.label.toLowerCase()
    if (seen.has(dedupe)) return
    seen.add(dedupe)
    out.push(hint)
  }

  for (const m of requiredMissingFields.value) {
    push({
      key: `missing:${m.key}`,
      label: t('proposal.refinement_hints.add_missing', { field: m.label }),
      source: 'missing',
    })
  }

  if (hasResolvableBlockingAmbiguity.value) {
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
