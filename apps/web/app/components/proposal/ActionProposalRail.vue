<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'

const props = defineProps<{ proposal: ActionProposal | null; loading?: boolean }>()
const emit = defineEmits<{ 'confirm-execute': []; 'resolve-ambiguity': [text: string] }>()

const isReady    = computed(() => props.proposal?.status === 'ready')
const isDraft    = computed(() => props.proposal?.status === 'draft')
const isExecuted = computed(() => props.proposal?.status === 'executed')
const isFailed   = computed(() => props.proposal?.status === 'failed')

const canConfirm = computed(() => isReady.value && !props.loading)

const exampleCommands = [
  'Create a follow-up task for Rossini tomorrow at 10am',
  'Add a new lead from the marketing conference',
]
</script>

<template>
  <div class="flex h-full flex-col">
    <Transition name="rail-fade" mode="out-in">

      <!-- ── Empty state ───────────────────────────────────── -->
      <div v-if="!proposal" key="empty" class="flex flex-1 flex-col items-center justify-center gap-5 p-8 text-center">
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-raised text-2xl">
          ⚡
        </div>
        <div class="flex flex-col gap-2">
          <p class="text-sm font-semibold text-text-muted">{{ $t('proposal.empty_title') }}</p>
          <p class="max-w-52.5 text-xs leading-relaxed text-muted">
            {{ $t('proposal.empty_description') }}
          </p>
        </div>
        <div class="flex w-full flex-col gap-1.5">
          <p class="text-xs text-muted">{{ $t('proposal.empty_try') }}</p>
          <div
            v-for="ex in exampleCommands"
            :key="ex"
            class="rounded-lg border border-border bg-surface-raised px-3 py-2 text-left text-xs italic text-text-muted"
          >
            "{{ ex }}"
          </div>
        </div>
      </div>

      <!-- ── Proposal content ──────────────────────────────── -->
      <div v-else key="content" class="flex h-full flex-col">
        <!-- Status header -->
        <ProposalStatusBanner :proposal="proposal" />

        <!-- Scrollable body -->
        <div class="flex-1 overflow-y-auto">
          <!-- Editable fields -->
          <div class="px-4 pb-2 pt-4">
            <p class="mb-3 text-xs font-medium uppercase tracking-wide text-muted">
              {{ $t('proposal.fields') }}
            </p>
            <ProposalEditableProposalField
              v-for="field in proposal.editable_fields"
              :key="field.key"
              :field="field"
            />
          </div>

          <!-- Ambiguity resolution -->
          <ProposalAmbiguityPanel
            v-if="proposal.ambiguities?.length"
            :proposal="proposal"
            :loading="loading"
            @resolve="emit('resolve-ambiguity', $event)"
          />

          <!-- Missing fields warning -->
          <ProposalMissingInformationPanel
            v-if="proposal.missing.length"
            :fields="proposal.missing"
          />

          <!-- Proposed changes -->
          <ProposalProposedChangesList :changes="proposal.changes" />

          <!-- Last refinement feedback -->
          <ProposalLastRefinementPanel
            v-if="proposal.last_refinement"
            :refinement="proposal.last_refinement"
          />

          <!-- Failure reason -->
          <div
            v-if="isFailed && proposal.failure_reason"
            class="mx-4 mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2.5 text-xs text-red-400"
          >
            <p class="mb-1 font-semibold">{{ $t('proposal.execution_failed') }}</p>
            <p class="leading-relaxed opacity-80">{{ proposal.failure_reason }}</p>
          </div>

          <!-- Execution result -->
          <ProposalExecutionResultPanel
            v-if="isExecuted && proposal.execution_result"
            :result="proposal.execution_result"
          />
        </div>

        <!-- Footer — hidden when already executed or failed -->
        <div v-if="!isExecuted && !isFailed" class="flex gap-2 border-t border-border p-4">
          <button
            type="button"
            class="flex flex-1 items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-medium transition-colors"
            :disabled="!canConfirm"
            :class="canConfirm
              ? 'bg-accent text-white hover:bg-accent-hover'
              : 'cursor-not-allowed bg-surface-raised text-muted'"
            @click="emit('confirm-execute')"
          >
            <span v-if="loading" class="h-2 w-2 animate-pulse rounded-full bg-current opacity-60" />
            {{ loading ? $t('proposal.confirming') : isDraft ? $t('proposal.complete_to_confirm') : $t('proposal.confirm_execute') }}
          </button>
        </div>
      </div>

    </Transition>
  </div>
</template>

<style scoped>
.rail-fade-enter-active,
.rail-fade-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}
.rail-fade-enter-from,
.rail-fade-leave-to {
  opacity: 0;
  transform: translateY(4px);
}
</style>
