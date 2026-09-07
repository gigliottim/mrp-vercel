'use client'

import { useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { cambiarEstado } from '../actions'

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

const ESTADO_LABEL: Record<string, string> = {
  borrador: 'Borrador',
  planificada: 'Planificada',
  liberada: 'Liberada',
  en_proceso: 'En proceso',
  pausada: 'Pausada',
  completada: 'Completada',
  cancelada: 'Cancelada',
  cerrada: 'Cerrada',
}

export function OrdenAcciones({ id, estado }: { id: number; estado: string }) {
  const [busy, setBusy] = useState(false)
  const transiciones = TRANSICIONES[estado] ?? []

  if (transiciones.length === 0) return null

  const handle = async (destino: string) => {
    setBusy(true)
    const res = await cambiarEstado(id, destino)
    setBusy(false)
    if (res.ok) {
      toast.success('Estado actualizado')
    } else {
      toast.error(res.error ?? 'Error')
    }
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={
          <Button variant="outline" size="sm" disabled={busy}>
            Acciones de estado
          </Button>
        }
      />
      <DropdownMenuContent align="end">
        {transiciones.map((dest) => (
          <DropdownMenuItem key={dest} onClick={() => handle(dest)}>
            {ESTADO_LABEL[dest] ?? dest}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}