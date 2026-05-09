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
const { proposal, loading, error, interpret, confirmAndExecute } = useActionProposal()

// real proposal wins; fall back to selected mock; null when no mock chosen
// cast needed: mock objects are deeply readonly, not assignable to mutable ActionProposal
const displayProposal = computed<ActionProposal | null>(
  () => (proposal.value ?? (mockState.value ? mockProposalMap[mockState.value] : null)) as ActionProposal | null,
)

async function handleSubmit() {
  await interpret(commandText.value)
}
</script>

<template>
  <!-- ── Auth gate ────────────────────────────────────────── -->
  <div v-if="!isAuthenticated" class="flex h-full items-center justify-center bg-[var(--color-bg)]">
    <AuthLoginPanel />
  </div>

  <!-- ── Main UI ───────────────────────────────────────────── -->
  <div v-else class="flex h-full">
    <!-- Left column -->
    <div class="flex flex-1 flex-col gap-5 overflow-y-auto px-6 py-6">
      <CommandComposer
        v-model="commandText"
        :loading="loading"
        @submit="handleSubmit"
      />

      <!-- Parsing feedback (appears after interpretation) -->
      <CommandLiveParsingFeedback :proposal="displayProposal" />

      <!-- API error banner -->
      <div
        v-if="error"
        class="flex items-start gap-2.5 rounded-xl border border-red-500/25 bg-red-500/8 px-3.5 py-3 text-xs text-red-400"
      >
        <span class="mt-0.5 shrink-0 text-base leading-none">⚠</span>
        <span class="leading-relaxed">{{ error }}</span>
      </div>

      <CommandQuickStarters />
      <ContextTabs />

      <!-- Dev: mock switcher -->
      <div class="flex items-center gap-2 border-t border-[var(--color-border-subtle)] pt-4">
        <span class="text-xs text-[var(--color-muted)]">Dev:</span>
        <button
          type="button"
          class="rounded px-2 py-1 text-xs font-medium transition-colors"
          :class="mockState === null
            ? 'bg-[var(--color-accent)] text-white'
            : 'border border-[var(--color-border)] text-[var(--color-muted)] hover:text-[var(--color-text-muted)]'"
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
            ? 'bg-[var(--color-accent)] text-white'
            : 'border border-[var(--color-border)] text-[var(--color-muted)] hover:text-[var(--color-text-muted)]'"
          @click="mockState = s"
        >
          {{ s }}
        </button>
      </div>
    </div>

    <!-- Right rail -->
    <div class="flex w-[440px] shrink-0 flex-col overflow-hidden border-l border-[var(--color-border)] bg-[var(--color-surface)]">
      <div class="border-b border-[var(--color-border)] px-4 py-3">
        <p class="text-xs font-medium uppercase tracking-wide text-[var(--color-muted)]">
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
