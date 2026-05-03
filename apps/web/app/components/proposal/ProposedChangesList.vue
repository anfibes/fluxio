<script setup lang="ts">
import type { ProposedChange } from '~/types/actions'

defineProps<{ changes: ProposedChange[] }>()
</script>

<template>
  <div class="px-4 pb-4">
    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-[var(--color-muted)]">Actions</p>
    <div class="flex flex-col gap-2">
      <div
        v-for="(change, i) in changes"
        :key="i"
        class="flex items-center gap-2.5 rounded-lg bg-[var(--color-surface-raised)] p-2.5"
      >
        <span class="rounded bg-[var(--color-accent)]/15 px-1.5 py-0.5 font-mono text-xs uppercase text-[var(--color-accent)]">
          {{ change.type }}
        </span>
        <span class="flex-1 text-xs text-[var(--color-text)]">{{ change.label }}</span>
        <span class="text-xs text-[var(--color-muted)]">{{ change.module }}</span>
      </div>
    </div>
  </div>
</template>
