'use client'

import type { Column } from '@/components/crud/data-table'
import { Badge } from '@/components/ui/badge'
import type { ValidacionRow, Deposito } from './validacion-form'

export function makeColumns(depositos: Deposito[]): Column<ValidacionRow>[] {
  const nombreDeposito = (id: number) =>
    depositos.find((d) => d.id === id)?.nombre ?? `#${id}`
  return [
    { key: 'origen', header: 'Origen', render: (r) => nombreDeposito(Number(r.tipo_deposito_origen_id)) },
    { key: 'destino', header: 'Destino', render: (r) => nombreDeposito(Number(r.tipo_deposito_destino_id)) },
    { key: 'activo', header: 'Estado', render: (r) => (r.activo ? <Badge variant="outline">Activo</Badge> : <Badge variant="destructive">Inactivo</Badge>) },
  ]
}
