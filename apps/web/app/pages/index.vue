<script setup lang="ts">
import { mockProposalDraft, mockProposalExecuted, mockProposalReady } from '~/mocks/actionProposals'
import type { ActionProposal } from '~/types/actions'
import MobileActionProposalExperience from '~/components/proposal/mobile/MobileActionProposalExperience.vue'

// ── dev mock switcher ────────────────────────────────────────
const mockState = ref<'ready' | 'draft' | 'executed' | null>(null)

const mockProposalMap = {
  ready: mockProposalReady,
  draft: mockProposalDraft,
  executed: mockProposalExecuted,
} satisfies Record<string, ActionProposal>

// ── auth ─────────────────────────────────────────────────────
const { isAuthenticated } = useAuth()

// ── live state ───────────────────────────────────────────────
const commandText = ref('')
const { proposal, loading, confirming, error, submitCommand, confirmAndExecute, resolveAmbiguity, setError, clear } = useActionProposal()
const { history, push: pushHistory } = useCommandHistory()

// ── composer keyboard shortcut ───────────────────────────────
const composerRef = ref<{ focus: () => void } | null>(null)

function handleGlobalKey(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    e.preventDefault()
    composerRef.value?.focus()
  }
}

// ── Viewport breakpoint (1024px = Tailwind lg) ──────────────
const isDesktop = ref(true)

function updateBreakpoint() {
  if (typeof window !== 'undefined') {
    isDesktop.value = window.innerWidth >= 1024
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleGlobalKey)
  updateBreakpoint()
  window.addEventListener('resize', updateBreakpoint)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleGlobalKey)
  window.removeEventListener('resize', updateBreakpoint)
})

// ── proposal display ─────────────────────────────────────────
// cast needed: mock objects are deeply readonly, not assignable to mutable ActionProposal
const displayProposal = computed<ActionProposal | null>(
  () => (proposal.value ?? (mockState.value ? mockProposalMap[mockState.value] : null)) as ActionProposal | null,
)

// ── contextual placeholder ───────────────────────────────────
const { t } = useI18n()

const composerPlaceholder = computed<string | undefined>(() => {
  const p = displayProposal.value
  if (!p) return undefined
  // Unknown intent is not refinable — the next command starts over, so keep
  // the default "describe what you want to do" placeholder.
  if (p.intent === 'unknown') return undefined
  const blockingAmbiguities = p.ambiguities?.filter(a => a.blocking) ?? []
  if (blockingAmbiguities.length) return t('command.placeholder_resolve_ambiguity')
  const requiredMissing = p.missing?.filter(f => f.required) ?? []
  if (requiredMissing.length) return t('command.placeholder_add_missing')
  if (p.status === 'draft' || p.status === 'ready') return t('command.placeholder_refine')
  return undefined
})

// ── command submit ───────────────────────────────────────────
async function handleSubmit() {
  const text = commandText.value
  pushHistory(text)
  await submitCommand(text)
  if (!error.value) {
    commandText.value = ''
    await nextTick()
    composerRef.value?.focus()
  }
}

function handleClear() {
  commandText.value = ''
  setError(null)
}

function fillFromHistory(cmd: string) {
  commandText.value = cmd
  composerRef.value?.focus()
}

async function handleResolveAmbiguity(text: string) {
  if (!proposal.value) return
  pushHistory(text)
  await resolveAmbiguity(proposal.value.id, text)
  if (!error.value) {
    commandText.value = ''
    await nextTick()
    composerRef.value?.focus()
  }
}

// "New Proposal": abandon the current proposal and return to the empty
// state. Pure client-side clear — no backend call.
function handleNewProposal() {
  clear()
  commandText.value = ''
  nextTick(() => composerRef.value?.focus())
}
</script>

<template>
  <!-- ── Auth gate ────────────────────────────────────────── -->
  <div v-if="!isAuthenticated" class="flex h-full items-center justify-center bg-bg">
    <AuthLoginPanel />
  </div>

  <!-- ── Main UI ───────────────────────────────────────────── -->
  <!-- Mobile: single scrollable column. Desktop: side-by-side with internal scroll. -->
  <div v-else class="flex h-full flex-col overflow-y-auto lg:flex-row lg:overflow-hidden">
    <!-- Workspace column -->
    <div class="flex min-w-0 flex-col gap-3 px-4 py-3 lg:flex-1 lg:gap-5 lg:overflow-y-auto lg:px-6 lg:py-6">
      <CommandComposer
        ref="composerRef"
        v-model="commandText"
        :loading="loading"
        :placeholder="composerPlaceholder"
        :show-new-proposal="displayProposal !== null"
        @submit="handleSubmit"
        @clear="handleClear"
        @new-proposal="handleNewProposal"
      />

      <!-- Canonical phrase summary (desktop only; mobile uses the phrase inside the proposal card) -->
      <CommandCanonicalSummary v-if="isDesktop" :canonical-phrase="displayProposal?.canonical_phrase ?? null" />

      <!-- Recent command history (desktop only) -->
      <CommandRecentHistory
        v-if="isDesktop"
        :history="history"
        @select="fillFromHistory"
      />

      <!-- Parsing feedback (desktop only) -->
      <CommandLiveParsingFeedback v-if="isDesktop" :proposal="displayProposal" />

      <!-- API error banner (dismissible) -->
      <div
        v-if="error"
        class="flex items-start gap-2.5 rounded-xl border border-red-500/25 bg-red-500/8 px-3.5 py-3 text-xs text-red-400"
      >
        <span class="mt-0.5 shrink-0 text-base leading-none">⚠</span>
        <span class="flex-1 leading-relaxed">{{ error }}</span>
        <button
          type="button"
          class="shrink-0 text-red-400/60 transition-colors hover:text-red-400"
          @click="setError(null)"
        >
          ✕
        </button>
      </div>

      <CommandQuickStarters v-if="isDesktop" />
      <ContextTabs v-if="isDesktop" />

      <!-- Dev: mock switcher (desktop only) -->
      <details v-if="isDesktop" class="border-t border-border-subtle pt-4">
        <summary class="cursor-pointer select-none text-xs text-muted hover:text-text-muted">
          Dev preview
        </summary>
        <div class="mt-2 flex items-center gap-2">
          <button
            type="button"
            class="rounded px-2 py-1 text-xs font-medium transition-colors"
            :class="mockState === null
              ? 'bg-accent text-white'
              : 'border border-border text-muted hover:text-text-muted'"
            @click="mockState = null"
          >
            none
          </button>
          <button
            v-for="s in (['ready', 'draft', 'executed'] as const)"
            :key="s"
            type="button"
            class="rounded px-2 py-1 text-xs font-medium capitalize transition-colors"
            :class="mockState === s
              ? 'bg-accent text-white'
              : 'border border-border text-muted hover:text-text-muted'"
            @click="mockState = s"
          >
            {{ s }}
          </button>
        </div>
      </details>
    </div>

    <!-- Desktop proposal rail: fixed-width sidebar (unchanged). -->
    <div v-if="isDesktop" class="flex min-h-112 flex-col border-t border-border bg-surface lg:min-h-0 lg:w-110 lg:shrink-0 lg:border-l lg:border-t-0 lg:overflow-hidden">
      <div class="border-b border-border px-4 py-3">
        <p class="text-xs font-medium uppercase tracking-wide text-muted">
          {{ $t('proposal.title') }}
        </p>
      </div>
      <div class="flex-1 overflow-hidden">
        <ProposalActionProposalRail
          :proposal="displayProposal"
          :loading="loading"
          :confirming="confirming"
          @confirm-execute="confirmAndExecute"
          @resolve-ambiguity="handleResolveAmbiguity"
        />
      </div>
    </div>

    <!-- Mobile proposal experience (new, full-width). -->
    <MobileActionProposalExperience
      v-else
      class="min-h-112 flex-1"
      :proposal="displayProposal"
      :loading="loading"
      :confirming="confirming"
      @confirm-execute="confirmAndExecute"
      @resolve-ambiguity="handleResolveAmbiguity"
    />
  </div>
</template>
