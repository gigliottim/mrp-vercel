'use client'

import { cn } from '@/lib/utils'
import type { TreeRow } from './maestro-client'

export function ArbolEstructura({
  filas,
  selectedNodeId,
  onSelectNode,
}: {
  filas: TreeRow[]
  selectedNodeId: number | null
  onSelectNode: (id: number) => void
}) {
  return (
    <div className="rounded-md border">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b bg-muted/50">
            <th className="p-2 text-left font-medium">Código</th>
            <th className="p-2 text-left font-medium">Detalle</th>
            <th className="p-2 text-right font-medium">Cant.</th>
            <th className="p-2 text-left font-medium">Unidad</th>
          </tr>
        </thead>
        <tbody>
          {filas.map((f) => (
            <tr
              key={`${f.variante_id}-${f.nivel}-${f.bom_detalle_id ?? 'root'}`}
              className={cn(
                'cursor-pointer border-b hover:bg-muted/50',
                selectedNodeId === f.variante_id && 'bg-muted'
              )}
              onClick={() => onSelectNode(f.variante_id)}
            >
              <td className="p-2" style={{ paddingLeft: `${12 + f.nivel * 20}px` }}>
                <span className="font-medium">{f.parte_codigo}</span>
                <span className="text-muted-foreground"> / {f.codigo_variante}</span>
              </td>
              <td className="p-2">{f.variante_detalle}</td>
              <td className="p-2 text-right font-mono">{f.cantidad}</td>
              <td className="p-2">{f.unidad ?? '—'}</td>
            </tr>
          ))}
          {filas.length === 0 ? (
            <tr>
              <td colSpan={4} className="h-16 text-center text-muted-foreground">
                La variante no tiene BOM activa
              </td>
            </tr>
          ) : null}
        </tbody>
      </table>
    </div>
  )
}