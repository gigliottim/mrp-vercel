'use client'

import type { Column } from '@/components/crud/data-table'
import { Badge } from '@/components/ui/badge'
import type { TiposPartesRow } from './tipos-partes-form'

export const columns: Column<TiposPartesRow>[] = [
  { key: 'codigo', header: 'Código', render: (r) => String(r.codigo ?? '') },
  { key: 'nombre', header: 'Nombre', render: (r) => String(r.nombre ?? '') },
  { key: 'orden', header: 'Orden', render: (r) => String(r.orden ?? '') },
  { key: 'activo', header: 'Estado', render: (r) => (r.activo ? <Badge variant="outline">Activo</Badge> : <Badge variant="destructive">Inactivo</Badge>) },
]
