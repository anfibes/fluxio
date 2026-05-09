<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'

const props = defineProps<{ proposal: ActionProposal | null }>()

const intentLabel = computed(() =>
  props.proposal ? props.proposal.intent.replace(/_/g, ' ') : '',
)

const confidencePct = computed(() =>
  props.proposal ? Math.round(props.proposal.confidence * 100) : 0,
)

const confidenceColor = computed(() => {
  if (confidencePct.value >= 80) return 'text-emerald-400'
  if (confidencePct.value >= 60) return 'text-amber-400'
  return 'text-red-400'
})

const entityEntries = computed(() =>
  props.proposal ? Object.entries(props.proposal.entities) : [],
)
</script>

<template>
  <Transition name="slide-down">
    <div
      v-if="proposal"
      class="flex flex-col gap-2.5 rounded-xl border border-border bg-surface px-3.5 py-3 text-sm"
    >
      <div class="flex items-center gap-2.5">
        <span class="rounded-md bg-accent/15 px-2 py-0.5 font-mono text-xs capitalize text-accent">
          {{ intentLabel }}
        </span>
        <div class="ml-auto flex items-center gap-1.5">
          <span class="text-xs text-muted">confidence</span>
          <span class="tabular-nums text-xs font-semibold" :class="confidenceColor">{{ confidencePct }}%</span>
        </div>
      </div>

      <div v-if="entityEntries.length" class="flex flex-wrap gap-1.5">
        <span
          v-for="[key, value] in entityEntries"
          :key="key"
          class="flex items-center gap-1 rounded-md bg-surface-raised px-2 py-0.5 text-xs"
        >
          <span class="text-muted">{{ key }}</span>
          <span class="text-text-muted">{{ value }}</span>
        </span>
      </div>

      <p v-if="proposal.warnings.length" class="flex items-center gap-1.5 text-xs text-amber-400">
        <span>⚠</span>
        <span>{{ proposal.warnings[0] }}</span>
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
  transform: translateY(-6px);
}
</style>
