<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'

const props = defineProps<{ proposal: ActionProposal | null; loading?: boolean }>()
const emit = defineEmits<{ 'confirm-execute': [] }>()

const isReady    = computed(() => props.proposal?.status === 'ready')
const isDraft    = computed(() => props.proposal?.status === 'draft')
const isExecuted = computed(() => props.proposal?.status === 'executed')
const isFailed   = computed(() => props.proposal?.status === 'failed')

const canConfirm = computed(() => isReady.value && !props.loading)
</script>

<template>
  <div class="flex h-full flex-col">

    <!-- ── Empty state ─────────────────────────────────────── -->
    <div v-if="!proposal" class="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
      <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--color-surface-raised)] text-2xl">
        ⚡
      </div>
      <div class="flex flex-col gap-1.5">
        <p class="text-sm font-semibold text-[var(--color-text-muted)]">{{ $t('proposal.empty_title') }}</p>
        <p class="max-w-[200px] text-xs leading-relaxed text-[var(--color-muted)]">
          {{ $t('proposal.empty_description') }}
        </p>
      </div>
    </div>

    <!-- ── Proposal content ────────────────────────────────── -->
    <template v-else>
      <!-- Status header -->
      <ProposalStatusBanner :proposal="proposal" />

      <!-- Scrollable body -->
      <div class="flex-1 overflow-y-auto">
        <!-- Editable fields -->
        <div class="px-4 pb-2 pt-4">
          <p class="mb-3 text-xs font-medium uppercase tracking-wide text-[var(--color-muted)]">
            {{ $t('proposal.fields') }}
          </p>
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

        <!-- Failure reason -->
        <div
          v-if="isFailed && proposal.failure_reason"
          class="mx-4 mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2.5 text-xs text-red-400"
        >
          <p class="mb-1 font-semibold">Execution failed</p>
          <p class="leading-relaxed opacity-80">{{ proposal.failure_reason }}</p>
        </div>

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
          class="flex flex-1 items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-medium transition-colors"
          :disabled="!canConfirm"
          :class="canConfirm
            ? 'bg-[var(--color-accent)] text-white hover:bg-[var(--color-accent-hover)]'
            : 'cursor-not-allowed bg-[var(--color-surface-raised)] text-[var(--color-muted)]'"
          @click="emit('confirm-execute')"
        >
          <span v-if="loading" class="h-2 w-2 animate-pulse rounded-full bg-current opacity-60" />
          {{ loading ? $t('proposal.confirming') : isDraft ? $t('proposal.complete_to_confirm') : $t('proposal.confirm_execute') }}
        </button>
      </div>
    </template>
  </div>
</template>
