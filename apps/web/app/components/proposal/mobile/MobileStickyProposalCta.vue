<script setup lang="ts">
import type { ActionProposalStatus } from '~/types/actions'

const props = defineProps<{
  status: ActionProposalStatus
  confirming?: boolean
}>()

const emit = defineEmits<{
  'confirm-execute': []
}>()

interface CtaState {
  visible: boolean
  label: string
  disabled: boolean
  variant: 'primary' | 'subtle' | 'error' | 'loading'
}

const ctaState = computed<CtaState>(() => {
  if (props.confirming) {
    return { visible: true, label: 'Confirming…', disabled: true, variant: 'loading' }
  }
  switch (props.status) {
    case 'draft':
      return { visible: true, label: 'Complete Proposal', disabled: true, variant: 'subtle' }
    case 'ready':
      return { visible: true, label: 'Confirm & Execute', disabled: false, variant: 'primary' }
    case 'confirmed':
    case 'executed':
      return { visible: false, label: '', disabled: true, variant: 'subtle' }
    case 'failed':
      return { visible: true, label: 'Execution Failed', disabled: true, variant: 'error' }
    default:
      return { visible: false, label: '', disabled: true, variant: 'subtle' }
  }
})
</script>

<template>
  <div
    v-if="ctaState.visible"
    class="mob-cta shrink-0 border-t border-border bg-surface px-4 py-3"
  >
    <button
      type="button"
      class="mob-cta-btn"
      :class="{
        'mob-cta-btn--primary': ctaState.variant === 'primary',
        'mob-cta-btn--subtle': ctaState.variant === 'subtle',
        'mob-cta-btn--error': ctaState.variant === 'error',
        'mob-cta-btn--loading': ctaState.variant === 'loading',
      }"
      :disabled="ctaState.disabled"
      @click="ctaState.variant === 'primary' && emit('confirm-execute')"
    >
      <span
        v-if="ctaState.variant === 'loading'"
        class="mob-cta-dot"
      />
      {{ ctaState.label }}
    </button>
  </div>
</template>

<style scoped>
.mob-cta {
  position: sticky;
  bottom: 0;
  z-index: 10;
  box-shadow: 0 -1px 3px rgba(0, 0, 0, 0.04);
}

.mob-cta-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  font-size: 0.9375rem;
  font-weight: 600;
  transition: background-color 0.15s ease, opacity 0.15s ease;
}

.mob-cta-btn:disabled {
  cursor: not-allowed;
}

.mob-cta-btn--primary {
  background-color: var(--color-accent);
  color: white;
}

.mob-cta-btn--primary:not(:disabled):active {
  background-color: var(--color-accent-hover);
}

.mob-cta-btn--subtle {
  background-color: var(--color-surface-raised);
  color: var(--color-muted);
  opacity: 0.8;
}

.mob-cta-btn--error {
  background-color: rgb(239 68 68 / 0.12);
  color: rgb(239 68 68 / 0.8);
  border: 1px solid rgb(239 68 68 / 0.25);
}

.mob-cta-btn--loading {
  background-color: var(--color-accent);
  color: white;
  opacity: 0.8;
}

.mob-cta-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  background-color: currentColor;
  opacity: 0.7;
  animation: mob-pulse 1s ease-in-out infinite;
}

@keyframes mob-pulse {
  0%, 100% { opacity: 0.7; }
  50%      { opacity: 0.2; }
}
</style>
