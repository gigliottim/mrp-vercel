'use client'

import type { Column } from '@/components/crud/data-table'
import { Badge } from '@/components/ui/badge'
import type { EntidadesRow } from './entidades-form'

export const columns: Column<EntidadesRow>[] = [
  { key: 'razon_social', header: 'Razón social', render: (r) => String(r.razon_social ?? '') },
  { key: 'tipo', header: 'Tipo', render: (r) => <Badge variant="secondary">{String(r.tipo)}</Badge> },
  { key: 'identificacion_tributaria', header: 'CUIT', render: (r) => String(r.identificacion_tributaria ?? '') },
]
