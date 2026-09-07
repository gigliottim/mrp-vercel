'use client'

import { useState } from 'react'
import { ArbolEstructura } from './arbol-estructura'
import { DetallePanel } from './detalle-panel'

export type VarianteOpt = {
  id: number
  codigo_variante: string
  detalle: string
  parte_codigo?: string | null
  parte_detalle?: string | null
  tipo_codigo?: string | null
}

export type UmOpt = { id: number; simbolo: string }

export type TreeRow = {
  variante_id: number
  parte_codigo: string
  parte_detalle: string
  codigo_variante: string
  variante_detalle: string
  tipo_codigo: string | null
  parent_id: number | null
  bom_detalle_id: number | null
  cantidad: number
  unidad_medida_id: number | null
  unidad: string | null
  nivel: number
}

export function MaestroClient({
  filas,
  variantes,
  ums,
  canAdmin,
}: {
  filas: TreeRow[]
  variantes: VarianteOpt[]
  ums: UmOpt[]
  canAdmin: boolean
}) {
  const [selectedNodeId, setSelectedNodeId] = useState<number | null>(
    filas.length > 0 ? filas[0].variante_id : null
  )
  const nodo = filas.find((f) => f.variante_id === selectedNodeId) ?? null

  return (
    <div className="grid items-start gap-6 lg:grid-cols-[2fr_3fr]">
      <div className="min-w-0">
        <h2 className="mb-2 text-base font-semibold">Estructura</h2>
        <ArbolEstructura filas={filas} selectedNodeId={selectedNodeId} onSelectNode={setSelectedNodeId} />
      </div>
      <div className="min-w-0">
        <h2 className="mb-2 text-base font-semibold">Composición</h2>
        <DetallePanel
          nodo={nodo}
          filas={filas}
          variantes={variantes}
          ums={ums}
          canAdmin={canAdmin}
        />
      </div>
    </div>
  )
}