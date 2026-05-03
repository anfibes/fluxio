import type { ActionProposal } from '~/types/actions'

export function useActionProposal() {
  const proposal = ref<ActionProposal | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

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
    setProposal,
    setLoading,
    setError,
    clear,
  }
}
