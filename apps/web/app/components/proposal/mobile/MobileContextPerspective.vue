<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'
import { formatFieldValue } from './mobileProposalFormatters'
import MobileTechnicalDetailsDrawer from './MobileTechnicalDetailsDrawer.vue'

const props = defineProps<{
  proposal: ActionProposal | null
}>()

// ── Derived data ───────────────────────────────────────────────────

const entityEntries = computed<[string, unknown][]>(() => {
  const entities = props.proposal?.entities
  if (!entities || typeof entities !== 'object') return []
  return Object.entries(entities)
})

const editableFields = computed(() => props.proposal?.editable_fields ?? [])

const populatedFields = computed(() =>
  editableFields.value.filter(f => f.value != null && f.value !== ''),
)

const changesList = computed(() => props.proposal?.changes ?? [])

const hasContextData = computed(() =>
  entityEntries.value.length > 0 || populatedFields.value.length > 0 || changesList.value.length > 0,
)
</script>

<template>
  <div>
    <!-- Empty state -->
    <div v-if="!hasContextData" class="mx-4 mt-4 rounded-xl border border-border bg-surface px-4 py-6 text-center">
      <p class="text-sm font-medium text-text-muted">No context data yet</p>
      <p class="mt-1 text-xs leading-relaxed text-muted">
        Context information will appear here as the proposal is interpreted and entities are resolved.
      </p>
    </div>

    <!-- Entities -->
    <div v-if="entityEntries.length" class="mx-4 mt-3 overflow-hidden rounded-xl border border-border bg-surface">
      <div class="border-b border-border px-4 py-2.5">
        <p class="text-[11px] font-medium uppercase tracking-wider text-muted/60">Entities</p>
      </div>
      <div class="px-4 py-3">
        <div class="flex flex-col gap-2">
          <div
            v-for="[key, value] in entityEntries"
            :key="key"
            class="flex items-center justify-between gap-3"
          >
            <span class="min-w-0 text-xs text-muted">{{ key }}</span>
            <span class="shrink-0 text-right text-xs font-medium text-text">
              {{ formatFieldValue(value) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Operational fields -->
    <div v-if="populatedFields.length" class="mx-4 mt-3 overflow-hidden rounded-xl border border-border bg-surface">
      <div class="border-b border-border px-4 py-2.5">
        <p class="text-[11px] font-medium uppercase tracking-wider text-muted/60">Fields</p>
      </div>
      <div class="px-4 py-3">
        <div class="flex flex-col gap-2">
          <div
            v-for="field in populatedFields"
            :key="field.key"
            class="flex items-center justify-between gap-3"
          >
            <span class="min-w-0 text-xs text-muted">{{ field.label }}</span>
            <span class="shrink-0 text-right text-xs font-medium text-text">
              {{ formatFieldValue(field.value) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Proposed changes -->
    <div v-if="changesList.length" class="mx-4 mt-3 overflow-hidden rounded-xl border border-border bg-surface">
      <div class="border-b border-border px-4 py-2.5">
        <p class="text-[11px] font-medium uppercase tracking-wider text-muted/60">Actions</p>
      </div>
      <div class="px-4 py-3">
        <div class="flex flex-col gap-1.5">
          <div
            v-for="(change, i) in changesList"
            :key="i"
            class="flex items-center gap-2 rounded-md bg-surface-raised px-2.5 py-1.5"
          >
            <span class="shrink-0 rounded bg-accent/15 px-1.5 py-0.5 font-mono text-[10px] uppercase text-accent">
              {{ change.type }}
            </span>
            <span class="min-w-0 flex-1 truncate text-xs text-text">{{ change.label }}</span>
            <span class="shrink-0 text-xs text-muted">{{ change.module }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Technical details -->
    <MobileTechnicalDetailsDrawer :proposal="proposal" />
  </div>
</template>
