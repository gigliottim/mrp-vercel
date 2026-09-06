import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { CopiarForm, type VarianteOpt } from './copiar-form'

export const dynamic = 'force-dynamic'

export default async function CopiarComponentesPage() {
  const session = await getSession()
  if (!session) return null
  const variantes = await apiFetch<{ data: VarianteOpt[] }>('/api/v1/variantes?perPage=500', session.accessToken).catch(() => null)
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Copiar Componentes de BOM</h1>
        <p className="text-sm text-muted-foreground">Copia los componentes de nivel 1 de una pieza a otra</p>
      </div>
      {canAdmin ? (
        <CopiarForm variantes={variantes?.data ?? []} />
      ) : (
        <p className="text-sm text-muted-foreground">Sin permisos para copiar componentes.</p>
      )}
    </div>
  )
}
