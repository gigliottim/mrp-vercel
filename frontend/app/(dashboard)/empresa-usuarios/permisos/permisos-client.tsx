'use client'

import { useMemo, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { toast } from 'sonner'
import { guardarPermisos } from './actions'

type MenuItem = { id: number; label: string; section_key: string; section_label: string; parent_id: number | null; sort_order: number }
type AclRow = { id: number; menu_item_id: number; subject_type: string; subject_id: number; effect: string }
type Rol = { id: number; name: string }

export function PermisosClient({ items, acl, roles }: { items: MenuItem[]; acl: AclRow[]; roles: Rol[] }) {
  const [selectedRol, setSelectedRol] = useState(roles[0]?.id ?? 0)
  const [busy, setBusy] = useState(false)
  const [query, setQuery] = useState('')
  const [colapsadas, setColapsadas] = useState<Set<string>>(new Set())

  const aclFor = (menuItemId: number) =>
    acl.find((a) => a.menu_item_id === menuItemId && a.subject_type === 'role' && a.subject_id === selectedRol)

  const toggle = (menuItemId: number) => {
    const existing = aclFor(menuItemId)
    if (existing) {
      existing.effect = existing.effect === 'allow' ? 'deny' : 'allow'
    } else {
      acl.push({ id: 0, menu_item_id: menuItemId, subject_type: 'role', subject_id: selectedRol, effect: 'allow' })
    }
    // forzar re-render
    setSelectedRol((r) => r)
  }

  const handleGuardar = async () => {
    setBusy(true)
    const perms = acl
      .filter((a) => a.subject_type === 'role' && a.subject_id === selectedRol)
      .map((a) => ({ subject_type: 'role', subject_id: selectedRol, effect: a.effect }))
    const menuItemIds = [...new Set(acl.filter((a) => a.subject_type === 'role' && a.subject_id === selectedRol).map((a) => a.menu_item_id))]
    const res = await guardarPermisos({ menu_item_ids: menuItemIds, perms })
    setBusy(false)
    if (res.ok) toast.success('Permisos guardados')
    else toast.error(res.error ?? 'Error')
  }

  const secciones = useMemo(() => {
    const q = query.trim().toLowerCase()
    const filtrados = q
      ? items.filter(
          (i) =>
            i.label.toLowerCase().includes(q) ||
            i.section_label.toLowerCase().includes(q)
        )
      : items
    return [...new Set(filtrados.map((i) => i.section_label))].map((seccion) => ({
      seccion,
      items: filtrados.filter((i) => i.section_label === seccion),
    }))
  }, [items, query])

  const toggleSeccion = (seccion: string) => {
    setColapsadas((prev) => {
      const next = new Set(prev)
      if (next.has(seccion)) next.delete(seccion)
      else next.add(seccion)
      return next
    })
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end gap-4">
        <div className="w-64 space-y-2">
          <label className="text-sm font-medium">Rol</label>
          <select
            value={selectedRol}
            onChange={(e) => setSelectedRol(Number(e.target.value))}
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          >
            {roles.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
          </select>
        </div>
        <div className="w-64 space-y-2">
          <label className="text-sm font-medium">Buscar</label>
          <Input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Filtrar permisos..."
          />
        </div>
        <Button onClick={handleGuardar} disabled={busy}>{busy ? 'Guardando...' : 'Guardar permisos'}</Button>
      </div>

      {secciones.length === 0 ? (
        <p className="text-sm text-muted-foreground">Sin resultados para “{query}”.</p>
      ) : null}
      {secciones.map(({ seccion, items: seccionItems }) => (
        <div key={seccion} className="rounded-md border p-4">
          <button
            type="button"
            className="flex w-full items-center gap-2 text-left font-semibold"
            onClick={() => toggleSeccion(seccion)}
          >
            <span className="text-xs text-muted-foreground">{colapsadas.has(seccion) ? '▶' : '▼'}</span>
            {seccion}
            <span className="text-xs text-muted-foreground">({seccionItems.length})</span>
          </button>
          {!colapsadas.has(seccion) ? (
            <div className="mt-2 grid gap-1 sm:grid-cols-2 lg:grid-cols-3">
              {seccionItems.map((item) => {
                const aclRow = aclFor(item.id)
                const allow = aclRow?.effect === 'allow'
                return (
                  <label key={item.id} className="flex items-center gap-2 text-sm">
                    <Checkbox checked={allow} onCheckedChange={() => toggle(item.id)} />
                    {item.label}
                  </label>
                )
              })}
            </div>
          ) : null}
        </div>
      ))}
    </div>
  )
}