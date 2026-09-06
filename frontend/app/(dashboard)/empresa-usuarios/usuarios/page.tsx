import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { UsuarioForm, type Usuario, type Rol } from './usuarios-form'
import { eliminarUsuario } from './actions'

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
      {canAdmin ? <UsuarioForm initial={null} roles={roles?.data ?? []} onCancel={() => {}} /> : null}
      <div className="rounded-md border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/50 text-left">
              <th className="p-2 font-medium">Email</th>
              <th className="p-2 font-medium">Nombre</th>
              <th className="p-2 font-medium">Rol</th>
              {canAdmin ? <th className="p-2 font-medium">Acciones</th> : null}
            </tr>
          </thead>
          <tbody>
            {(usuarios?.data ?? []).map((u) => (
              <tr key={u.user_id} className="border-b">
                <td className="p-2">{u.email}</td>
                <td className="p-2">{u.nombre || '—'}</td>
                <td className="p-2">{u.role_nombre}</td>
                {canAdmin ? (
                  <td className="p-2">
                    <button
                      onClick={async () => {
                        if (confirm(`¿Eliminar a ${u.email}?`)) {
                          await eliminarUsuario(u.user_id)
                          window.location.reload()
                        }
                      }}
                      className="text-sm text-destructive hover:underline"
                    >
                      Eliminar
                    </button>
                  </td>
                ) : null}
              </tr>
            ))}
            {(usuarios?.data ?? []).length === 0 ? (
              <tr><td colSpan={4} className="h-16 text-center text-muted-foreground">Sin usuarios</td></tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  )
}
