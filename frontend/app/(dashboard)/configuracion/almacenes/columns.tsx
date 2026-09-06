'use client'

import type { Column } from '@/components/crud/data-table'
import { Badge } from '@/components/ui/badge'
import type { AlmacenesRow } from './almacenes-form'

export const columns: Column<AlmacenesRow>[] = [
  { key: 'codigo', header: 'Código', render: (r) => String(r.codigo ?? '') },
  { key: 'nombre', header: 'Nombre', render: (r) => String(r.nombre ?? '') },
  { key: 'es_deposito_venta', header: 'Venta', render: (r) => (r.es_deposito_venta ? <Badge variant="outline">Sí</Badge> : <Badge variant="secondary">No</Badge>) },
  { key: 'es_deposito_produccion', header: 'Producción', render: (r) => (r.es_deposito_produccion ? <Badge variant="outline">Sí</Badge> : <Badge variant="secondary">No</Badge>) },
]
