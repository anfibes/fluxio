<script setup lang="ts">
import type { ActionProposal } from '~/types/actions'
import ProposalStatusBanner from '../ProposalStatusBanner.vue'
import MobileProposalPerspective from './MobileProposalPerspective.vue'
import MobileContextPerspective from './MobileContextPerspective.vue'
import MobileHistoryPerspective from './MobileHistoryPerspective.vue'
import MobileStickyProposalCta from './MobileStickyProposalCta.vue'

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

// ── Perspective (local UI state only) ──────────────────────────────

type Perspective = 'proposal' | 'context' | 'history'
const activeTab = ref<Perspective>('proposal')

// ── Status derivations (shared across tabs) ────────────────────────

const isDraft    = computed(() => props.proposal?.status === 'draft')
const isReady    = computed(() => props.proposal?.status === 'ready')
const isExecuted = computed(() => props.proposal?.status === 'executed')

const displayPhrase = computed(() => {
  const phrase = props.proposal?.canonical_phrase?.trim()
  return phrase && phrase.length > 0 ? phrase : null
})
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
      <!-- Status banner (always visible) -->
      <ProposalStatusBanner :proposal="proposal" />

      <!-- Executed: completion header (always visible) -->
      <div
        v-if="isExecuted"
        class="mx-4 mt-3 flex flex-col items-center gap-2 rounded-xl bg-emerald-500/6 px-4 py-6 text-center"
      >
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/15 text-lg text-emerald-400">
          ✓
        </div>
        <p class="text-sm font-semibold text-emerald-400">Action complete</p>
        <p v-if="displayPhrase" class="text-xs leading-relaxed text-emerald-400/70">
          {{ displayPhrase }}
        </p>
      </div>

      <!-- ── Segmented control ──────────────────────── -->
      <div class="mob-segmented mx-4 mt-3">
        <button
          v-for="tab in (['proposal', 'context', 'history'] as Perspective[])"
          :key="tab"
          type="button"
          class="mob-segmented-btn"
          :class="{ 'mob-segmented-btn--active': activeTab === tab }"
          @click="activeTab = tab"
        >
          {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
        </button>
      </div>

      <!-- Scrollable body -->
      <div class="flex-1 overflow-y-auto pb-24">

        <MobileProposalPerspective
          v-if="activeTab === 'proposal'"
          :proposal="proposal"
          :loading="loading"
          @resolve-ambiguity="emit('resolve-ambiguity', $event)"
        />

        <MobileContextPerspective
          v-if="activeTab === 'context'"
          :proposal="proposal"
        />

        <MobileHistoryPerspective
          v-if="activeTab === 'history'"
          :proposal="proposal"
        />

      </div>

      <!-- ═══════════════ STICKY CTA ═══════════════ -->
      <MobileStickyProposalCta
        :status="proposal.status"
        :confirming="confirming"
        @confirm-execute="emit('confirm-execute')"
      />
    </template>
  </div>
</template>

<style scoped>
.mob-exp {
  width: 100%;
}

/* ── Segmented control ──────────────────────────────── */
.mob-segmented {
  display: flex;
  border-radius: 0.75rem;
  background-color: var(--color-surface-raised);
  padding: 0.25rem;
  gap: 0.125rem;
}

.mob-segmented-btn {
  flex: 1;
  padding: 0.625rem 0.5rem;
  border-radius: 0.625rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-muted);
  text-align: center;
  transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
  cursor: pointer;
  background: none;
  border: none;
  letter-spacing: -0.01em;
}

.mob-segmented-btn--active {
  background-color: var(--color-surface);
  color: var(--color-text);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04);
}
</style>
