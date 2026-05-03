<script setup lang="ts">
defineProps<{ result: Record<string, unknown> }>()
</script>

<template>
  <div class="mx-4 mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3">
    <p class="mb-2 flex items-center gap-1.5 text-xs font-medium text-emerald-400">
      <span>✓</span> Executed successfully
    </p>
    <dl class="flex flex-col gap-1">
      <div
        v-for="(value, key) in result"
        :key="key"
        class="flex items-center gap-2 text-xs"
      >
        <dt class="min-w-0 shrink-0 text-emerald-400/60">{{ key }}</dt>
        <dd class="truncate text-emerald-300/80">{{ value }}</dd>
      </div>
    </dl>
  </div>
</template>
