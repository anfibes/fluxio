const token = ref<string | null>(null)

export function useAuth() {
  function setToken(t: string) {
    token.value = t
  }

  function clearToken() {
    token.value = null
  }

  const isAuthenticated = computed(() => token.value !== null)

  return {
    token: readonly(token),
    isAuthenticated,
    setToken,
    clearToken,
  }
}
