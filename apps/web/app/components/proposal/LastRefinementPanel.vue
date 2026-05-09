<script setup lang="ts">
import type { ActionProposalRefinement } from '~/types/actions'

defineProps<{ refinement: ActionProposalRefinement }>()
</script>

<template>
  <div class="mx-4 mb-4 overflow-hidden rounded-xl border border-border bg-surface-raised">
    <div class="border-b border-border px-3.5 py-2">
      <p class="text-xs font-medium uppercase tracking-wide text-muted">Last update</p>
    </div>
    <div class="flex flex-col gap-2 px-3.5 py-3">
      <p class="text-xs italic leading-relaxed text-text-muted">"{{ refinement.text }}"</p>
      <p class="text-xs text-muted">{{ refinement.summary }}</p>
      <ul v-if="refinement.changes.length" class="flex flex-col gap-1 pt-0.5">
        <li
          v-for="change in refinement.changes"
          :key="change.field"
          class="flex items-center gap-1.5 text-xs"
        >
          <span class="text-muted">{{ change.label }}</span>
          <span class="text-border-subtle">→</span>
          <span class="font-medium text-text-muted">{{ change.to ?? '—' }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>
