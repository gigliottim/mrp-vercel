'use client'

import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

export type TiposDepositosRow = {
  id: number
  codigo: string
  nombre: string
  descripcion: string
  orden: number
  es_sistema: boolean
  activo: boolean
}

const schema = z.object({
  codigo: z.string().optional(),
  nombre: z.string().optional(),
  descripcion: z.string().optional(),
  orden: z.coerce.number().optional(),
  es_sistema: z.boolean().optional(),
  activo: z.boolean().optional(),
})

export function TiposDepositosForm({
  initial,
  onSubmit,
  onCancel,
}: {
  initial: TiposDepositosRow | null
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  onCancel: () => void
}) {
  const { register, handleSubmit, setValue, watch, formState: { errors } } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      codigo: initial?.codigo ?? '',
      nombre: initial?.nombre ?? '',
      descripcion: initial?.descripcion ?? '',
      orden: initial?.orden ?? 0,
      es_sistema: initial?.es_sistema ?? false,
      activo: initial?.activo ?? false,
    },
  })

  return (
    <form
      onSubmit={handleSubmit((values) => onSubmit(values as Record<string, unknown>))}
      className="space-y-4"
    >
      <div className="space-y-2">
        <Label>Código</Label>
        <Input type="text" {...register('codigo')} />
      </div>
      <div className="space-y-2">
        <Label>Nombre</Label>
        <Input type="text" {...register('nombre')} />
      </div>
      <div className="space-y-2">
        <Label>Descripción</Label>
        <Textarea {...register('descripcion')} />
      </div>
      <div className="space-y-2">
        <Label>Orden</Label>
        <Input type="number" {...register('orden')} />
      </div>
      <div className="flex items-center gap-2">
        <Switch checked={watch('es_sistema') ?? false} onCheckedChange={(v) => setValue('es_sistema', v)} />
        <Label>Es sistema</Label>
      </div>
      <div className="flex items-center gap-2">
        <Switch checked={watch('activo') ?? false} onCheckedChange={(v) => setValue('activo', v)} />
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
