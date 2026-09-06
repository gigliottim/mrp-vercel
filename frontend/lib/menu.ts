import type { Paginated } from './api'

export type MenuItem = {
  id: number
  code: string
  label: string
  route: string | null
  icon: string | null
  section_key: string
  section_label: string
  parent_id: number | null
  sort_order: number
}

export async function fetchMenu(token: string): Promise<MenuItem[]> {
  const res = await fetch(
    `${process.env.NEXT_PUBLIC_API_URL ?? 'https://api.mimrp.com.ar'}/api/v1/menu`,
    {
      headers: { Authorization: `Bearer ${token}` },
      cache: 'no-store',
    }
  )
  if (!res.ok) return []
  const body = (await res.json()) as Paginated<MenuItem>
  return body.data ?? []
}

export function groupBySection(items: MenuItem[]): { key: string; label: string; items: MenuItem[] }[] {
  const sections = new Map<string, { key: string; label: string; items: MenuItem[] }>()
  for (const item of items) {
    const existing = sections.get(item.section_key)
    if (existing) {
      existing.items.push(item)
    } else {
      sections.set(item.section_key, {
        key: item.section_key,
        label: item.section_label,
        items: [item],
      })
    }
  }
  return Array.from(sections.values())
}
