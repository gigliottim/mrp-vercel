import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { PermisosClient } from './permisos-client'

export const dynamic = 'force-dynamic'

export default async function PermisosPage() {
  const session = await getSession()
  if (!session) return null
  const [tree, acl, roles] = await Promise.all([
    apiFetch<{ data: Array<{ id: number; label: string; section_key: string; section_label: string; parent_id: number | null; sort_order: number }> }>('/api/v1/empresa/permisos/tree', session.accessToken).catch(() => null),
    apiFetch<{ data: Array<{ id: number; menu_item_id: number; subject_type: string; subject_id: number; effect: string }> }>('/api/v1/empresa/permisos', session.accessToken).catch(() => null),
    apiFetch<{ data: Array<{ id: number; name: string }> }>('/api/v1/empresa/roles', session.accessToken).catch(() => null),
  ])
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  if (!canAdmin) {
    return <p className="text-sm text-muted-foreground">Solo el administrador puede gestionar permisos.</p>
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Permisos</h1>
        <p className="text-sm text-muted-foreground">ACL por rol sobre los ítems del menú</p>
      </div>
      <PermisosClient
        items={tree?.data ?? []}
        acl={acl?.data ?? []}
        roles={roles?.data ?? []}
      />
    </div>
  )
}
