'use client'

import { useState } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { Plus } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { toast } from 'sonner'
import { DataTable, type Column } from './data-table'

export type CrudPageProps<T extends Record<string, unknown>> = {
  title: string
  description?: string
  columns: Column<T>[]
  data: T[]
  page: number
  perPage: number
  total: number
  canCreate: boolean
  createLabel?: string
  FormComponent: React.ComponentType<{
    initial: T | null
    onSubmit: (data: Record<string, unknown>) => Promise<void>
    onCancel: () => void
  }>
  /** Props extra serializables (opciones de selects, etc.) para FormComponent */
  formExtraProps?: Record<string, unknown>
  onCreate: (data: Record<string, unknown>) => Promise<{ ok?: boolean; error?: string }>
  onUpdate: (id: number, data: Record<string, unknown>) => Promise<{ ok?: boolean; error?: string }>
  onDelete: (id: number) => Promise<{ ok?: boolean; error?: string }>
  idField?: string
}

export function CrudPage<T extends Record<string, unknown>>({
  title,
  description,
  columns,
  data,
  page,
  perPage,
  total,
  canCreate,
  createLabel = 'Nuevo',
  FormComponent,
  formExtraProps,
  onCreate,
  onUpdate,
  onDelete,
  idField = 'id',
}: CrudPageProps<T>) {
  const router = useRouter()
  const searchParams = useSearchParams()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<T | null>(null)
  const [deleting, setDeleting] = useState<T | null>(null)
  const [busy, setBusy] = useState(false)

  const idOf = (row: T) => Number(row[idField])

  const handleSubmit = async (data: Record<string, unknown>) => {
    setBusy(true)
    const res = editing ? await onUpdate(idOf(editing), data) : await onCreate(data)
    setBusy(false)
    if (res.ok) {
      toast.success(editing ? 'Actualizado' : 'Creado')
      setDialogOpen(false)
      setEditing(null)
      router.refresh()
    } else {
      toast.error(res.error ?? 'Error')
    }
  }

  const handleDelete = async () => {
    if (!deleting) return
    setBusy(true)
    const res = await onDelete(idOf(deleting))
    setBusy(false)
    if (res.ok) {
      toast.success('Eliminado')
      setDeleting(null)
      router.refresh()
    } else {
      toast.error(res.error ?? 'Error')
    }
  }

  const goToPage = (p: number) => {
    const params = new URLSearchParams(searchParams.toString())
    params.set('page', String(p))
    router.push(`?${params.toString()}`)
  }

  const actionColumn: Column<T> = {
    key: 'acciones',
    header: 'Acciones',
    render: (row) => (
      <div className="flex gap-2">
        <Button
          variant="ghost"
          size="sm"
          onClick={() => {
            setEditing(row)
            setDialogOpen(true)
          }}
        >
          Editar
        </Button>
        <Button variant="ghost" size="sm" className="text-destructive" onClick={() => setDeleting(row)}>
          Eliminar
        </Button>
      </div>
    ),
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">{title}</h1>
          {description ? <p className="text-muted-foreground text-sm">{description}</p> : null}
        </div>
        {canCreate ? (
          <Button
            onClick={() => {
              setEditing(null)
              setDialogOpen(true)
            }}
          >
            <Plus className="mr-2 h-4 w-4" />
            {createLabel}
          </Button>
        ) : null}
      </div>

      <DataTable
        columns={[...columns, actionColumn]}
        data={data}
        page={page}
        perPage={perPage}
        total={total}
        onPageChange={goToPage}
      />

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Editar' : createLabel}</DialogTitle>
          </DialogHeader>
          <FormComponent
            initial={editing}
            onSubmit={handleSubmit}
            onCancel={() => {
              setDialogOpen(false)
              setEditing(null)
            }}
            {...formExtraProps}
          />
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>¿Eliminar registro?</AlertDialogTitle>
            <AlertDialogDescription>
              Esta acción no se puede deshacer.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={busy}>Cancelar</AlertDialogCancel>
            <AlertDialogAction disabled={busy} onClick={handleDelete}>
              Eliminar
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
