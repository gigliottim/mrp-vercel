import { getSession } from '@/lib/session'
import { ImportClient } from './import-client'

export const dynamic = 'force-dynamic'

export default async function ImportarPartesPage() {
  const session = await getSession()
  if (!session) return null
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Importar / Exportar Partes y Variantes</h1>
        <p className="text-sm text-muted-foreground">CSV con plantilla descargable. Formato: 28 columnas parte + variante por fila.</p>
      </div>
      {canAdmin ? (
        <ImportClient token={session.accessToken} />
      ) : (
        <p className="text-sm text-muted-foreground">Solo administradores pueden importar/exportar.</p>
      )}
    </div>
  )
}
