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

export type EntidadesRow = {
  id: number
  razon_social: string
  tipo: string
  identificacion_tributaria: string
  contacto_email: string
  contacto_telefono: string
  direccion: string
}

const schema = z.object({
  razon_social: z.string().optional(),
  tipo: z.enum(['PROVEEDOR', 'CLIENTE', 'AMBOS']).optional(),
  identificacion_tributaria: z.string().optional(),
  contacto_email: z.string().optional(),
  contacto_telefono: z.string().optional(),
  direccion: z.string().optional(),
})

export function EntidadesForm({
  initial,
  onSubmit,
  onCancel,
}: {
  initial: EntidadesRow | null
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  onCancel: () => void
}) {
  const { register, handleSubmit, setValue, watch, formState: { errors } } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      razon_social: initial?.razon_social ?? '',
      tipo: (initial?.tipo ?? 'PROVEEDOR') as 'PROVEEDOR' | 'CLIENTE' | 'AMBOS',
      identificacion_tributaria: initial?.identificacion_tributaria ?? '',
      contacto_email: initial?.contacto_email ?? '',
      contacto_telefono: initial?.contacto_telefono ?? '',
      direccion: initial?.direccion ?? '',
    },
  })

  return (
    <form
      onSubmit={handleSubmit((values) => onSubmit(values as Record<string, unknown>))}
      className="space-y-4"
    >
      <div className="space-y-2">
        <Label>Razón social</Label>
        <Input type="text" {...register('razon_social')} />
      </div>
      <div className="space-y-2">
        <Label>Tipo</Label>
        <Select value={watch('tipo')} onValueChange={(v) => setValue('tipo', v as 'PROVEEDOR' | 'CLIENTE' | 'AMBOS')}>
          <SelectTrigger><SelectValue placeholder="Tipo" /></SelectTrigger>
          <SelectContent>
            {['PROVEEDOR', 'CLIENTE', 'AMBOS'].map((o) => (
              <SelectItem key={o} value={o}>{o}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-2">
        <Label>Identificación tributaria</Label>
        <Input type="text" {...register('identificacion_tributaria')} />
      </div>
      <div className="space-y-2">
        <Label>Email</Label>
        <Input type="email" {...register('contacto_email')} />
      </div>
      <div className="space-y-2">
        <Label>Teléfono</Label>
        <Input type="text" {...register('contacto_telefono')} />
      </div>
      <div className="space-y-2">
        <Label>Dirección</Label>
        <Textarea {...register('direccion')} />
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
