import type { Column } from '@/components/crud/data-table'
import { Badge } from '@/components/ui/badge'
import { tipoLabel } from './unidades-labels'
import type { UnidadMedida } from './unidades-form'

export const columns: Column<UnidadMedida>[] = [
  { key: 'tipo', header: 'Tipo', render: (r) => tipoLabel(String(r.tipo)) },
  { key: 'unidad', header: 'Unidad', render: (r) => String(r.unidad) },
  { key: 'simbolo', header: 'Símbolo', render: (r) => <code>{String(r.simbolo)}</code> },
  { key: 'equivalencia_base', header: 'Equivalencia', render: (r) => String(r.equivalencia_base) },
  { key: 'es_base', header: 'Base', render: (r) => (r.es_base ? <Badge>Base</Badge> : null) },
  { key: 'activo', header: 'Estado', render: (r) => (r.activo ? <Badge variant="outline">Activo</Badge> : <Badge variant="destructive">Inactivo</Badge>) },
]