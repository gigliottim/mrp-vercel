export type Pagination = { page: number; perPage: number; offset: number }

export function parsePagination(query: Record<string, string | undefined>): Pagination {
  const page = Math.max(1, Number(query.page ?? 1) || 1)
  const perPage = Math.min(100, Math.max(1, Number(query.perPage ?? 20) || 20))
  return { page, perPage, offset: (page - 1) * perPage }
}
