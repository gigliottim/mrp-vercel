'use client'

import type { Column } from '@/components/crud/data-table'
import type { CompraRow } from './compras-form'

export const columns: Column<CompraRow>[] = [
  { key: 'id', header: 'ID', render: (r) => String(r.id) },
  { key: 'fecha', header: 'Fecha', render: (r) => String(r.fecha) },
  { key: 'nro_comprobante', header: 'Comprobante', render: (r) => (r.nro_comprobante ? <code className="text-sm">{r.nro_comprobante}</code> : '—') },
  { key: 'precio_unitario', header: 'Precio unit.', render: (r) => String(r.precio_unitario) },
  { key: 'id_entidad', header: 'Proveedor', render: (r) => `#${r.id_entidad}` },
]
