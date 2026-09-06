'use client'

import type { Column } from '@/components/crud/data-table'
import { Badge } from '@/components/ui/badge'
import type { ValidacionRow } from './validacion-form'

export type ValidacionRowConNombres = ValidacionRow & {
  origen_nombre: string
  destino_nombre: string
}

export const columns: Column<ValidacionRowConNombres>[] = [
  { key: 'origen', header: 'Origen', render: (r) => r.origen_nombre },
  { key: 'destino', header: 'Destino', render: (r) => r.destino_nombre },
  {
    key: 'activo',
    header: 'Estado',
    render: (r) =>
      r.activo ? (
        <Badge variant="outline">Activo</Badge>
      ) : (
        <Badge variant="destructive">Inactivo</Badge>
      ),
  },
]
