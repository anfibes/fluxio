<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'

const props = defineProps<{
  proposal: ActionProposal | null
  loading?: boolean
  confirming?: boolean
}>()

const emit = defineEmits<{
  'confirm-execute': []
  'resolve-ambiguity': [text: string]
  'reset': []
}>()

// ── Status derivations (from props only, no local state) ──────────────

const isDraft    = computed(() => props.proposal?.status === 'draft')
const isReady    = computed(() => props.proposal?.status === 'ready')
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

// ── CTA state (derived, no local status) ──────────────────────────────

interface CtaState {
  visible: boolean
  label: string
  disabled: boolean
  variant: 'primary' | 'subtle' | 'error' | 'loading'
}

const ctaState = computed<CtaState>(() => {
  if (!props.proposal) {
    return { visible: false, label: '', disabled: true, variant: 'subtle' }
  }
  if (props.confirming) {
    return { visible: true, label: 'Confirming…', disabled: true, variant: 'loading' }
  }
  switch (props.proposal.status) {
    case 'draft':
      return { visible: true, label: 'Complete Proposal', disabled: true, variant: 'subtle' }
    case 'ready':
      return { visible: true, label: 'Confirm & Execute', disabled: false, variant: 'primary' }
    case 'confirmed':
    case 'executed':
      return { visible: false, label: '', disabled: true, variant: 'subtle' }
    case 'failed':
      return { visible: true, label: 'Execution Failed', disabled: true, variant: 'error' }
    default:
      return { visible: false, label: '', disabled: true, variant: 'subtle' }
  }
})

// ── Developer Drawer data ─────────────────────────────────────────────

const devDrawerSections = computed(() => {
  const p = props.proposal
  if (!p) return []
  return [
    { label: 'Status',     value: p.status },
    { label: 'Intent',     value: p.intent ? p.intent.replace(/_/g, ' ') : '—' },
    { label: 'Proposal ID', value: p.id },
    { label: 'Confidence', value: typeof p.confidence === 'number' ? `${Math.round(p.confidence * 100)}%` : '—' },
  ]
})

// ── Canonical phrase (trimmed, fallback) ──────────────────────────────

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

// ── Fail-safe accessors (resilient to backend shape evolution) ──

const editableFields = computed(() => props.proposal?.editable_fields ?? [])
const changesList = computed(() => props.proposal?.changes ?? [])
const warningsList = computed(() => props.proposal?.warnings ?? [])
const sourceText = computed(() => props.proposal?.source_text ?? '')

const populatedFields = computed(() =>
  editableFields.value.filter(f => f.value != null && f.value !== ''),
)

const missingFields = computed(() =>
  editableFields.value.filter(f => f.value == null || f.value === ''),
)

// ── Value formatter (safe render for any field type) ──────────────

function formatFieldValue(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (Array.isArray(value)) return value.length ? value.map(item => formatFieldValue(item)).join(', ') : '—'
  if (typeof value === 'object') {
    try { return JSON.stringify(value) } catch { return '—' }
  }
  return String(value)
}

// ── Field source badge (subtle provenance indicator) ──────────────

const sourceLabel: Record<string, string> = {
  detected: 'detected',
  inferred:  'inferred',
  guessed:   'guessed',
  computed:  'computed',
  derived:   'derived',
  edited:    'edited',
  missing:   'missing',
}

function sourceBadge(source: string): string {
  return sourceLabel[source] ?? 'detected'
}
</script>

<template>
  <div class="mob-exp flex h-full flex-col bg-bg">

    <!-- ═══════════════ EMPTY STATE ═══════════════ -->
    <div v-if="!proposal" class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
      <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-accent/10 text-2xl">
        ⚡
      </div>
      <div class="flex flex-col gap-1.5">
        <p class="text-base font-semibold text-text">What would you like to do?</p>
        <p class="max-w-64 text-sm leading-relaxed text-muted">
          Type a command above to propose an action. Fluxio will interpret it and show you a proposal to review.
        </p>
      </div>
    </div>

    <!-- ═══════════════ PROPOSAL CONTENT ═══════════════ -->
    <template v-else>
      <!-- Scrollable body -->
      <div class="flex-1 overflow-y-auto pb-24">

        <!-- ── Status banner ────────────────────────── -->
        <ProposalStatusBanner :proposal="proposal" />

        <!-- ── Blocking items (unresolved) ──────────── -->
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

        <!-- ── Executed: completion header ──────────── -->
        <div
          v-if="isExecuted"
          class="mx-4 mt-4 flex flex-col items-center gap-2 rounded-xl bg-emerald-500/6 px-4 py-6 text-center"
        >
          <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/15 text-lg text-emerald-400">
            ✓
          </div>
          <p class="text-sm font-semibold text-emerald-400">Action complete</p>
          <p v-if="displayPhrase" class="text-xs leading-relaxed text-emerald-400/70">
            {{ displayPhrase }}
          </p>
        </div>

        <!-- ── Proposal Card ─────────────────────────── -->
        <div
          class="mx-4 mt-3 overflow-hidden rounded-xl border border-border bg-surface"
          :class="{ 'opacity-60': isExecuted }"
        >
          <!-- Card hero: headline + source text -->
          <div class="px-4 pt-4 pb-3">
            <p
              v-if="cardHeadline"
              class="text-base font-semibold leading-snug text-text"
            >
              {{ cardHeadline }}
            </p>
            <p v-if="sourceText" class="mt-1 text-xs italic text-muted">
              "{{ sourceText }}"
            </p>
          </div>

          <div class="border-t border-border-subtle" />

          <!-- Populated fields -->
          <div v-if="populatedFields.length" class="px-4 py-3">
            <p class="mb-2.5 text-[10px] font-medium uppercase tracking-widest text-muted/50">
              Details
            </p>
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

          <!-- Missing fields -->
          <div v-if="missingFields.length" class="border-t border-border-subtle px-4 py-3">
            <p class="mb-2.5 text-[10px] font-medium uppercase tracking-widest text-muted/50">
              Missing
            </p>
            <div class="flex flex-col gap-1.5">
              <div
                v-for="field in missingFields"
                :key="field.key"
                class="flex items-center gap-2 text-xs text-muted italic"
              >
                <span class="text-[10px] text-muted/40">·</span>
                <span>{{ field.label }}</span>
              </div>
            </div>
          </div>

          <!-- Proposed changes -->
          <div v-if="changesList.length" class="border-t border-border-subtle px-4 py-3">
            <p class="mb-2.5 text-[10px] font-medium uppercase tracking-widest text-muted/50">
              Will
            </p>
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

        <!-- ── Refinement hints ──────────────────────── -->
        <ProposalRefinementHints
          v-if="proposal"
          :proposal="proposal"
          class="mt-3"
        />

        <!-- ── Warnings ──────────────────────────────── -->
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

        <!-- ── Last refinement ───────────────────────── -->
        <div v-if="proposal.last_refinement" class="mt-3">
          <ProposalLastRefinementPanel :refinement="proposal.last_refinement" />
        </div>

        <!-- ── Execution result ──────────────────────── -->
        <ProposalExecutionResultPanel
          v-if="isExecuted && proposal.execution_result"
          :result="proposal.execution_result"
        />

        <!-- ── Execution failure ─────────────────────── -->
        <div
          v-if="isFailed && (proposal.execution_failure || proposal.failure_reason)"
          class="mx-4 mt-3 rounded-lg border border-red-500/30 bg-red-500/10 px-3.5 py-3"
        >
          <p class="mb-1 text-xs font-semibold text-red-400">Execution Failed</p>
          <p class="text-xs leading-relaxed text-red-400/80">
            {{ proposal.execution_failure?.message ?? proposal.failure_reason }}
          </p>
        </div>

        <!-- ── Technical details drawer ───────────────── -->
        <details class="dev-drawer mx-4 mt-3 mb-4">
          <summary class="dev-drawer-summary">
            <span>Technical details</span>
            <span class="dev-drawer-chevron">▸</span>
          </summary>
          <div class="dev-drawer-body">
            <p class="mb-2 text-[10px] leading-relaxed text-muted/50">
              Metadata about this proposal. Not part of the primary workflow.
            </p>
            <dl class="flex flex-col gap-2">
              <div
                v-for="row in devDrawerSections"
                :key="row.label"
                class="flex items-center justify-between"
              >
                <dt class="text-xs text-muted">{{ row.label }}</dt>
                <dd class="text-xs font-medium text-text-muted">{{ row.value }}</dd>
              </div>
            </dl>
          </div>
        </details>

      </div>

      <!-- ═══════════════ STICKY CTA ═══════════════ -->
      <div
        v-if="ctaState.visible"
        class="mob-cta shrink-0 border-t border-border bg-surface px-4 py-3"
      >
        <button
          type="button"
          class="mob-cta-btn"
          :class="{
            'mob-cta-btn--primary': ctaState.variant === 'primary',
            'mob-cta-btn--subtle': ctaState.variant === 'subtle',
            'mob-cta-btn--error': ctaState.variant === 'error',
            'mob-cta-btn--loading': ctaState.variant === 'loading',
          }"
          :disabled="ctaState.disabled"
          @click="ctaState.variant === 'primary' && emit('confirm-execute')"
        >
          <span
            v-if="ctaState.variant === 'loading'"
            class="mob-cta-dot"
          />
          {{ ctaState.label }}
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
/* ── Component root ─────────────────────────────────── */
.mob-exp {
  width: 100%;
}

/* ── Developer Drawer ───────────────────────────────── */
.dev-drawer {
  border-radius: 0.75rem;
  border: 1px solid var(--color-border);
  background-color: var(--color-surface);
  overflow: hidden;
}

.dev-drawer[open] {
  background-color: var(--color-surface-raised);
}

.dev-drawer-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.625rem 0.875rem;
  font-size: 0.6875rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-muted);
  cursor: pointer;
  user-select: none;
  list-style: none;
}

.dev-drawer-summary::-webkit-details-marker {
  display: none;
}

.dev-drawer-chevron {
  font-size: 0.625rem;
  transition: transform 0.15s ease;
}

.dev-drawer[open] .dev-drawer-chevron {
  transform: rotate(90deg);
}

.dev-drawer-body {
  padding: 0.5rem 0.875rem 0.75rem;
  border-top: 1px solid var(--color-border-subtle);
}

/* ── Sticky CTA ─────────────────────────────────────── */
.mob-cta {
  position: sticky;
  bottom: 0;
  z-index: 10;
}

.mob-cta-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  font-size: 0.9375rem;
  font-weight: 600;
  transition: background-color 0.15s ease, opacity 0.15s ease;
}

.mob-cta-btn:disabled {
  cursor: not-allowed;
}

.mob-cta-btn--primary {
  background-color: var(--color-accent);
  color: white;
}

.mob-cta-btn--primary:not(:disabled):active {
  background-color: var(--color-accent-hover);
}

.mob-cta-btn--subtle {
  background-color: var(--color-surface-raised);
  color: var(--color-muted);
  opacity: 0.8;
}

.mob-cta-btn--error {
  background-color: rgb(239 68 68 / 0.12);
  color: rgb(239 68 68 / 0.8);
  border: 1px solid rgb(239 68 68 / 0.25);
}

.mob-cta-btn--loading {
  background-color: var(--color-accent);
  color: white;
  opacity: 0.8;
}

.mob-cta-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  background-color: currentColor;
  opacity: 0.7;
  animation: mob-pulse 1s ease-in-out infinite;
}

@keyframes mob-pulse {
  0%, 100% { opacity: 0.7; }
  50%      { opacity: 0.2; }
}
</style>
