import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { crearRol, eliminarRol } from './actions'

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
      {canAdmin ? (
        <form
          action={async (formData) => {
            'use server'
            await crearRol({ name: String(formData.get('name') ?? '') })
          }}
          className="flex max-w-md items-end gap-2"
        >
          <div className="flex-1 space-y-2">
            <label className="text-sm font-medium">Nombre del rol</label>
            <input name="name" required className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
          </div>
          <button type="submit" className="h-10 rounded-md bg-primary px-4 text-sm text-primary-foreground">Crear</button>
        </form>
      ) : null}
      <div className="rounded-md border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/50 text-left">
              <th className="p-2 font-medium">ID</th>
              <th className="p-2 font-medium">Nombre</th>
              {canAdmin ? <th className="p-2 font-medium">Acciones</th> : null}
            </tr>
          </thead>
          <tbody>
            {(res?.data ?? []).map((r) => (
              <tr key={r.id} className="border-b">
                <td className="p-2">{r.id}</td>
                <td className="p-2">{r.name}</td>
                {canAdmin ? (
                  <td className="p-2">
                    <button
                      onClick={async () => {
                        if (confirm(`¿Eliminar rol ${r.name}?`)) {
                          await eliminarRol(r.id)
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
          </tbody>
        </table>
      </div>
    </div>
  )
}
