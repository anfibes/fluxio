<script setup lang="ts">
import { mockProposalDraft, mockProposalExecuted, mockProposalReady } from '~/mocks/actionProposals'
import type { ActionProposal } from '~/types/actions'

// ── dev mock switcher ────────────────────────────────────────
const states = ['ready', 'draft', 'executed'] as const
type DemoState = typeof states[number]

const mockState = ref<DemoState>('ready')

const mockProposalMap: Record<DemoState, ActionProposal> = {
  ready: mockProposalReady,
  draft: mockProposalDraft,
  executed: mockProposalExecuted,
}

// ── auth ─────────────────────────────────────────────────────
const { isAuthenticated } = useAuth()

// ── live state ───────────────────────────────────────────────
const commandText = ref('')
const { proposal, loading, error, interpret } = useActionProposal()

// real proposal wins; fall back to selected mock while no API response exists
// cast needed: readonly(proposal).value is Readonly<ActionProposal>, mocks are ActionProposal
const displayProposal = computed<ActionProposal>(
  () => (proposal.value ?? mockProposalMap[mockState.value]) as ActionProposal,
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
    <div class="flex flex-1 flex-col gap-4 overflow-y-auto p-6">
      <!-- Dev mock switcher -->
      <div class="flex items-center gap-2">
        <span class="text-xs text-[var(--color-muted)]">Mock:</span>
        <button
          v-for="s in states"
          :key="s"
          type="button"
          class="rounded-md px-3 py-1 text-xs font-medium capitalize transition-colors"
          :class="mockState === s
            ? 'bg-[var(--color-accent)] text-white'
            : 'border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-muted)] hover:text-[var(--color-text-muted)]'"
          @click="mockState = s"
        >
          {{ s }}
        </button>
      </div>

      <!-- API error banner -->
      <div
        v-if="error"
        class="flex items-start gap-2 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2.5 text-xs text-red-400"
      >
        <span class="mt-0.5 shrink-0">⚠</span>
        <span>{{ error }}</span>
      </div>

      <CommandComposer
        v-model="commandText"
        :loading="loading"
        @submit="handleSubmit"
      />
      <CommandLiveParsingFeedback :proposal="displayProposal" />
      <CommandQuickStarters />
      <ContextTabs />
    </div>

    <!-- Right rail -->
    <div class="flex w-[440px] shrink-0 flex-col overflow-hidden border-l border-[var(--color-border)] bg-[var(--color-surface)]">
      <div class="border-b border-[var(--color-border)] px-4 py-3">
        <p class="text-xs font-medium uppercase tracking-wide text-[var(--color-muted)]">
          {{ $t('proposal.title') }}
        </p>
      </div>
      <div class="flex-1 overflow-hidden">
        <ProposalActionProposalRail :proposal="displayProposal" />
      </div>
    </div>
  </div>
</template>
