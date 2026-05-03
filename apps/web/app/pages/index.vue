<script setup lang="ts">
import { mockProposalDraft, mockProposalExecuted, mockProposalReady } from '~/mocks/actionProposals'
import type { ActionProposal } from '~/types/actions'

const states = ['ready', 'draft', 'executed'] as const
type DemoState = typeof states[number]

const state = ref<DemoState>('ready')

const proposalMap: Record<DemoState, ActionProposal> = {
  ready: mockProposalReady,
  draft: mockProposalDraft,
  executed: mockProposalExecuted,
}

const currentProposal = computed(() => proposalMap[state.value])
</script>

<template>
  <div class="flex h-full">
    <!-- ── Left column ─────────────────────────────────── -->
    <div class="flex flex-1 flex-col gap-4 overflow-y-auto p-6">
      <!-- Dev state switcher -->
      <div class="flex items-center gap-2">
        <span class="text-xs text-[var(--color-muted)]">Preview:</span>
        <button
          v-for="s in states"
          :key="s"
          type="button"
          class="rounded-md px-3 py-1 text-xs font-medium capitalize transition-colors"
          :class="state === s
            ? 'bg-[var(--color-accent)] text-white'
            : 'border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-muted)] hover:text-[var(--color-text-muted)]'"
          @click="state = s"
        >
          {{ s }}
        </button>
      </div>

      <CommandComposer />
      <CommandLiveParsingFeedback :proposal="currentProposal" />
      <CommandQuickStarters />
      <ContextTabs />
    </div>

    <!-- ── Right rail ─────────────────────────────────── -->
    <div class="flex w-[440px] shrink-0 flex-col overflow-hidden border-l border-[var(--color-border)] bg-[var(--color-surface)]">
      <div class="border-b border-[var(--color-border)] px-4 py-3">
        <p class="text-xs font-medium uppercase tracking-wide text-[var(--color-muted)]">Proposal</p>
      </div>
      <div class="flex-1 overflow-hidden">
        <ProposalActionProposalRail :proposal="currentProposal" />
      </div>
    </div>
  </div>
</template>
