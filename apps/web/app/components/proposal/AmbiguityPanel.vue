<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'

const props = defineProps<{ proposal: ActionProposal; loading?: boolean }>()
const emit = defineEmits<{ resolve: [text: string] }>()

const { t } = useI18n()

const unresolvedAmbiguities = computed(() =>
  props.proposal.ambiguities.filter(a => a.blocking && a.selected_candidate_id === null),
)

function reasonLabel(reason: string): string {
  if (reason === 'multiple_matches') return t('proposal.ambiguity.multiple_matches')
  return reason
}

function confidencePct(confidence: number): number {
  return Math.round(confidence * 100)
}
</script>

<template>
  <div
    v-if="unresolvedAmbiguities.length"
    class="mx-4 mb-4 overflow-hidden rounded-lg border border-blue-500/30 bg-blue-500/10"
  >
    <!-- Header -->
    <div class="border-b border-blue-500/20 px-3.5 py-2">
      <p class="text-xs font-medium text-blue-400">{{ $t('proposal.ambiguity.title') }}</p>
    </div>

    <!-- Ambiguity entries -->
    <div
      v-for="ambiguity in unresolvedAmbiguities"
      :key="ambiguity.key"
      class="px-3.5 py-3"
    >
      <!-- Field + reason -->
      <div class="mb-2 flex flex-wrap items-center gap-1.5">
        <span class="text-xs font-medium text-blue-300">{{ ambiguity.label }}</span>
        <span v-if="ambiguity.query" class="text-xs text-blue-300/60">
          {{ $t('proposal.ambiguity.query', { query: ambiguity.query }) }}
        </span>
        <span class="rounded-full bg-blue-500/15 px-1.5 py-px text-xs text-blue-400/80 ring-1 ring-blue-500/20">
          {{ reasonLabel(ambiguity.reason) }}
        </span>
      </div>

      <!-- Select prompt -->
      <p class="mb-2 text-xs text-blue-300/70">{{ $t('proposal.ambiguity.select_candidate') }}</p>

      <!-- Candidate cards -->
      <div class="flex flex-col gap-1.5">
        <button
          v-for="candidate in ambiguity.candidates"
          :key="candidate.id"
          type="button"
          class="flex w-full items-center gap-2.5 rounded-lg border border-border bg-surface-raised px-3 py-2 text-left text-xs transition-colors"
          :class="loading
            ? 'cursor-not-allowed opacity-50'
            : 'hover:border-blue-500/40 hover:bg-blue-500/8'"
          :disabled="loading"
          @click="emit('resolve', candidate.label)"
        >
          <!-- Label -->
          <span class="flex-1 font-medium text-text-muted">{{ candidate.label }}</span>

          <!-- Type pill -->
          <span class="shrink-0 rounded-full bg-surface px-1.5 py-px text-xs text-muted ring-1 ring-border">
            {{ candidate.type }}
          </span>

          <!-- Confidence -->
          <span
            v-if="candidate.confidence != null"
            class="shrink-0 tabular-nums text-xs text-muted"
          >
            {{ confidencePct(candidate.confidence) }}%
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
