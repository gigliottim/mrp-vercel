import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { UsuariosClient } from './usuarios-client'
import type { Usuario, Rol } from './usuarios-form'

export const dynamic = 'force-dynamic'

export default async function UsuariosPage() {
  const session = await getSession()
  if (!session) return null
  const [usuarios, roles] = await Promise.all([
    apiFetch<{ data: Usuario[] }>('/api/v1/empresa/usuarios', session.accessToken).catch(() => null),
    apiFetch<{ data: Rol[] }>('/api/v1/empresa/roles', session.accessToken).catch(() => null),
  ])
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Usuarios</h1>
        <p className="text-sm text-muted-foreground">Usuarios de la empresa</p>
      </div>
      <UsuariosClient usuarios={usuarios?.data ?? []} roles={roles?.data ?? []} canAdmin={canAdmin} />
    </div>
  )
}
