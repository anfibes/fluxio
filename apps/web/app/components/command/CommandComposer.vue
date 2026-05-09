<script setup lang="ts">
const props = defineProps<{
  modelValue: string
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  submit: []
  clear: []
}>()

const textareaEl = ref<HTMLTextAreaElement | null>(null)
const canSubmit = computed(() => props.modelValue.trim().length > 0 && !props.loading)

function focus() {
  textareaEl.value?.focus()
}

defineExpose({ focus })

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    emit('clear')
    return
  }
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    if (canSubmit.value) emit('submit')
  }
}
</script>

<template>
  <div class="composer-wrap" :class="{ 'composer-wrap--loading': loading }">
    <textarea
      ref="textareaEl"
      :value="modelValue"
      rows="4"
      autofocus
      :placeholder="$t('command.placeholder')"
      :disabled="loading"
      class="composer-input"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
      @keydown="handleKeydown"
    />
    <div class="composer-footer">
      <span class="composer-hint">{{ $t('command.hint') }}</span>
      <button
        type="button"
        :disabled="!canSubmit"
        class="composer-btn"
        :class="canSubmit ? 'composer-btn--active' : 'composer-btn--idle'"
        @click="emit('submit')"
      >
        <span v-if="loading" class="loading-dot" />
        {{ loading ? $t('command.interpreting') : $t('command.propose') }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.composer-wrap {
  display: flex;
  flex-direction: column;
  border-radius: 0.875rem;
  border: 1px solid var(--color-border);
  background-color: var(--color-surface);
  transition: border-color 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
}

.composer-wrap:focus-within {
  border-color: var(--color-accent);
  box-shadow: 0 0 0 3px rgb(99 102 241 / 0.12);
}

.composer-wrap--loading {
  opacity: 0.75;
  pointer-events: none;
}

.composer-input {
  width: 100%;
  resize: none;
  background: transparent;
  padding: 1rem 1.125rem 0.625rem;
  font-size: 0.9375rem;
  line-height: 1.6;
  color: var(--color-text);
  outline: none;
  font-family: inherit;
}

.composer-input::placeholder {
  color: var(--color-muted);
}

.composer-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.625rem 1rem 0.75rem;
  border-top: 1px solid var(--color-border-subtle);
}

.composer-hint {
  font-size: 0.6875rem;
  color: var(--color-muted);
  user-select: none;
}

.composer-btn {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  border-radius: 0.5rem;
  padding: 0.375rem 0.875rem;
  font-size: 0.8125rem;
  font-weight: 500;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.composer-btn:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.composer-btn--active {
  background-color: var(--color-accent);
  color: white;
}

.composer-btn--active:not(:disabled):hover {
  background-color: var(--color-accent-hover);
}

.composer-btn--idle {
  background-color: var(--color-surface-raised);
  color: var(--color-muted);
}

.loading-dot {
  width: 0.4375rem;
  height: 0.4375rem;
  border-radius: 50%;
  background-color: currentColor;
  opacity: 0.6;
  animation: pulse 1s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 0.2; }
}
</style>
