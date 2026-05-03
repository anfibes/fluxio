<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'

const props = defineProps<{ proposal: ActionProposal }>()

const isReady    = computed(() => props.proposal.status === 'ready')
const isDraft    = computed(() => props.proposal.status === 'draft')
const isExecuted = computed(() => props.proposal.status === 'executed')
</script>

<template>
  <div class="flex h-full flex-col">
    <!-- Status header -->
    <ProposalStatusBanner :proposal="proposal" />

    <!-- Scrollable body -->
    <div class="flex-1 overflow-y-auto">
      <!-- Editable fields -->
      <div class="px-4 pb-2 pt-4">
        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-[var(--color-muted)]">{{ $t('proposal.fields') }}</p>
        <ProposalEditableProposalField
          v-for="field in proposal.editable_fields"
          :key="field.key"
          :field="field"
        />
      </div>

      <!-- Missing fields warning -->
      <ProposalMissingInformationPanel
        v-if="proposal.missing.length"
        :fields="proposal.missing"
      />

      <!-- Proposed changes -->
      <ProposalProposedChangesList :changes="proposal.changes" />

      <!-- Execution result -->
      <ProposalExecutionResultPanel
        v-if="isExecuted && proposal.execution_result"
        :result="proposal.execution_result"
      />
    </div>

    <!-- Footer — hidden when already executed -->
    <div v-if="!isExecuted" class="flex gap-2 border-t border-[var(--color-border)] p-4">
      <button
        type="button"
        class="flex-1 rounded-lg py-2 text-sm font-medium transition-colors"
        :disabled="!isReady"
        :class="isReady
          ? 'bg-[var(--color-accent)] text-white hover:bg-[var(--color-accent-hover)]'
          : 'cursor-not-allowed bg-[var(--color-surface-raised)] text-[var(--color-muted)]'"
      >
        {{ isDraft ? $t('proposal.complete_to_confirm') : $t('proposal.confirm_execute') }}
      </button>
    </div>
  </div>
</template>
