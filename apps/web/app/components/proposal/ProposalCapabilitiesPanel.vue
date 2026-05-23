<script setup lang="ts">
import type { ProposalCapabilities } from '~/types/actions'

const props = defineProps<{ capabilities?: ProposalCapabilities }>()

function formatCapabilityLabel(value: string): string {
  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, c => c.toUpperCase())
}

const rows = computed(() => {
  if (!props.capabilities) return []
  return [
    {
      key: 'ambiguity_resolution',
      labelKey: 'proposal.capabilities.ambiguity_resolution',
      enabled: props.capabilities.supports_ambiguity_resolution,
    },
    {
      key: 'contextual_references',
      labelKey: 'proposal.capabilities.contextual_references',
      enabled: props.capabilities.supports_contextual_references,
    },
    {
      key: 'collection_mutations',
      labelKey: 'proposal.capabilities.collection_mutations',
      enabled: props.capabilities.supports_collection_mutations,
    },
  ]
})

const refinementLabels = computed(() =>
  (props.capabilities?.refinements ?? []).map(formatCapabilityLabel),
)

const mutationFields = computed(() => {
  const fields = (props.capabilities?.mutations ?? []).flatMap(m => m.fields)
  return Array.from(new Set(fields)).map(formatCapabilityLabel)
})
</script>

<template>
  <div
    v-if="capabilities"
    class="mx-4 mb-4 rounded-lg border border-border bg-surface-raised px-3.5 py-3"
  >
    <p class="mb-2 text-xs font-medium text-muted">
      {{ $t('proposal.capabilities.title') }}
    </p>
    <ul class="flex flex-col gap-1">
      <li
        v-for="row in rows"
        :key="row.key"
        class="flex items-center justify-between text-xs text-muted"
      >
        <span>{{ $t(row.labelKey) }}</span>
        <span
          :class="row.enabled ? 'text-text-muted' : 'text-muted/50'"
        >
          {{ row.enabled ? $t('proposal.capabilities.supported') : $t('proposal.capabilities.not_supported') }}
        </span>
      </li>
    </ul>

    <div
      v-if="refinementLabels.length"
      class="mt-3 border-t border-border/60 pt-2"
    >
      <p class="mb-1 text-xs text-muted/70">
        {{ $t('proposal.capabilities.supported_refinements') }}
      </p>
      <p class="text-xs leading-relaxed text-text-muted">
        {{ refinementLabels.join(', ') }}
      </p>
    </div>

    <div
      v-if="mutationFields.length"
      class="mt-2"
    >
      <p class="mb-1 text-xs text-muted/70">
        {{ $t('proposal.capabilities.allowed_fields') }}
      </p>
      <p class="text-xs leading-relaxed text-text-muted">
        {{ mutationFields.join(', ') }}
      </p>
    </div>
  </div>
</template>
