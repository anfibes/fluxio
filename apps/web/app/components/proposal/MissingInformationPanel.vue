<script setup lang="ts">
import type { MissingField } from '~/types/actions'

defineProps<{ fields: MissingField[] }>()
</script>

<template>
  <div
    v-if="fields.length"
    class="mx-4 mb-4 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3"
  >
    <p class="mb-2 text-xs font-medium text-amber-400">Missing information</p>
    <ul class="flex flex-col gap-1">
      <li
        v-for="f in fields"
        :key="f.key"
        class="flex items-start gap-2 text-xs text-amber-300/80"
      >
        <span class="mt-0.5 shrink-0 text-amber-500">·</span>
        <span>
          <span class="font-medium">{{ f.label }}</span>
          <span v-if="f.required" class="text-amber-400"> *</span>
          <span v-if="f.message" class="text-amber-300/60"> — {{ f.message }}</span>
        </span>
      </li>
    </ul>
  </div>
</template>
