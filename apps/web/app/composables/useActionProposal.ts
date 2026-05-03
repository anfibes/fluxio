import type { ActionProposal } from '~/types/actions'

export function useActionProposal() {
  const api = useApi()

  const proposal = ref<ActionProposal | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function interpret(text: string): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post<ActionProposal>('/actions/interpret', { text })
      if (response.success) {
        proposal.value = response.data
      }
      else {
        error.value = response.message
      }
    }
    catch (err: unknown) {
      error.value = err instanceof Error ? err.message : 'Unknown error'
    }
    finally {
      loading.value = false
    }
  }

  function setProposal(p: ActionProposal | null) {
    proposal.value = p
  }

  function setLoading(value: boolean) {
    loading.value = value
  }

  function setError(message: string | null) {
    error.value = message
  }

  function clear() {
    proposal.value = null
    error.value = null
    loading.value = false
  }

  return {
    proposal: readonly(proposal),
    loading: readonly(loading),
    error: readonly(error),
    interpret,
    setProposal,
    setLoading,
    setError,
    clear,
  }
}
