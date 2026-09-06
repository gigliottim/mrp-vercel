'use client'

import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'

export type CentroRow = {
  id: number
  codigo: string
  nombre: string
  descripcion: string | null
  tipo: string
  capacidad_horas_dia: number
  eficiencia_porcentaje: number
  costo_hora: number
  capacidad_finita: boolean
  activo: boolean
}

const schema = z.object({
  codigo: z.string().min(1).max(20),
  nombre: z.string().min(1).max(100),
  descripcion: z.string().optional(),
  tipo: z.string().max(20).optional(),
  capacidad_horas_dia: z.coerce.number().optional(),
  eficiencia_porcentaje: z.coerce.number().optional(),
  costo_hora: z.coerce.number().optional(),
  capacidad_finita: z.boolean().optional(),
  activo: z.boolean().optional(),
})

export function CentroForm({
  initial,
  onSubmit,
  onCancel,
}: {
  initial: CentroRow | null
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  onCancel: () => void
}) {
  const { register, handleSubmit, setValue, watch, formState: { errors } } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      codigo: initial?.codigo ?? '',
      nombre: initial?.nombre ?? '',
      descripcion: initial?.descripcion ?? '',
      tipo: initial?.tipo ?? 'manual',
      capacidad_horas_dia: initial?.capacidad_horas_dia ?? 8,
      eficiencia_porcentaje: initial?.eficiencia_porcentaje ?? 100,
      costo_hora: initial?.costo_hora ?? 0,
      capacidad_finita: initial?.capacidad_finita ?? false,
      activo: initial?.activo ?? true,
    },
  })

  return (
    <form onSubmit={handleSubmit((v) => onSubmit(v as Record<string, unknown>))} className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>Código</Label>
          <Input {...register('codigo')} />
          {errors.codigo ? <p className="text-sm text-destructive">{errors.codigo.message}</p> : null}
        </div>
        <div className="space-y-2">
          <Label>Nombre</Label>
          <Input {...register('nombre')} />
        </div>
        <div className="space-y-2">
          <Label>Tipo</Label>
          <Input {...register('tipo')} />
        </div>
        <div className="space-y-2">
          <Label>Capacidad (hs/día)</Label>
          <Input type="number" step="any" {...register('capacidad_horas_dia')} />
        </div>
        <div className="space-y-2">
          <Label>Eficiencia (%)</Label>
          <Input type="number" step="any" {...register('eficiencia_porcentaje')} />
        </div>
        <div className="space-y-2">
          <Label>Costo hora</Label>
          <Input type="number" step="any" {...register('costo_hora')} />
        </div>
      </div>
      <div className="space-y-2">
        <Label>Descripción</Label>
        <Textarea {...register('descripcion')} />
      </div>
      <div className="flex items-center gap-2">
        <Switch checked={watch('capacidad_finita') ?? false} onCheckedChange={(v) => setValue('capacidad_finita', v)} />
        <Label>Capacidad finita</Label>
      </div>
      <div className="flex items-center gap-2">
        <Switch checked={watch('activo') ?? true} onCheckedChange={(v) => setValue('activo', v)} />
        <Label>Activo</Label>
      </div>
      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={onCancel}>Cancelar</Button>
        <Button type="submit">Guardar</Button>
      </div>
    </form>
  )
}
