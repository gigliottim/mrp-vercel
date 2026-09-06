const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'https://api.mimrp.com.ar'

export class ApiError extends Error {
  code: string
  status: number
  constructor(code: string, message: string, status: number) {
    super(message)
    this.code = code
    this.status = status
  }
}

export async function apiFetch<T>(
  path: string,
  token: string,
  options: RequestInit = {}
): Promise<T> {
  const res = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
      ...options.headers,
    },
  })
  if (!res.ok) {
    const body = await res.json().catch(() => null)
    throw new ApiError(
      body?.error?.code ?? 'UNKNOWN',
      body?.error?.message ?? `HTTP ${res.status}`,
      res.status
    )
  }
  if (res.status === 204) return undefined as T
  return res.json() as Promise<T>
}

export type Paginated<T> = {
  data: T[]
  pagination: { page: number; perPage: number; total: number }
}
