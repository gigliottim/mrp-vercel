'use client'

import type { Column } from '@/components/crud/data-table'
import { Badge } from '@/components/ui/badge'
import type { BomRow } from './bom-form'

export const columns: Column<BomRow>[] = [
  { key: 'id', header: 'ID', render: (r) => String(r.id) },
  { key: 'variante_padre_id', header: 'Variante padre', render: (r) => `#${r.variante_padre_id}` },
  { key: 'version', header: 'Versión', render: (r) => <code className="text-sm">{String(r.version)}</code> },
  {
    key: 'activa',
    header: 'Estado',
    render: (r) =>
      r.activa ? <Badge variant="outline">Activa</Badge> : <Badge variant="secondary">Inactiva</Badge>,
  },
  { key: 'fecha_efectiva', header: 'Efectiva desde', render: (r) => String(r.fecha_efectiva) },
]
