import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { RolesClient } from './roles-client'

export const dynamic = 'force-dynamic'

type Rol = { id: number; name: string; guard_name: string }

export default async function RolesPage() {
  const session = await getSession()
  if (!session) return null
  const res = await apiFetch<{ data: Rol[] }>('/api/v1/empresa/roles', session.accessToken).catch(() => null)
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Roles</h1>
        <p className="text-sm text-muted-foreground">Roles de la empresa</p>
      </div>
      <RolesClient roles={res?.data ?? []} canAdmin={canAdmin} />
    </div>
  )
}
