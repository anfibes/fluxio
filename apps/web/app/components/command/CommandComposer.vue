<script setup lang="ts">
import type { ProposalConversationTurn } from '~/composables/useActionProposal'

const props = defineProps<{
  modelValue: string
  loading?: boolean
  placeholder?: string
  trail?: readonly ProposalConversationTurn[]
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
    <!-- Conversation trail (current session, user turns only) -->
    <div v-if="trail && trail.length" class="composer-trail">
      <p class="composer-trail-title">{{ $t('command.trail.title') }}</p>
      <ul class="composer-trail-list">
        <li v-for="turn in trail" :key="turn.id" class="composer-trail-turn">
          <span class="composer-trail-role">{{ $t('command.trail.you') }}</span>
          <span class="composer-trail-text">{{ turn.text }}</span>
        </li>
      </ul>
    </div>

    <textarea
      ref="textareaEl"
      :value="modelValue"
      rows="4"
      autofocus
      :placeholder="placeholder ?? $t('command.placeholder')"
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

/* ── Conversation trail ─────────────────────────────────── */
.composer-trail {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  max-height: 9rem;
  overflow-y: auto;
  padding: 0.625rem 1.125rem;
  border-bottom: 1px solid var(--color-border-subtle);
  background-color: var(--color-surface-raised);
  border-top-left-radius: 0.875rem;
  border-top-right-radius: 0.875rem;
}

.composer-trail-title {
  font-size: 0.625rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-muted);
}

.composer-trail-list {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.composer-trail-turn {
  display: flex;
  flex-direction: column;
  gap: 0.0625rem;
}

.composer-trail-role {
  font-size: 0.625rem;
  font-weight: 600;
  color: var(--color-muted);
}

.composer-trail-text {
  font-size: 0.8125rem;
  line-height: 1.4;
  color: var(--color-text-muted);
  word-break: break-word;
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

@media (max-width: 767px) {
  .composer-hint {
    display: none;
  }

  .composer-btn {
    min-height: 2.25rem;
    padding: 0.375rem 1.125rem;
  }
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
