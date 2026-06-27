<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'
import ProposalAmbiguityPanel from '../AmbiguityPanel.vue'
import ProposalMissingInformationPanel from '../MissingInformationPanel.vue'
import ProposalRefinementHints from '../ProposalRefinementHints.vue'
import ProposalLastRefinementPanel from '../LastRefinementPanel.vue'
import ProposalExecutionResultPanel from '../ExecutionResultPanel.vue'
import MobileTechnicalDetailsDrawer from './MobileTechnicalDetailsDrawer.vue'
import { formatFieldValue } from './mobileProposalFormatters'

const props = defineProps<{
  proposal: ActionProposal | null
  loading?: boolean
}>()

const emit = defineEmits<{
  'resolve-ambiguity': [text: string]
}>()

// ── Status derivations ─────────────────────────────────────────────

const isExecuted = computed(() => props.proposal?.status === 'executed')
const isFailed   = computed(() => props.proposal?.status === 'failed')

const blockingAmbiguities = computed(() =>
  props.proposal?.ambiguities?.filter(a => a.blocking) ?? [],
)

const requiredMissingFields = computed(() =>
  props.proposal?.missing?.filter(f => f.required) ?? [],
)

const hasBlockingItems = computed(() =>
  blockingAmbiguities.value.length > 0 || requiredMissingFields.value.length > 0,
)

// ── Card data ──────────────────────────────────────────────────────

const displayPhrase = computed(() => {
  const phrase = props.proposal?.canonical_phrase?.trim()
  return phrase && phrase.length > 0 ? phrase : null
})

const cardHeadline = computed(() => {
  if (displayPhrase.value) return displayPhrase.value
  const p = props.proposal
  if (!p) return null
  return p.intent ? p.intent.replace(/_/g, ' ') : null
})

const sourceText = computed(() => props.proposal?.source_text ?? '')

const allFields = computed(() => props.proposal?.editable_fields ?? [])

const populatedFields = computed(() =>
  allFields.value.filter(f => f.value != null && f.value !== ''),
)

const missingFields = computed(() =>
  allFields.value.filter(f => f.value == null || f.value === ''),
)

const missingRequiredFields = computed(() =>
  missingFields.value.filter(f => f.required),
)

const missingOptionalFields = computed(() =>
  missingFields.value.filter(f => !f.required),
)

const changesList = computed(() => props.proposal?.changes ?? [])
const warningsList = computed(() => props.proposal?.warnings ?? [])

const executionFailureMessage = computed(() =>
  props.proposal?.execution_failure?.message ?? props.proposal?.failure_reason ?? '',
)

// ── Narrative layer (human-readable proposal state) ────────────────

interface NarrativeCue {
  eyebrow: string
  message: string
  variant: 'info' | 'ready' | 'incomplete' | 'success' | 'error'
}

const narrativeCue = computed<NarrativeCue>(() => {
  const p = props.proposal
  if (!p) return { eyebrow: '', message: '', variant: 'info' }

  const status = p.status
  const hasMissing = missingRequiredFields.value.length > 0
  const hasAmbiguities = blockingAmbiguities.value.length > 0

  if (status === 'executed') {
    return { eyebrow: 'Action complete',  message: 'This proposal was executed successfully.',       variant: 'success' }
  }
  if (status === 'failed') {
    return { eyebrow: 'Action failed',    message: 'This action could not be completed.',             variant: 'error' }
  }
  if (status === 'ready') {
    return { eyebrow: 'Ready to execute', message: 'Fluxio is ready to execute this proposal.',       variant: 'ready' }
  }
  if (status === 'confirmed') {
    return { eyebrow: 'Confirmed',        message: 'This proposal has been confirmed.',                variant: 'info' }
  }
  if (status === 'draft') {
    if (hasMissing) {
      return { eyebrow: 'Needs details',   message: 'Add the missing information to make this proposal ready.', variant: 'incomplete' }
    }
    if (hasAmbiguities) {
      return { eyebrow: 'Needs clarification', message: 'Choose the right match to continue.',               variant: 'incomplete' }
    }
    return { eyebrow: 'Fluxio understood', message: 'Review the proposal. Refine or add details as needed.',  variant: 'info' }
  }
  return { eyebrow: '', message: '', variant: 'info' }
})
</script>

<template>
  <div>
    <!-- Blocking items -->
    <template v-if="hasBlockingItems">
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

    <!-- Proposal Card -->
    <div
      class="mx-4 mt-3 overflow-hidden rounded-xl border border-border bg-surface"
      :class="{ 'opacity-60': isExecuted }"
    >
      <div class="px-4 pt-5 pb-4">
        <!-- Narrative cue -->
        <div v-if="narrativeCue.eyebrow" class="flex items-start gap-2 mb-3">
          <span
            class="shrink-0 mt-px rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
            :class="{
              'bg-accent/12 text-accent': narrativeCue.variant === 'info',
              'bg-emerald-500/12 text-emerald-400': narrativeCue.variant === 'ready' || narrativeCue.variant === 'success',
              'bg-amber-500/12 text-amber-400': narrativeCue.variant === 'incomplete',
              'bg-red-500/12 text-red-400': narrativeCue.variant === 'error',
            }"
          >
            {{ narrativeCue.eyebrow }}
          </span>
          <span class="text-[11px] leading-relaxed text-muted">{{ narrativeCue.message }}</span>
        </div>

        <!-- Headline -->
        <p v-if="cardHeadline" class="text-lg font-semibold leading-snug text-text">
          {{ cardHeadline }}
        </p>
        <p v-if="sourceText" class="mt-1.5 text-xs italic text-muted/70">
          "{{ sourceText }}"
        </p>
      </div>

      <div class="border-t border-border-subtle" />

      <!-- Populated fields -->
      <div v-if="populatedFields.length" class="px-4 py-3.5">
        <p class="mb-3 text-[11px] font-medium uppercase tracking-wider text-muted/60">What I found</p>
        <div class="flex flex-col gap-2.5">
          <div
            v-for="field in populatedFields"
            :key="field.key"
            class="flex items-baseline justify-between gap-3"
          >
            <span class="min-w-0 text-[11px] text-muted/70">{{ field.label }}</span>
            <span class="shrink-0 text-right text-sm font-medium text-text">
              {{ formatFieldValue(field.value) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Missing: required fields -->
      <div v-if="missingRequiredFields.length" class="border-t border-border-subtle px-4 py-3.5">
        <p class="mb-3 text-[11px] font-medium uppercase tracking-wider text-muted/60">Still needed</p>
        <div class="flex flex-col gap-2">
          <div
            v-for="field in missingRequiredFields"
            :key="field.key"
            class="flex items-center gap-2.5 rounded-md bg-amber-500/6 px-3 py-2"
          >
            <span class="text-xs text-amber-500/60">+</span>
            <span class="text-xs font-medium text-amber-400/90">{{ field.label }}</span>
            <span v-if="field.required" class="ml-auto text-[10px] text-amber-500/40">required</span>
          </div>
        </div>
      </div>

      <!-- Missing: optional fields -->
      <div v-if="missingOptionalFields.length" class="border-t border-border-subtle px-4 py-3">
        <div class="flex flex-col gap-1.5">
          <div
            v-for="field in missingOptionalFields"
            :key="field.key"
            class="flex items-center gap-2 text-xs text-muted/50"
          >
            <span class="text-[10px] text-muted/30">·</span>
            <span>{{ field.label }}</span>
          </div>
        </div>
      </div>

      <!-- Proposed changes -->
      <div v-if="changesList.length" class="border-t border-border-subtle px-4 py-3.5">
        <p class="mb-3 text-[11px] font-medium uppercase tracking-wider text-muted/60">When you confirm</p>
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
          </div>
        </div>
      </div>
    </div>

    <!-- Refinement hints -->
    <ProposalRefinementHints v-if="proposal" :proposal="proposal" class="mt-3" />

    <!-- Warnings -->
    <div
      v-if="warningsList.length"
      class="mx-4 mt-3 rounded-lg border border-amber-500/20 bg-amber-500/6 px-3.5 py-3"
    >
      <ul class="flex flex-col gap-1">
        <li
          v-for="(warning, i) in warningsList"
          :key="i"
          class="flex items-start gap-2 text-xs text-amber-400/80"
        >
          <span class="mt-0.5 shrink-0 text-amber-500/60">⚠</span>
          <span class="leading-relaxed">{{ warning }}</span>
        </li>
      </ul>
    </div>

    <!-- Last refinement -->
    <div v-if="proposal?.last_refinement" class="mt-3">
      <ProposalLastRefinementPanel :refinement="proposal.last_refinement" />
    </div>

    <!-- Execution result -->
    <ProposalExecutionResultPanel
      v-if="isExecuted && proposal?.execution_result"
      :result="proposal.execution_result"
    />

    <!-- Execution failure -->
    <div
      v-if="isFailed && executionFailureMessage"
      class="mx-4 mt-3 rounded-lg border border-red-500/30 bg-red-500/10 px-3.5 py-3"
    >
      <p class="mb-1 text-xs font-semibold text-red-400">Execution Failed</p>
      <p class="text-xs leading-relaxed text-red-400/80">
        {{ executionFailureMessage }}
      </p>
    </div>

    <!-- Technical details -->
    <MobileTechnicalDetailsDrawer :proposal="proposal" />
  </div>
</template>
