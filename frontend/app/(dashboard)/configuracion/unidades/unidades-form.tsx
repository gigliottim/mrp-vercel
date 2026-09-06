'use client'

import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { TIPOS, TIPO_LABELS } from './unidades-labels'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'



const schema = z.object({
  tipo: z.enum(TIPOS),
  unidad: z.string().min(1).max(50),
  simbolo: z.string().min(1).max(10),
  equivalencia_base: z.coerce.number().positive(),
  es_base: z.boolean().optional(),
  activo: z.boolean().optional(),
})

export type UnidadMedida = {
  id: number
  tipo: string
  unidad: string
  simbolo: string
  equivalencia_base: number
  es_base: boolean
  activo: boolean
  is_system: boolean
  locked: boolean
}




export function UnidadForm({
  initial,
  onSubmit,
  onCancel,
}: {
  initial: UnidadMedida | null
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  onCancel: () => void
}) {
  const { register, handleSubmit, setValue, watch, formState: { errors } } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      tipo: (initial?.tipo ?? 'longitud') as (typeof TIPOS)[number],
      unidad: initial?.unidad ?? '',
      simbolo: initial?.simbolo ?? '',
      equivalencia_base: initial?.equivalencia_base ?? 1,
      es_base: initial?.es_base ?? false,
      activo: initial?.activo ?? true,
    },
  })
  const tipo = watch('tipo')

  return (
    <form
      onSubmit={handleSubmit((values) => onSubmit(values as Record<string, unknown>))}
      className="space-y-4"
    >
      <div className="space-y-2">
        <Label>Tipo</Label>
        <Select
          value={tipo}
          onValueChange={(v) => setValue('tipo', v as (typeof TIPOS)[number])}
        >
          <SelectTrigger>
            <SelectValue placeholder="Tipo" />
          </SelectTrigger>
          <SelectContent>
            {TIPOS.map((t) => (
              <SelectItem key={t} value={t}>
                {TIPO_LABELS[t]}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-2">
        <Label>Unidad</Label>
        <Input {...register('unidad')} placeholder="Metro" />
        {errors.unidad ? <p className="text-sm text-destructive">{errors.unidad.message}</p> : null}
      </div>
      <div className="space-y-2">
        <Label>Símbolo</Label>
        <Input {...register('simbolo')} placeholder="m" />
        {errors.simbolo ? <p className="text-sm text-destructive">{errors.simbolo.message}</p> : null}
      </div>
      <div className="space-y-2">
        <Label>Equivalencia base</Label>
        <Input type="number" step="any" {...register('equivalencia_base')} />
        {errors.equivalencia_base ? (
          <p className="text-sm text-destructive">{errors.equivalencia_base.message}</p>
        ) : null}
      </div>
      <div className="flex items-center gap-2">
        <Switch checked={watch('es_base') ?? false} onCheckedChange={(v) => setValue('es_base', v)} />
        <Label>Es base</Label>
      </div>
      <div className="flex items-center gap-2">
        <Switch checked={watch('activo') ?? true} onCheckedChange={(v) => setValue('activo', v)} />
        <Label>Activo</Label>
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
