<script setup lang="ts">
defineProps<{ result: Record<string, unknown> }>()
</script>

<template>
  <div class="mx-4 mb-4 overflow-hidden rounded-xl border border-emerald-500/25 bg-emerald-500/8">
    <div class="flex items-center gap-2 border-b border-emerald-500/15 px-3.5 py-2.5">
      <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/20 text-xs text-emerald-400">✓</span>
      <p class="text-xs font-semibold text-emerald-400">{{ $t('proposal.executed_successfully') }}</p>
    </div>
    <dl class="flex flex-col gap-0 divide-y divide-emerald-500/10">
      <div
        v-for="(value, key) in result"
        :key="key"
        class="flex items-center justify-between px-3.5 py-2 text-xs"
      >
        <dt class="text-emerald-400/60">{{ key }}</dt>
        <dd class="font-medium text-emerald-300">{{ value }}</dd>
      </div>
    </dl>
  </div>
</template>
