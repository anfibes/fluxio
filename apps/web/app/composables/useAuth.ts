import type { ApiSuccessResponse } from '~/types/api'

export interface AuthUser {
  id: number
  name: string
  email: string
}

interface LoginData {
  user: AuthUser
  token: string
}

export function useAuth() {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

  // token persists across reloads via cookie; user is restored on next login or /me call
  const token = useCookie<string | null>('fluxio_token', {
    default: () => null,
    maxAge: 60 * 60 * 24 * 7,
  })
  const user = useState<AuthUser | null>('auth_user', () => null)

  const isAuthenticated = computed(() => token.value !== null)

  async function login(email: string, password: string): Promise<void> {
    // $fetch directly — avoids circular dep with useApi (which calls useAuth)
    try {
      const res = await $fetch<ApiSuccessResponse<LoginData>>('/auth/login', {
        method: 'POST',
        baseURL,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: { email, password },
      })
      token.value = res.data.token
      user.value = res.data.user
    }
    catch (err: unknown) {
      const fetchErr = err as { data?: { message?: string }; message?: string }
      throw new Error(fetchErr.data?.message ?? fetchErr.message ?? 'Login failed')
    }
  }

  async function logout(): Promise<void> {
    if (token.value) {
      await $fetch('/auth/logout', {
        method: 'POST',
        baseURL,
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token.value}`,
        },
      }).catch(() => {}) // best effort — always clear locally
    }
    token.value = null
    user.value = null
  }

  function setToken(t: string) {
    token.value = t
  }

  function clearToken() {
    token.value = null
    user.value = null
  }

  return {
    token: readonly(token),
    user: readonly(user),
    isAuthenticated,
    login,
    logout,
    setToken,
    clearToken,
  }
}
