import { getSession } from '@/lib/session'
import { MaestroImportClient } from './maestro-import-client'

export const dynamic = 'force-dynamic'

export default async function ImportarMaestroPage() {
  const session = await getSession()
  if (!session) return null
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Importar / Exportar Maestro de Productos</h1>
        <p className="text-sm text-muted-foreground">CSV de relaciones BOM: padre_parte, padre_variante, hijo_parte, hijo_variante, cantidad, unidad.</p>
      </div>
      {canAdmin ? (
        <MaestroImportClient token={session.accessToken} />
      ) : (
        <p className="text-sm text-muted-foreground">Solo administradores pueden importar/exportar.</p>
      )}
    </div>
  )
}
