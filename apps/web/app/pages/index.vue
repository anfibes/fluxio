<script setup lang="ts">
import { mockProposalDraft, mockProposalExecuted, mockProposalReady } from '~/mocks/actionProposals'
import type { ActionProposal } from '~/types/actions'

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

onMounted(() => document.addEventListener('keydown', handleGlobalKey))
onUnmounted(() => document.removeEventListener('keydown', handleGlobalKey))

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

function handleReset() {
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
  <div v-else class="flex flex-col overflow-y-auto lg:h-full lg:flex-row lg:overflow-hidden">
    <!-- Workspace column -->
    <div class="flex min-w-0 flex-col gap-5 px-4 py-5 lg:flex-1 lg:overflow-y-auto lg:px-6 lg:py-6">
      <CommandComposer
        ref="composerRef"
        v-model="commandText"
        :loading="loading"
        :placeholder="composerPlaceholder"
        @submit="handleSubmit"
        @clear="handleClear"
      />

      <!-- Canonical phrase summary (read-only narration projection, near the input) -->
      <CommandCanonicalSummary :canonical-phrase="displayProposal?.canonical_phrase ?? null" />

      <!-- Recent command history -->
      <CommandRecentHistory
        :history="history"
        @select="fillFromHistory"
      />

      <!-- Parsing feedback (appears after interpretation) -->
      <CommandLiveParsingFeedback :proposal="displayProposal" />

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

      <CommandQuickStarters />
      <ContextTabs />

      <!-- Dev: mock switcher (collapsible) -->
      <details class="border-t border-border-subtle pt-4">
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

    <!-- Proposal rail: full-width below workspace on mobile, fixed-width sidebar on desktop. -->
    <!-- min-h-[28rem] ensures h-full inside the rail resolves correctly on mobile. -->
    <div class="flex min-h-112 flex-col border-t border-border bg-surface lg:min-h-0 lg:w-110 lg:shrink-0 lg:border-l lg:border-t-0 lg:overflow-hidden">
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
          @reset="handleReset"
        />
      </div>
    </div>
  </div>
</template>
