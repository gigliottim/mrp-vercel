'use client'

import type { Column } from '@/components/crud/data-table'
import { Badge } from '@/components/ui/badge'
import type { PlanRow } from './planificacion-form'

const ESTADO_VARIANT: Record<string, string> = {
  programado: 'outline',
  en_ejecucion: 'default',
  completado: 'secondary',
}

export const columns: Column<PlanRow>[] = [
  { key: 'id', header: 'ID', render: (r) => String(r.id) },
  { key: 'orden_produccion_id', header: 'Orden', render: (r) => `#${r.orden_produccion_id}` },
  { key: 'centro_trabajo_id', header: 'Centro', render: (r) => `#${r.centro_trabajo_id}` },
  { key: 'periodo', header: 'Periodo', render: (r) => <code className="text-xs">{String(r.periodo)}</code> },
  { key: 'estado', header: 'Estado', render: (r) => <Badge variant={(ESTADO_VARIANT[String(r.estado)] ?? 'outline') as 'outline'}>{String(r.estado)}</Badge> },
]
