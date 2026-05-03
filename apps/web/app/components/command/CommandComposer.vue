<script setup lang="ts">
const text = ref('')
</script>

<template>
  <div class="flex flex-col gap-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
    <textarea
      v-model="text"
      rows="3"
      placeholder="Describe what you want to do…"
      class="w-full resize-none bg-transparent text-sm leading-relaxed text-[var(--color-text)] outline-none placeholder:text-[var(--color-muted)]"
    />
    <div class="flex items-center justify-between border-t border-[var(--color-border-subtle)] pt-3">
      <span class="text-xs text-[var(--color-muted)]">⌘ ↵ to propose</span>
      <button
        type="button"
        class="rounded-lg bg-[var(--color-accent)] px-4 py-1.5 text-sm font-medium text-white transition-colors hover:bg-[var(--color-accent-hover)]"
      >
        Propose
      </button>
    </div>
  </div>
</template>
