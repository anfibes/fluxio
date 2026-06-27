import type { ActionProposal } from '~/types/actions'

/**
 * A single user-entered turn in the current proposal session.
 * UI-only, ephemeral state — never the source of truth for proposal state.
 */
export interface ProposalConversationTurn {
  id: string
  role: 'user'
  text: string
}

export function useActionProposal() {
  const api = useApi()

  const proposal = ref<ActionProposal | null>(null)
  const loading = ref(false)
  const confirming = ref(false)
  const error = ref<string | null>(null)

  // ── Conversation trail (UI-only, current session) ──────────────────
  // Records only the commands/refinements the user submitted successfully.
  // Reset when the session resets; never authoritative for proposal state.
  const conversationTrail = ref<ProposalConversationTurn[]>([])
  let turnSeq = 0

  function appendUserTurn(text: string) {
    conversationTrail.value.push({ id: `turn-${Date.now()}-${turnSeq++}`, role: 'user', text })
  }

  async function runProposalAction(id: string, action: 'confirm' | 'execute'): Promise<void> {
    const response = await api.post<ActionProposal>(`/actions/${id}/${action}`, {})
    if (response.success) proposal.value = response.data
    else throw new Error(response.message)
  }

  async function interpret(text: string): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post<ActionProposal>('/actions/interpret', { text })
      if (response.success) {
        proposal.value = response.data
        // interpret() always begins a fresh session — reset, then record turn 1.
        conversationTrail.value = []
        appendUserTurn(text)
      }
      else error.value = response.message
    }
    catch (err: unknown) {
      error.value = err instanceof Error ? err.message : 'Unknown error'
    }
    finally {
      loading.value = false
    }
  }

  async function refine(id: string, text: string): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post<ActionProposal>(`/actions/${id}/refine`, { text })
      if (response.success) {
        proposal.value = response.data
        appendUserTurn(text)
      }
      else error.value = response.message
    }
    catch (err: unknown) {
      error.value = err instanceof Error ? err.message : 'Unknown error'
    }
    finally {
      loading.value = false
    }
  }

  async function resolveAmbiguity(id: string, refinementText: string): Promise<void> {
    await refine(id, refinementText)
  }

  async function submitCommand(text: string): Promise<void> {
    const current = proposal.value
    if (!current || current.status === 'confirmed' || current.status === 'executed' || current.status === 'failed') {
      await interpret(text)
    }
    else {
      await refine(current.id, text)
    }
  }

  async function confirmAndExecute(): Promise<void> {
    if (!proposal.value || proposal.value.status !== 'ready') return
    const id = proposal.value.id
    loading.value = true
    confirming.value = true
    error.value = null
    try {
      await runProposalAction(id, 'confirm')
      await runProposalAction(id, 'execute')
    }
    catch (err: unknown) {
      error.value = err instanceof Error ? err.message : 'Unknown error'
    }
    finally {
      loading.value = false
      confirming.value = false
    }
  }

  function setProposal(p: ActionProposal | null) { proposal.value = p }
  function setLoading(value: boolean) { loading.value = value }
  function setError(message: string | null) { error.value = message }
  function clear() { proposal.value = null; error.value = null; loading.value = false; confirming.value = false; conversationTrail.value = [] }

  return {
    proposal: readonly(proposal),
    loading: readonly(loading),
    confirming: readonly(confirming),
    error: readonly(error),
    conversationTrail: readonly(conversationTrail),
    interpret,
    refine,
    resolveAmbiguity,
    submitCommand,
    confirmAndExecute,
    setProposal,
    setLoading,
    setError,
    clear,
  }
}
