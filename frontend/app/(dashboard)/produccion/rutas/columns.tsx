'use client'

import type { Column } from '@/components/crud/data-table'
import type { RutaRow } from './ruta-form'
import type { BomOpt } from './ruta-form'
import { ClonarRutaDialog } from './clonar-dialog'

export function makeColumns(boms: BomOpt[]): Column<RutaRow>[] {
  // Agrupar por BOM: la primera fila de cada BOM muestra el botón Clonar
  const vistos = new Set<number>()
  return [
    { key: 'id', header: 'ID', render: (r) => String(r.id) },
    { key: 'bom_id', header: 'BOM', render: (r) => `#${r.bom_id}` },
    { key: 'secuencia', header: 'Sec.', render: (r) => String(r.secuencia) },
    { key: 'centro_trabajo_id', header: 'Centro', render: (r) => `#${r.centro_trabajo_id}` },
    { key: 'descripcion', header: 'Descripción', render: (r) => String(r.descripcion) },
    { key: 'tiempo_setup_mins', header: 'Setup (min)', render: (r) => String(r.tiempo_setup_mins ?? 0) },
    { key: 'tiempo_proceso_unitario_mins', header: 'Proceso (min)', render: (r) => String(r.tiempo_proceso_unitario_mins ?? 0) },
    {
      key: 'clonar',
      header: 'Clonar',
      render: (r) => {
        if (vistos.has(r.bom_id)) return null
        vistos.add(r.bom_id)
        return <ClonarRutaDialog bomId={r.bom_id} boms={boms} />
      },
    },
  ]
}