<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'

const props = defineProps<{ proposal: ActionProposal | null; loading?: boolean }>()
const emit = defineEmits<{ 'confirm-execute': []; 'resolve-ambiguity': [text: string]; 'reset': [] }>()

const isReady    = computed(() => props.proposal?.status === 'ready')
const isDraft    = computed(() => props.proposal?.status === 'draft')
const isExecuted = computed(() => props.proposal?.status === 'executed')
const isFailed   = computed(() => props.proposal?.status === 'failed')

const canConfirm = computed(() => isReady.value && !props.loading)

// ── Operational hierarchy helpers ───────────────────────────

const blockingAmbiguities = computed(() =>
  props.proposal?.ambiguities?.filter(a => a.blocking && a.selected_candidate_id === null) ?? [],
)

const requiredMissingFields = computed(() =>
  props.proposal?.missing?.filter(f => f.required) ?? [],
)

const optionalMissingFields = computed(() =>
  props.proposal?.missing?.filter(f => !f.required) ?? [],
)

const hasBlockingItems = computed(() =>
  blockingAmbiguities.value.length > 0 || requiredMissingFields.value.length > 0,
)

const hasWarnings = computed(() =>
  (props.proposal?.warnings?.length ?? 0) > 0,
)

const hasInformationalItems = computed(() =>
  hasWarnings.value || optionalMissingFields.value.length > 0,
)

const hasExecutionOutcome = computed(() => isExecuted.value || isFailed.value)

const hasOutcome = computed(() =>
  !!props.proposal?.last_refinement || hasExecutionOutcome.value,
)

// ── Dev example commands ─────────────────────────────────────

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
        <ProposalStatusBanner :proposal="proposal">
          <template #actions>
            <button
              type="button"
              class="flex h-6 w-6 items-center justify-center rounded text-muted/50 transition-colors hover:bg-surface-raised hover:text-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
              :title="$t('proposal.reset_proposal')"
              :aria-label="$t('proposal.reset_proposal')"
              @click="emit('reset')"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                <polyline points="1 4 1 10 7 10" />
                <path d="M3.51 15a9 9 0 1 0 .49-4.99" />
              </svg>
            </button>
          </template>
        </ProposalStatusBanner>

        <!-- Scrollable body -->
        <div class="flex-1 overflow-y-auto">

          <!-- ── A. BLOCKING ──────────────────────────────── -->
          <template v-if="hasBlockingItems">
            <p class="px-4 pb-1.5 pt-4 text-xs font-medium uppercase tracking-wide text-muted/60">
              {{ $t('proposal.sections.blocking') }}
            </p>
            <ProposalAmbiguityPanel
              v-if="blockingAmbiguities.length"
              :ambiguities="blockingAmbiguities"
              :loading="loading"
              @resolve="emit('resolve-ambiguity', $event)"
            />
            <ProposalMissingInformationPanel
              v-if="requiredMissingFields.length"
              :fields="requiredMissingFields"
            />
          </template>

          <!-- ── B. ACTIONABLE ────────────────────────────── -->
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
          <ProposalProposedChangesList :changes="proposal.changes" />

          <!-- ── C. INFORMATIONAL ─────────────────────────── -->
          <template v-if="hasInformationalItems">
            <p class="px-4 pb-1.5 pt-1 text-xs font-medium uppercase tracking-wide text-muted/60">
              {{ $t('proposal.sections.informational') }}
            </p>
            <!-- Warnings -->
            <div
              v-if="hasWarnings"
              class="mx-4 mb-4 rounded-lg border border-border bg-surface-raised px-3.5 py-3"
            >
              <ul class="flex flex-col gap-1">
                <li
                  v-for="(warning, i) in proposal.warnings"
                  :key="i"
                  class="flex items-start gap-2 text-xs text-muted"
                >
                  <span class="mt-0.5 shrink-0 text-amber-500/70">·</span>
                  <span class="leading-relaxed">{{ warning }}</span>
                </li>
              </ul>
            </div>
            <!-- Optional missing fields -->
            <ProposalMissingInformationPanel
              v-if="optionalMissingFields.length"
              :fields="optionalMissingFields"
            />
          </template>

          <!-- ── D. OUTCOME ───────────────────────────────── -->
          <template v-if="hasOutcome">
            <p class="px-4 pb-1.5 pt-1 text-xs font-medium uppercase tracking-wide text-muted/60">
              {{ $t('proposal.sections.outcome') }}
            </p>
            <ProposalLastRefinementPanel
              v-if="proposal.last_refinement"
              :refinement="proposal.last_refinement"
            />
            <div
              v-if="isFailed && proposal.failure_reason"
              class="mx-4 mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2.5 text-xs text-red-400"
            >
              <p class="mb-1 font-semibold">{{ $t('proposal.execution_failed') }}</p>
              <p class="leading-relaxed opacity-80">{{ proposal.failure_reason }}</p>
            </div>
            <ProposalExecutionResultPanel
              v-if="isExecuted && proposal.execution_result"
              :result="proposal.execution_result"
            />
          </template>

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
