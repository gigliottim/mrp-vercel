'use client'

import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
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

export type ValidacionRow = {
  id: number
  tipo_deposito_origen_id: number
  tipo_deposito_destino_id: number
  activo: boolean
  observaciones: string | null
}

export type Deposito = {
  id: number
  codigo: string
  nombre: string
}

const schema = z
  .object({
    tipo_deposito_origen_id: z.coerce.number().int().positive(),
    tipo_deposito_destino_id: z.coerce.number().int().positive(),
    activo: z.boolean().optional(),
    observaciones: z.string().optional(),
  })
  .refine((v) => v.tipo_deposito_origen_id !== v.tipo_deposito_destino_id, {
    message: 'origen y destino deben diferir',
    path: ['tipo_deposito_destino_id'],
  })

export function ValidacionForm({
  initial,
  depositos = [],
  onSubmit,
  onCancel,
}: {
  initial: ValidacionRow | null
  depositos?: Deposito[]
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  onCancel: () => void
}) {
  const { register, handleSubmit, setValue, watch, formState: { errors } } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      tipo_deposito_origen_id: initial?.tipo_deposito_origen_id ?? 0,
      tipo_deposito_destino_id: initial?.tipo_deposito_destino_id ?? 0,
      activo: initial?.activo ?? true,
      observaciones: initial?.observaciones ?? '',
    },
  })

  const nombreDeposito = (id: number) =>
    depositos.find((d) => d.id === id)?.nombre ?? `#${id}`

  return (
    <form
      onSubmit={handleSubmit((values) => onSubmit(values as Record<string, unknown>))}
      className="space-y-4"
    >
      <div className="space-y-2">
        <Label>Origen</Label>
        <Select
          value={String(watch('tipo_deposito_origen_id') ?? 0)}
          onValueChange={(v) => setValue('tipo_deposito_origen_id', Number(v))}
        >
          <SelectTrigger>
            <SelectValue placeholder="Depósito origen" />
          </SelectTrigger>
          <SelectContent>
            {depositos.map((d) => (
              <SelectItem key={d.id} value={String(d.id)}>
                {d.nombre} ({d.codigo})
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-2">
        <Label>Destino</Label>
        <Select
          value={String(watch('tipo_deposito_destino_id') ?? 0)}
          onValueChange={(v) => setValue('tipo_deposito_destino_id', Number(v))}
        >
          <SelectTrigger>
            <SelectValue placeholder="Depósito destino" />
          </SelectTrigger>
          <SelectContent>
            {depositos.map((d) => (
              <SelectItem key={d.id} value={String(d.id)}>
                {d.nombre} ({d.codigo})
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        {errors.tipo_deposito_destino_id ? (
          <p className="text-sm text-destructive">{errors.tipo_deposito_destino_id.message}</p>
        ) : null}
      </div>
      <div className="space-y-2">
        <Label>Observaciones</Label>
        <Textarea {...register('observaciones')} />
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
