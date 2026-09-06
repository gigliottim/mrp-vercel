'use client'

import type { Column } from '@/components/crud/data-table'
import { Badge } from '@/components/ui/badge'
import type { ParteRow } from './partes-form'

export const columns: Column<ParteRow>[] = [
  { key: 'codigo', header: 'Código', render: (r) => <code className="text-sm">{String(r.codigo)}</code> },
  { key: 'detalle', header: 'Detalle', render: (r) => String(r.detalle) },
  { key: 'id_tipo', header: 'Tipo', render: (r) => `#${r.id_tipo}` },
  { key: 'id_grupo', header: 'Grupo', render: (r) => `#${r.id_grupo}` },
  {
    key: 'activo',
    header: 'Estado',
    render: (r) =>
      r.activo ? <Badge variant="outline">Activo</Badge> : <Badge variant="destructive">Inactivo</Badge>,
  },
]
