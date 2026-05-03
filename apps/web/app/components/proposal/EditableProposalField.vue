<script setup lang="ts">
import type { EditableField, EditableFieldSource } from '~/types/actions'

defineProps<{ field: EditableField }>()

const sourceColors: Record<EditableFieldSource, string> = {
  detected: 'text-emerald-400',
  inferred:  'text-blue-400',
  guessed:   'text-amber-400',
  computed:  'text-purple-400',
  edited:    'text-[var(--color-text-muted)]',
  missing:   'text-red-400',
}
</script>

<template>
  <div class="flex items-center justify-between border-b border-[var(--color-border-subtle)] py-2.5 last:border-0">
    <div class="flex min-w-0 flex-col gap-0.5">
      <span class="text-xs text-[var(--color-muted)]">{{ field.label }}</span>
      <span
        class="text-sm"
        :class="field.source === 'missing'
          ? 'italic text-[var(--color-muted)]'
          : 'text-[var(--color-text)]'"
      >
        {{ field.value ?? '—' }}
      </span>
    </div>
    <span class="ml-4 shrink-0 font-mono text-xs" :class="sourceColors[field.source]">
      {{ field.source }}
    </span>
  </div>
</template>
