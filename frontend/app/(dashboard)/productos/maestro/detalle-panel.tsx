'use client'

import { useMemo, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { VarianteOpt, UmOpt, TreeRow } from './maestro-client'
import {
  AgregarComponenteDialog,
  EditarComponenteDialog,
  EliminarComponenteDialog,
  ReemplazarComponenteDialog,
} from './componente-dialogs'

type DialogState =
  | { kind: 'agregar' }
  | { kind: 'editar'; hijo: TreeRow }
  | { kind: 'reemplazar'; hijo: TreeRow }
  | { kind: 'eliminar'; hijo: TreeRow }
  | null

export function DetallePanel({
  nodo,
  filas,
  variantes,
  ums,
  canAdmin,
}: {
  nodo: TreeRow | null
  filas: TreeRow[]
  variantes: VarianteOpt[]
  ums: UmOpt[]
  canAdmin: boolean
}) {
  const [dialog, setDialog] = useState<DialogState>(null)

  const hijos = useMemo(() => {
    if (!nodo) return []
    return filas.filter((f) => f.parent_id === nodo.variante_id && f.nivel === nodo.nivel + 1)
  }, [filas, nodo])

  if (!nodo) {
    return (
      <div className="rounded-md border p-8 text-center text-sm text-muted-foreground">
        Selecciona un nodo del árbol para ver sus componentes.
      </div>
    )
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-base font-semibold">
          {nodo.parte_codigo} / {nodo.codigo_variante}
          <span className="ml-2 text-sm font-normal text-muted-foreground">{nodo.variante_detalle}</span>
        </h2>
        {canAdmin ? (
          <Button size="sm" onClick={() => setDialog({ kind: 'agregar' })}>
            Agregar componente
          </Button>
        ) : null}
      </div>
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Código</TableHead>
              <TableHead>Detalle</TableHead>
              <TableHead className="text-right">Cantidad</TableHead>
              <TableHead>Unidad</TableHead>
              {canAdmin ? <TableHead className="text-right">Acciones</TableHead> : null}
            </TableRow>
          </TableHeader>
          <TableBody>
            {hijos.map((h) => (
              <TableRow key={h.bom_detalle_id ?? h.variante_id}>
                <TableCell>
                  {h.parte_codigo} / {h.codigo_variante}
                </TableCell>
                <TableCell>
                  {h.variante_detalle}
                  {h.tipo_codigo ? (
                    <span className="ml-1 text-xs text-muted-foreground">({h.tipo_codigo})</span>
                  ) : null}
                </TableCell>
                <TableCell className="text-right font-mono">{h.cantidad}</TableCell>
                <TableCell>{h.unidad ?? '—'}</TableCell>
                {canAdmin ? (
                  <TableCell className="text-right">
                    <DropdownMenu>
                      <DropdownMenuTrigger
                        render={
                          <Button variant="ghost" size="sm">
                            Acciones
                          </Button>
                        }
                      />
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => setDialog({ kind: 'editar', hijo: h })}>
                          Editar
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => setDialog({ kind: 'reemplazar', hijo: h })}>
                          Reemplazar
                        </DropdownMenuItem>
                        <DropdownMenuItem variant="destructive" onClick={() => setDialog({ kind: 'eliminar', hijo: h })}>
                          Eliminar
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                ) : null}
              </TableRow>
            ))}
            {hijos.length === 0 ? (
              <TableRow>
                <TableCell colSpan={canAdmin ? 5 : 4} className="h-16 text-center text-muted-foreground">
                  Este ítem no tiene componentes definidos.
                </TableCell>
              </TableRow>
            ) : null}
          </TableBody>
        </Table>
      </div>

      <AgregarComponenteDialog
        open={dialog?.kind === 'agregar'}
        onOpenChange={(o) => setDialog(o ? { kind: 'agregar' } : null)}
        nodo={nodo}
        variantes={variantes}
        ums={ums}
      />
      <EditarComponenteDialog
        open={dialog?.kind === 'editar'}
        onOpenChange={(o) => setDialog(o ? { kind: 'editar', hijo: (dialog as { hijo: TreeRow }).hijo } : null)}
        hijo={dialog?.kind === 'editar' ? dialog.hijo : null}
        ums={ums}
      />
      <ReemplazarComponenteDialog
        open={dialog?.kind === 'reemplazar'}
        onOpenChange={(o) => setDialog(o ? { kind: 'reemplazar', hijo: (dialog as { hijo: TreeRow }).hijo } : null)}
        hijo={dialog?.kind === 'reemplazar' ? dialog.hijo : null}
        nodo={nodo}
        variantes={variantes}
      />
      <EliminarComponenteDialog
        open={dialog?.kind === 'eliminar'}
        onOpenChange={(o) => setDialog(o ? { kind: 'eliminar', hijo: (dialog as { hijo: TreeRow }).hijo } : null)}
        hijo={dialog?.kind === 'eliminar' ? dialog.hijo : null}
      />
    </div>
  )
}