'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { toast } from 'sonner'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { cambiarEstado } from './actions'

export type OrdenRow = {
  id: number
  numero_orden: string
  variante_id: number
  cantidad_planificada: number
  cantidad_producida: number
  estado: string
  prioridad: string
  fecha_inicio_programada: string
  fecha_fin_programada: string
}

const ESTADO_VARIANT: Record<string, string> = {
  borrador: 'secondary',
  planificada: 'outline',
  liberada: 'default',
  en_proceso: 'default',
  pausada: 'secondary',
  completada: 'outline',
  cancelada: 'destructive',
  cerrada: 'outline',
}

const TRANSICIONES: Record<string, string[]> = {
  borrador: ['planificada', 'cancelada'],
  planificada: ['liberada', 'cancelada'],
  liberada: ['en_proceso', 'cancelada'],
  en_proceso: ['pausada', 'completada', 'cancelada'],
  pausada: ['en_proceso', 'cancelada'],
  completada: ['cerrada'],
  cancelada: [],
  cerrada: [],
}

export function OrdenesTable({ ordenes }: { ordenes: OrdenRow[] }) {
  const router = useRouter()
  const [busyId, setBusyId] = useState<number | null>(null)

  const handleEstado = async (id: number, estado: string) => {
    setBusyId(id)
    const res = await cambiarEstado(id, estado)
    setBusyId(null)
    if (res.ok) {
      toast.success(`Estado → ${estado}`)
      router.refresh()
    } else {
      toast.error(res.error ?? 'Error')
    }
  }

  return (
    <div className="rounded-md border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Nº orden</TableHead>
            <TableHead>Variante</TableHead>
            <TableHead className="text-right">Planificada</TableHead>
            <TableHead className="text-right">Producida</TableHead>
            <TableHead>Estado</TableHead>
            <TableHead>Prioridad</TableHead>
            <TableHead>Acciones</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {ordenes.length === 0 ? (
            <TableRow>
              <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                Sin órdenes
              </TableCell>
            </TableRow>
          ) : (
            ordenes.map((o) => (
              <TableRow key={o.id}>
                <TableCell className="font-mono text-sm">{o.numero_orden}</TableCell>
                <TableCell>#{o.variante_id}</TableCell>
                <TableCell className="text-right">{o.cantidad_planificada}</TableCell>
                <TableCell className="text-right">{o.cantidad_producida}</TableCell>
                <TableCell>
                  <Badge variant={(ESTADO_VARIANT[o.estado] ?? 'outline') as 'outline'}>
                    {o.estado}
                  </Badge>
                </TableCell>
                <TableCell>{o.prioridad}</TableCell>
                <TableCell>
                  <div className="flex gap-1">
                    {(TRANSICIONES[o.estado] ?? []).map((dest) => (
                      <Button
                        key={dest}
                        variant="outline"
                        size="sm"
                        disabled={busyId === o.id}
                        onClick={() => handleEstado(o.id, dest)}
                      >
                        {dest}
                      </Button>
                    ))}
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </div>
  )
}
