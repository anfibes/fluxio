<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'
import ProposalAmbiguityPanel from '../AmbiguityPanel.vue'
import ProposalMissingInformationPanel from '../MissingInformationPanel.vue'
import ProposalRefinementHints from '../ProposalRefinementHints.vue'
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

// ── Card data ──────────────────────────────────────────────────────

const { t } = useI18n()

const displayPhrase = computed(() => {
  const phrase = props.proposal?.canonical_phrase?.trim()
  return phrase && phrase.length > 0 ? phrase : null
})

const cardHeadline = computed(() => {
  if (displayPhrase.value) return displayPhrase.value
  const p = props.proposal
  if (!p) return null
  // Only actionable proposals reach this perspective (unknown intent renders
  // its own card upstream), so the raw intent name is a safe fallback.
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

const warningsList = computed(() => props.proposal?.warnings ?? [])

// ── Visual memory (field-level) ────────────────────────────────────
// Keys of the fields touched by the most recent refinement, derived purely
// from `last_refinement.changes[].field` (replaced wholesale by the backend
// on the next refinement turn — no local timing state). Only shown while the
// proposal is still being shaped: once confirmed/executed/failed the moment
// has passed and nothing may compete with the CTA / execution surfaces.
const recentlyChangedFieldKeys = computed<ReadonlySet<string>>(() => {
  const p = props.proposal
  if (!p || (p.status !== 'draft' && p.status !== 'ready')) return new Set()
  return new Set((p.last_refinement?.changes ?? []).map(c => c.field))
})

function isRecentlyChanged(fieldKey: string): boolean {
  return recentlyChangedFieldKeys.value.has(fieldKey)
}

const executionFailureMessage = computed(() =>
  props.proposal?.execution_failure?.message ?? props.proposal?.failure_reason ?? '',
)

// ── Narrative layer (human-readable proposal state) ────────────────

interface NarrativeCue {
  eyebrow: string
  message: string
  variant: 'info' | 'ready' | 'incomplete' | 'success' | 'error'
}

// The message accompanies the badge only when it carries guidance the badge
// alone can't ("add X", "choose a match", "why it failed"). Self-evident
// states (ready / confirmed / executed) show just the badge — repeating
// "ready to execute" in prose was noise next to the CTA.
function cue(key: string, variant: NarrativeCue['variant'], guidance = false): NarrativeCue {
  return {
    eyebrow: t(`proposal.narrative.${key}.eyebrow`),
    message: guidance ? t(`proposal.narrative.${key}.message`) : '',
    variant,
  }
}

const narrativeCue = computed<NarrativeCue>(() => {
  const p = props.proposal
  if (!p) return { eyebrow: '', message: '', variant: 'info' }

  const status = p.status
  const hasMissing = missingRequiredFields.value.length > 0
  const hasAmbiguities = blockingAmbiguities.value.length > 0

  if (status === 'executed') return cue('executed', 'success')
  if (status === 'failed')   return cue('failed', 'error', true)
  if (status === 'ready')    return cue('ready', 'ready')
  if (status === 'confirmed') return cue('confirmed', 'info')
  if (status === 'draft') {
    if (hasMissing)      return cue('needs_details', 'incomplete', true)
    if (hasAmbiguities)  return cue('needs_clarification', 'incomplete', true)
    return cue('understood', 'info', true)
  }
  return { eyebrow: '', message: '', variant: 'info' }
})
</script>

<template>
  <div>
    <!-- Blocking items -->
    <Transition name="section">
      <ProposalAmbiguityPanel
        v-if="blockingAmbiguities.length"
        :ambiguities="blockingAmbiguities"
        :loading="loading"
        @resolve="emit('resolve-ambiguity', $event)"
      />
    </Transition>
    <Transition name="section">
      <ProposalMissingInformationPanel
        v-if="requiredMissingFields.length"
        :fields="requiredMissingFields"
      />
    </Transition>

    <!-- Proposal Card -->
    <div
      class="mx-4 mt-3 overflow-hidden rounded-xl border border-border bg-surface"
      :class="{ 'opacity-60': isExecuted }"
    >
      <div class="px-4 pt-5 pb-4">
        <!-- Narrative cue -->
        <div v-if="narrativeCue.eyebrow" class="flex items-start gap-2 mb-2.5">
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
          <span v-if="narrativeCue.message" class="text-[11px] leading-relaxed text-muted">{{ narrativeCue.message }}</span>
        </div>

        <!-- Headline: the visual anchor of the card -->
        <p v-if="cardHeadline" class="text-xl font-semibold leading-snug tracking-tight text-text">
          {{ cardHeadline }}
        </p>
        <p v-if="sourceText" class="mt-1.5 text-xs italic text-muted/70">
          "{{ sourceText }}"
        </p>
      </div>

      <div class="border-t border-border-subtle" />

      <!-- Populated fields -->
      <Transition name="section">
        <div v-if="populatedFields.length" class="px-4 py-3.5">
          <p class="mb-2 text-[10px] font-medium uppercase tracking-wider text-muted/50">{{ $t('proposal.sections.what_found') }}</p>
          <!-- Fields discovered by a refinement enter with a subtle motion.
            Initial proposal render is NOT animated (no `appear`) — the card
            itself appearing is sufficient feedback. Removed fields use a short
            fade-out only to avoid transition artifacts during layout updates. -->
          <TransitionGroup tag="div" name="field-row" class="flex flex-col gap-1">
            <div
              v-for="field in populatedFields"
              :key="field.key"
              class="-mx-2 flex items-baseline justify-between gap-3 rounded-md px-2 py-0.5 transition-colors duration-700"
              :class="{ 'bg-accent/8': isRecentlyChanged(field.key) }"
            >
              <span class="min-w-0 text-[11px] text-muted/70">{{ field.label }}</span>
              <span class="shrink-0 text-right text-sm font-medium text-text">
                {{ formatFieldValue(field.value) }}
              </span>
            </div>
          </TransitionGroup>
        </div>
      </Transition>

      <!-- Missing: required fields -->
      <Transition name="section">
        <div v-if="missingRequiredFields.length" class="border-t border-border-subtle px-4 py-3.5">
          <p class="mb-2 text-[10px] font-medium uppercase tracking-wider text-muted/50">{{ $t('proposal.sections.still_needed') }}</p>
          <div class="flex flex-col gap-2">
            <div
              v-for="field in missingRequiredFields"
              :key="field.key"
              class="flex items-center gap-2.5 rounded-md bg-amber-500/6 px-3 py-2"
            >
              <span class="text-xs text-amber-500/60">+</span>
              <span class="text-xs font-medium text-amber-400/90">{{ field.label }}</span>
              <span v-if="field.required" class="ml-auto text-[10px] text-amber-500/40">{{ $t('proposal.field_required') }}</span>
            </div>
          </div>
        </div>
      </Transition>

      <!-- Missing: optional fields -->
      <Transition name="section">
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
      </Transition>

    </div>

    <!-- Refinement hints -->
    <ProposalRefinementHints v-if="proposal" :proposal="proposal" class="mt-3" />

    <!-- Warnings -->
    <Transition name="section">
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
    </Transition>

    <!-- Execution failure -->
    <Transition name="section">
      <div
        v-if="isFailed && executionFailureMessage"
        class="mx-4 mt-3 rounded-lg border border-red-500/30 bg-red-500/10 px-3.5 py-3"
      >
        <p class="mb-1 text-xs font-semibold text-red-400">{{ $t('proposal.failure.title') }}</p>
        <p class="text-xs leading-relaxed text-red-400/80">
          {{ executionFailureMessage }}
        </p>
      </div>
    </Transition>

    <!-- Technical details -->
    <MobileTechnicalDetailsDrawer :proposal="proposal" />
  </div>
</template>

<style scoped>
/* Newly discovered field rows enter calmly; siblings reflow smoothly. */
.field-row-enter-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.field-row-enter-from {
  opacity: 0;
  transform: translateY(4px);
}

.field-row-move {
  transition: transform 0.25s ease;
}

/* Cleared fields leave with a quick fade. Without this, the row's own
   700ms color transition would make Vue hold the removed element frozen
   on screen while waiting for a transitionend that never fires. */
.field-row-leave-active {
  transition: opacity 0.15s ease;
}

.field-row-leave-to {
  opacity: 0;
}

/* Section-level appearance: a section that becomes relevant inside an
   already-visible proposal fades in gently. No `appear` anywhere, so the
   initial proposal render (and tab switches, which remount this component)
   stays calm. Disappearing sections use the same quick fade as field rows. */
.section-enter-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.section-enter-from {
  opacity: 0;
  transform: translateY(4px);
}

.section-leave-active {
  transition: opacity 0.15s ease;
}

.section-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .field-row-enter-active,
  .field-row-move,
  .field-row-leave-active,
  .section-enter-active,
  .section-leave-active {
    transition: none;
  }
}
</style>
