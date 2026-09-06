'use client'

import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
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
import type { ActionResult } from './actions'

export type ConfiguracionGeneral = {
  id: number
  company_id: number
  decimal_places: number
  rounding_mode: string
  thousand_separator: string
  decimal_separator: string
  date_format: string
  time_format: string
}

const schema = z
  .object({
    decimal_places: z.coerce.number().int().min(1).max(10),
    rounding_mode: z.enum(['half_up', 'half_down', 'half_even', 'truncate']),
    thousand_separator: z.string().length(1),
    decimal_separator: z.string().length(1),
    date_format: z.string().max(20),
    time_format: z.string().max(20),
  })
  .refine((v) => v.thousand_separator !== v.decimal_separator, {
    message: 'separadores deben diferir',
    path: ['decimal_separator'],
  })

const ROUNDING_LABELS: Record<string, string> = {
  half_up: 'Redondeo estándar (mitad arriba)',
  half_down: 'Mitad abajo',
  half_even: 'Banquero (mitad par)',
  truncate: 'Truncar',
}

export function ConfiguracionForm({
  initial,
  onSubmit,
}: {
  initial: ConfiguracionGeneral
  onSubmit: (data: Record<string, unknown>) => Promise<ActionResult>
}) {
  const { register, handleSubmit, setValue, watch, formState: { errors } } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      decimal_places: initial.decimal_places,
      rounding_mode: initial.rounding_mode as 'half_up' | 'half_down' | 'half_even' | 'truncate',
      thousand_separator: initial.thousand_separator,
      decimal_separator: initial.decimal_separator,
      date_format: initial.date_format,
      time_format: initial.time_format,
    },
  })

  const submit = async (values: Record<string, unknown>) => {
    const res = await onSubmit(values)
    if (res.ok) toast.success('Configuración actualizada')
    else toast.error(res.error ?? 'Error')
  }

  return (
    <form
      onSubmit={handleSubmit((values) => submit(values as Record<string, unknown>))}
      className="max-w-lg space-y-4"
    >
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>Decimales</Label>
          <Input type="number" {...register('decimal_places')} />
        </div>
        <div className="space-y-2">
          <Label>Modo de redondeo</Label>
          <Select
            value={watch('rounding_mode')}
            onValueChange={(v) =>
              setValue('rounding_mode', v as 'half_up' | 'half_down' | 'half_even' | 'truncate')
            }
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {Object.entries(ROUNDING_LABELS).map(([k, label]) => (
                <SelectItem key={k} value={k}>
                  {label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label>Separador de miles</Label>
          <Input {...register('thousand_separator')} maxLength={1} className="w-20" />
        </div>
        <div className="space-y-2">
          <Label>Separador decimal</Label>
          <Input {...register('decimal_separator')} maxLength={1} className="w-20" />
        </div>
        {errors.decimal_separator ? (
          <p className="text-sm text-destructive col-span-2">{errors.decimal_separator.message}</p>
        ) : null}
        <div className="space-y-2">
          <Label>Formato de fecha</Label>
          <Input {...register('date_format')} placeholder="d/m/Y" />
        </div>
        <div className="space-y-2">
          <Label>Formato de hora</Label>
          <Input {...register('time_format')} placeholder="H:i" />
        </div>
      </div>
      <div className="flex justify-end">
        <Button type="submit">Guardar</Button>
      </div>
    </form>
  )
}
