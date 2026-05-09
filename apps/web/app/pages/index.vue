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
const { proposal, loading, error, interpret, confirmAndExecute, setError } = useActionProposal()
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

// ── command submit ───────────────────────────────────────────
async function handleSubmit() {
  pushHistory(commandText.value)
  await interpret(commandText.value)
}

function handleClear() {
  commandText.value = ''
  setError(null)
}

function fillFromHistory(cmd: string) {
  commandText.value = cmd
  composerRef.value?.focus()
}
</script>

<template>
  <!-- ── Auth gate ────────────────────────────────────────── -->
  <div v-if="!isAuthenticated" class="flex h-full items-center justify-center bg-bg">
    <AuthLoginPanel />
  </div>

  <!-- ── Main UI ───────────────────────────────────────────── -->
  <div v-else class="flex h-full">
    <!-- Left column -->
    <div class="flex flex-1 flex-col gap-5 overflow-y-auto px-6 py-6">
      <CommandComposer
        ref="composerRef"
        v-model="commandText"
        :loading="loading"
        @submit="handleSubmit"
        @clear="handleClear"
      />

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

    <!-- Right rail -->
    <div class="flex w-[440px] shrink-0 flex-col overflow-hidden border-l border-border bg-surface">
      <div class="border-b border-border px-4 py-3">
        <p class="text-xs font-medium uppercase tracking-wide text-muted">
          {{ $t('proposal.title') }}
        </p>
      </div>
      <div class="flex-1 overflow-hidden">
        <ProposalActionProposalRail
          :proposal="displayProposal"
          :loading="loading"
          @confirm-execute="confirmAndExecute"
        />
      </div>
    </div>
  </div>
</template>
