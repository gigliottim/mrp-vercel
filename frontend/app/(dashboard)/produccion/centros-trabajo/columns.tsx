'use client'

import type { Column } from '@/components/crud/data-table'
import { Badge } from '@/components/ui/badge'
import type { CentroRow } from './centros-form'

export const columns: Column<CentroRow>[] = [
  { key: 'codigo', header: 'Código', render: (r) => <code className="text-sm">{String(r.codigo)}</code> },
  { key: 'nombre', header: 'Nombre', render: (r) => String(r.nombre) },
  { key: 'tipo', header: 'Tipo', render: (r) => String(r.tipo ?? '') },
  { key: 'capacidad_horas_dia', header: 'Hs/día', render: (r) => String(r.capacidad_horas_dia ?? 0) },
  { key: 'costo_hora', header: 'Costo/h', render: (r) => String(r.costo_hora ?? 0) },
  { key: 'activo', header: 'Estado', render: (r) => (r.activo ? <Badge variant="outline">Activo</Badge> : <Badge variant="destructive">Inactivo</Badge>) },
]
