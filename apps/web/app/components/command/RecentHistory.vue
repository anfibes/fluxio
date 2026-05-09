<script setup lang="ts">
defineProps<{ history: readonly string[] }>()
const emit = defineEmits<{ select: [command: string] }>()
</script>

<template>
  <div v-if="history.length" class="flex flex-col gap-2">
    <p class="text-xs font-medium uppercase tracking-wide text-muted">
      {{ $t('command.recent') }}
    </p>
    <div class="flex flex-col gap-1">
      <button
        v-for="cmd in history"
        :key="cmd"
        type="button"
        class="truncate rounded-lg border border-border bg-surface px-3 py-1.5 text-left text-xs text-text-muted transition-colors hover:border-border-subtle hover:bg-surface-raised hover:text-text"
        @click="emit('select', cmd)"
      >
        {{ cmd }}
      </button>
    </div>
  </div>
</template>
