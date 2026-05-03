<script setup lang="ts">
const starters = [
  { icon: '+', label: 'New task' },
  { icon: '↗', label: 'New lead' },
  { icon: '⊙', label: 'Schedule meeting' },
  { icon: '✉', label: 'Send follow-up' },
  { icon: '✓', label: 'Mark complete' },
]
</script>

<template>
  <div class="flex flex-col gap-2">
    <p class="text-xs font-medium uppercase tracking-wide text-[var(--color-muted)]">Quick actions</p>
    <div class="flex flex-wrap gap-2">
      <button
        v-for="s in starters"
        :key="s.label"
        type="button"
        class="flex items-center gap-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-1.5 text-xs text-[var(--color-text-muted)] transition-colors hover:bg-[var(--color-surface-raised)] hover:text-[var(--color-text)]"
      >
        <span class="text-[var(--color-muted)]">{{ s.icon }}</span>
        {{ s.label }}
      </button>
    </div>
  </div>
</template>
