'use client'

import type { Column } from '@/components/crud/data-table'
import type { RutaRow } from './ruta-form'

export const columns: Column<RutaRow>[] = [
  { key: 'id', header: 'ID', render: (r) => String(r.id) },
  { key: 'bom_id', header: 'BOM', render: (r) => `#${r.bom_id}` },
  { key: 'secuencia', header: 'Sec.', render: (r) => String(r.secuencia) },
  { key: 'centro_trabajo_id', header: 'Centro', render: (r) => `#${r.centro_trabajo_id}` },
  { key: 'descripcion', header: 'Descripción', render: (r) => String(r.descripcion) },
  { key: 'tiempo_setup_mins', header: 'Setup (min)', render: (r) => String(r.tiempo_setup_mins ?? 0) },
  { key: 'tiempo_proceso_unitario_mins', header: 'Proceso (min)', render: (r) => String(r.tiempo_proceso_unitario_mins ?? 0) },
]