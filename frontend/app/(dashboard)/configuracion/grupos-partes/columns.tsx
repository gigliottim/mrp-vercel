'use client'

import type { Column } from '@/components/crud/data-table'
import { Badge } from '@/components/ui/badge'
import type { GruposPartesRow } from './grupos-partes-form'

export const columns: Column<GruposPartesRow>[] = [
  { key: 'codigo', header: 'Código', render: (r) => String(r.codigo ?? '') },
  { key: 'nombre', header: 'Nombre', render: (r) => String(r.nombre ?? '') },
  { key: 'color', header: 'color', render: (r) => (<span className="inline-block h-4 w-4 rounded border" style={{ backgroundColor: String(r.color) }} />) },
  { key: 'activo', header: 'Estado', render: (r) => (r.activo ? <Badge variant="outline">Activo</Badge> : <Badge variant="destructive">Inactivo</Badge>) },
]
