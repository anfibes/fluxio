export interface ApiSuccessResponse<T> {
  success: true
  message: string
  data: T
}

export interface ApiErrorResponse {
  success: false
  message: string
  errors?: Record<string, string[]>
}

export interface ApiPaginatedMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface ApiPaginatedResponse<T> {
  success: true
  message: string
  data: T[]
  meta: ApiPaginatedMeta
}

export type ApiResponse<T> = ApiSuccessResponse<T> | ApiErrorResponse
