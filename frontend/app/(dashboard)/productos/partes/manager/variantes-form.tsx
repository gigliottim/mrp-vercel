'use client'

import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

export type VarianteRow = {
  id: number
  id_parte: number
  codigo_variante: string
  detalle: string
  estado: string
  stock_actual: number
  punto_pedido: number
  stock_seguridad: number
  lote_minimo: number
  anticipo_compra: number
  lead_time_produccion: number
  costo: number
}

export type ParteOpt = { id: number; codigo: string; detalle: string }

const ESTADOS = ['activa', 'desarrollo', 'obsoleta', 'descontinuada'] as const

const schema = z.object({
  id_parte: z.coerce.number().int().positive(),
  codigo_variante: z.string().min(1).max(50),
  detalle: z.string().min(1).max(255),
  estado: z.enum(ESTADOS).optional(),
  stock_seguridad: z.coerce.number().optional(),
  punto_pedido: z.coerce.number().optional(),
  lote_minimo: z.coerce.number().optional(),
  anticipo_compra: z.coerce.number().int().optional(),
  lead_time_produccion: z.coerce.number().int().optional(),
  costo: z.coerce.number().optional(),
})

export function VarianteForm({
  initial,
  partes,
  onSubmit,
  onCancel,
}: {
  initial: VarianteRow | null
  partes: ParteOpt[]
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  onCancel: () => void
}) {
  const { register, handleSubmit, setValue, watch, formState: { errors } } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      id_parte: initial?.id_parte ?? 0,
      codigo_variante: initial?.codigo_variante ?? '',
      detalle: initial?.detalle ?? '',
      estado: (initial?.estado ?? 'activa') as (typeof ESTADOS)[number],
      stock_seguridad: initial?.stock_seguridad ?? 0,
      punto_pedido: initial?.punto_pedido ?? 0,
      lote_minimo: initial?.lote_minimo ?? 1,
      anticipo_compra: initial?.anticipo_compra ?? 0,
      lead_time_produccion: initial?.lead_time_produccion ?? 0,
      costo: initial?.costo ?? 0,
    },
  })

  return (
    <form
      onSubmit={handleSubmit((values) => onSubmit(values as Record<string, unknown>))}
      className="space-y-4"
    >
      <div className="space-y-2">
        <Label>Parte</Label>
        <Select
          value={String(watch('id_parte') ?? 0)}
          onValueChange={(v) => setValue('id_parte', Number(v))}
        >
          <SelectTrigger>
            <SelectValue placeholder="Parte" />
          </SelectTrigger>
          <SelectContent>
            {partes.map((p) => (
              <SelectItem key={p.id} value={String(p.id)}>
                {p.codigo} — {p.detalle}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>Código variante</Label>
          <Input {...register('codigo_variante')} />
          {errors.codigo_variante ? (
            <p className="text-sm text-destructive">{errors.codigo_variante.message}</p>
          ) : null}
        </div>
        <div className="space-y-2">
          <Label>Detalle</Label>
          <Input {...register('detalle')} />
        </div>
        <div className="space-y-2">
          <Label>Estado</Label>
          <Select
            value={watch('estado')}
            onValueChange={(v) => setValue('estado', v as (typeof ESTADOS)[number])}
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {ESTADOS.map((e) => (
                <SelectItem key={e} value={e}>
                  {e}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label>Stock seguridad</Label>
          <Input type="number" step="any" {...register('stock_seguridad')} />
        </div>
        <div className="space-y-2">
          <Label>Punto de pedido</Label>
          <Input type="number" step="any" {...register('punto_pedido')} />
        </div>
        <div className="space-y-2">
          <Label>Lote mínimo</Label>
          <Input type="number" step="any" {...register('lote_minimo')} />
        </div>
        <div className="space-y-2">
          <Label>Anticipo compra (días)</Label>
          <Input type="number" {...register('anticipo_compra')} />
        </div>
        <div className="space-y-2">
          <Label>Lead time producción (días)</Label>
          <Input type="number" {...register('lead_time_produccion')} />
        </div>
        <div className="space-y-2">
          <Label>Costo</Label>
          <Input type="number" step="any" {...register('costo')} />
        </div>
      </div>
      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancelar
        </Button>
        <Button type="submit">Guardar</Button>
      </div>
    </form>
  )
}
