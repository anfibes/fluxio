import type { ApiErrorResponse, ApiPaginatedResponse, ApiResponse, ApiSuccessResponse } from '~/types/api'

export class ApiError extends Error {
  status: number
  errors?: Record<string, string[]>

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

export function useApi() {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string

  const { token } = useAuth()

  function authHeaders(): Record<string, string> {
    return token.value ? { Authorization: `Bearer ${token.value}` } : {}
  }

  async function get<T>(path: string): Promise<ApiResponse<T>> {
    return request<ApiResponse<T>>(path, { method: 'GET' })
  }

  async function post<T>(path: string, body: object): Promise<ApiResponse<T>> {
    return request<ApiResponse<T>>(path, { method: 'POST', body })
  }

  async function put<T>(path: string, body: object): Promise<ApiResponse<T>> {
    return request<ApiResponse<T>>(path, { method: 'PUT', body })
  }

  async function patch<T>(path: string, body: object): Promise<ApiResponse<T>> {
    return request<ApiResponse<T>>(path, { method: 'PATCH', body })
  }

  async function del<T>(path: string): Promise<ApiResponse<T>> {
    return request<ApiResponse<T>>(path, { method: 'DELETE' })
  }

  async function paginated<T>(path: string, params?: Record<string, string | number>): Promise<ApiPaginatedResponse<T> | ApiErrorResponse> {
    return request<ApiPaginatedResponse<T> | ApiErrorResponse>(path, { method: 'GET', params })
  }

  async function request<T>(path: string, opts: Parameters<typeof $fetch>[1] = {}): Promise<T> {
    try {
      return await $fetch<T>(path, {
        baseURL,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...authHeaders(),
        },
        ...opts,
      })
    }
    catch (err: unknown) {
      const fetchError = err as { status?: number; data?: ApiErrorResponse; message?: string }
      throw new ApiError(
        fetchError.data?.message ?? fetchError.message ?? 'Unknown error',
        fetchError.status ?? 0,
        fetchError.data?.errors,
      )
    }
  }

  return { get, post, put, patch, del, paginated }
}
