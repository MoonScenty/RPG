import strings from '@/locales/ko'

export class ApiError extends Error {
  status: number
  errors?: Record<string, string[]>

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'))
  const value = match?.[1]
  return value !== undefined ? decodeURIComponent(value) : null
}

/** ApiError의 필드별 검증 메시지 중 첫 번째를, 없으면 기본 메시지를 반환한다. */
export function firstErrorMessage(e: unknown, fallback: string): string {
  if (!(e instanceof ApiError)) return fallback
  const first = e.errors ? Object.values(e.errors)[0]?.[0] : undefined
  return first ?? e.message
}

// Laravel Sanctum SPA 인증: 상태 변경 요청 전에 /sanctum/csrf-cookie를 한 번
// 호출해 XSRF-TOKEN 쿠키를 받아두고, 이후 요청 헤더(X-XSRF-TOKEN)로 그대로
// 돌려보내야 CSRF 검증을 통과한다.
async function ensureCsrfCookie(): Promise<void> {
  if (readCookie('XSRF-TOKEN')) return
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
}

export async function apiRequest<T>(method: string, path: string, body?: unknown): Promise<T> {
  const isMutating = method !== 'GET'
  if (isMutating) await ensureCsrfCookie()

  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'

  const xsrfToken = readCookie('XSRF-TOKEN')
  if (isMutating && xsrfToken) headers['X-XSRF-TOKEN'] = xsrfToken

  const response = await fetch(path, {
    method,
    headers,
    credentials: 'include',
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  if (response.status === 204) return undefined as T

  const isJson = response.headers.get('content-type')?.includes('application/json') ?? false
  const data = isJson ? await response.json() : null

  if (!response.ok) {
    throw new ApiError(data?.message ?? strings.common.requestFailed, response.status, data?.errors)
  }

  return data as T
}
