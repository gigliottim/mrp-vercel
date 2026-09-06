'use client'

import type { Column } from '@/components/crud/data-table'
import { Badge } from '@/components/ui/badge'
import type { VarianteRow } from './variantes-form'

const ESTADO_BADGE: Record<string, string> = {
  activa: 'outline',
  desarrollo: 'secondary',
  obsoleta: 'destructive',
  descontinuada: 'destructive',
}

export const columns: Column<VarianteRow>[] = [
  {
    key: 'codigo_variante',
    header: 'Código variante',
    render: (r) => <code className="text-sm">{String(r.codigo_variante)}</code>,
  },
  { key: 'detalle', header: 'Detalle', render: (r) => String(r.detalle) },
  {
    key: 'estado',
    header: 'Estado',
    render: (r) => (
      <Badge variant={(ESTADO_BADGE[String(r.estado)] ?? 'outline') as 'outline'}>
        {String(r.estado)}
      </Badge>
    ),
  },
  {
    key: 'stock_actual',
    header: 'Stock',
    render: (r) => (
      <span className={Number(r.stock_actual) <= Number(r.punto_pedido) ? 'font-semibold text-destructive' : ''}>
        {String(r.stock_actual ?? 0)}
      </span>
    ),
  },
  { key: 'punto_pedido', header: 'Pto. pedido', render: (r) => String(r.punto_pedido ?? 0) },
  { key: 'costo', header: 'Costo', render: (r) => String(r.costo ?? 0) },
]
