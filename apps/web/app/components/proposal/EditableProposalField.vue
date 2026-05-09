<script setup lang="ts">
import type { EditableField, EditableFieldSource } from '~/types/actions'

defineProps<{ field: EditableField }>()

const sourceBadge: Record<EditableFieldSource, { text: string; cls: string }> = {
  detected: { text: 'detected', cls: 'bg-emerald-500/10 text-emerald-400' },
  inferred:  { text: 'inferred', cls: 'bg-blue-500/10 text-blue-400' },
  guessed:   { text: 'guessed',  cls: 'bg-amber-500/10 text-amber-400' },
  computed:  { text: 'computed', cls: 'bg-purple-500/10 text-purple-400' },
  edited:    { text: 'edited',   cls: 'bg-surface-raised text-text-muted' },
  missing:   { text: 'missing',  cls: 'bg-red-500/10 text-red-400' },
}
</script>

<template>
  <div class="flex items-start justify-between gap-3 border-b border-border-subtle py-2.5 last:border-0">
    <div class="flex min-w-0 flex-col gap-0.5">
      <span class="text-xs text-muted">{{ field.label }}</span>
      <span
        class="text-sm leading-snug"
        :class="field.source === 'missing'
          ? 'italic text-muted'
          : 'text-text'"
      >
        {{ field.value !== null && field.value !== undefined ? field.value : '—' }}
      </span>
    </div>
    <span
      class="mt-0.5 shrink-0 rounded px-1.5 py-0.5 font-mono text-xs"
      :class="sourceBadge[field.source].cls"
    >
      {{ sourceBadge[field.source].text }}
    </span>
  </div>
</template>
