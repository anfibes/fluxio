<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'

const props = defineProps<{ proposal: ActionProposal | null }>()

const intentLabel = computed(() =>
  props.proposal ? props.proposal.intent.replace(/_/g, ' ') : '',
)

const confidencePct = computed(() =>
  props.proposal ? Math.round(props.proposal.confidence * 100) : 0,
)

const entityEntries = computed(() =>
  props.proposal ? Object.entries(props.proposal.entities) : [],
)
</script>

<template>
  <Transition name="slide-down">
    <div
      v-if="proposal"
      class="flex flex-col gap-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-3 text-sm"
    >
      <div class="flex items-center gap-3">
        <span class="rounded bg-[var(--color-accent)]/20 px-2 py-0.5 font-mono text-xs capitalize text-[var(--color-accent)]">
          {{ intentLabel }}
        </span>
        <span class="text-xs text-[var(--color-muted)]">
          Confidence:
          <span class="font-medium text-[var(--color-text-muted)]">{{ confidencePct }}%</span>
        </span>
      </div>

      <div v-if="entityEntries.length" class="flex flex-wrap gap-1.5">
        <span
          v-for="[key, value] in entityEntries"
          :key="key"
          class="rounded bg-[var(--color-surface-raised)] px-2 py-0.5 text-xs text-[var(--color-text-muted)]"
        >
          <span class="text-[var(--color-muted)]">{{ key }}:</span> {{ value }}
        </span>
      </div>

      <p v-if="proposal.warnings.length" class="text-xs text-amber-400">
        ⚠ {{ proposal.warnings[0] }}
      </p>
    </div>
  </Transition>
</template>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
