import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { EmpresaForm, type Empresa } from './empresa-form'

export const dynamic = 'force-dynamic'

export default async function EmpresaPage() {
  const session = await getSession()
  if (!session) return null
  const res = await apiFetch<{ data: Empresa }>('/api/v1/empresa', session.accessToken).catch(() => null)
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Empresa</h1>
        <p className="text-sm text-muted-foreground">Datos de la empresa</p>
      </div>
      {res?.data ? (
        canAdmin ? (
          <EmpresaForm empresa={res.data} />
        ) : (
          <p className="text-sm text-muted-foreground">Solo el administrador puede editar los datos de la empresa.</p>
        )
      ) : (
        <p className="text-sm text-muted-foreground">No se pudieron cargar los datos.</p>
      )}
    </div>
  )
}
