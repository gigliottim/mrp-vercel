import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { ConfiguracionForm, type ConfiguracionGeneral } from './general-form'
import { actualizarGeneral, upsertClave } from './actions'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { RecalcularGeometria } from './recalcular-geometria'

export const dynamic = 'force-dynamic'

export type KvRow = {
  id: number
  clave: string
  valor: string
  descripcion: string | null
  tipo: string
}

export default async function ConfiguracionGeneralPage() {
  const session = await getSession()
  if (!session) return null

  const [general, kv] = await Promise.all([
    apiFetch<ConfiguracionGeneral>('/api/v1/configuracion', session.accessToken).catch(() => null),
    apiFetch<Paginated<KvRow>>('/api/v1/configuracion/kv', session.accessToken).catch(() => null),
  ])

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Configuración</h1>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Configuración general</CardTitle>
        </CardHeader>
        <CardContent>
          {general ? <ConfiguracionForm initial={general} onSubmit={actualizarGeneral} /> : (
            <p className="text-sm text-muted-foreground">Sin configuración</p>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Claves y valores</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Clave</TableHead>
                <TableHead>Valor</TableHead>
                <TableHead>Tipo</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(kv?.data ?? []).map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="font-mono text-sm">{row.clave}</TableCell>
                  <TableCell className="font-mono text-sm">{row.valor}</TableCell>
                  <TableCell>{row.tipo}</TableCell>
                </TableRow>
              ))}
              {!kv || kv.data.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={3} className="h-16 text-center text-muted-foreground">
                    Sin claves configuradas
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Mantenimiento</CardTitle>
        </CardHeader>
        <CardContent>
          <RecalcularGeometria token={session.accessToken} />
        </CardContent>
      </Card>
    </div>
  )
}
