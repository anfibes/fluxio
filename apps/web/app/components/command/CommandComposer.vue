<script setup lang="ts">
const props = defineProps<{
  modelValue: string
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  submit: []
}>()

const canSubmit = computed(() => props.modelValue.trim().length > 0 && !props.loading)

function handleKeydown(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key === 'Enter' && canSubmit.value) {
    emit('submit')
  }
}
</script>

<template>
  <div class="flex flex-col gap-3 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
    <textarea
      :value="modelValue"
      rows="3"
      placeholder="Describe what you want to do…"
      :disabled="loading"
      class="w-full resize-none bg-transparent text-sm leading-relaxed text-[var(--color-text)] outline-none placeholder:text-[var(--color-muted)] disabled:opacity-60"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
      @keydown="handleKeydown"
    />
    <div class="flex items-center justify-between border-t border-[var(--color-border-subtle)] pt-3">
      <span class="text-xs text-[var(--color-muted)]">⌘ ↵ to propose</span>
      <button
        type="button"
        :disabled="!canSubmit"
        class="rounded-lg px-4 py-1.5 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40"
        :class="canSubmit
          ? 'bg-[var(--color-accent)] text-white hover:bg-[var(--color-accent-hover)]'
          : 'bg-[var(--color-surface-raised)] text-[var(--color-muted)]'"
        @click="emit('submit')"
      >
        {{ loading ? 'Interpreting…' : 'Propose' }}
      </button>
    </div>
  </div>
</template>
