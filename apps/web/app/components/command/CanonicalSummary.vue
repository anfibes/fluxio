<script setup lang="ts">
/**
 * Read-only, purely presentational display of the backend-provided
 * `canonical_phrase` (Intent Narration projection). It renders the phrase as a
 * human-friendly summary near the command/refinement input — never reconstructs,
 * infers, or fetches it, and never duplicates the right rail's operational truth.
 * Renders nothing when no phrase is present.
 */
const props = defineProps<{
  canonicalPhrase: string | null
}>()

// Defensive: treat a blank/whitespace phrase as absent.
const phrase = computed(() => {
  const value = props.canonicalPhrase?.trim()
  return value && value.length > 0 ? value : null
})
</script>

<template>
  <Transition name="summary-fade">
    <div v-if="phrase" class="summary">
      <p class="summary-label">{{ $t('command.summary') }}</p>
      <p class="summary-phrase">{{ phrase }}</p>
    </div>
  </Transition>
</template>

<style scoped>
.summary {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  border-radius: 0.875rem;
  border: 1px solid var(--color-border-subtle);
  border-left: 2px solid color-mix(in srgb, var(--color-accent) 55%, transparent);
  background-color: color-mix(in srgb, var(--color-surface) 70%, transparent);
  padding: 0.625rem 0.875rem 0.6875rem;
}

.summary-label {
  font-size: 0.625rem;
  font-weight: 500;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--color-muted);
  user-select: none;
}

.summary-phrase {
  font-size: 0.9375rem;
  line-height: 1.5;
  color: var(--color-text);
}

.summary-fade-enter-active,
.summary-fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.summary-fade-enter-from,
.summary-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
