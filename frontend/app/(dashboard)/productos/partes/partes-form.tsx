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

export type ParteRow = {
  id: number
  codigo: string
  id_tipo: number
  id_grupo: number
  detalle: string
  largo_alto: number | null
  ancho: number | null
  espesor_profundidad: number | null
  activo: boolean
}

export type TipoParte = { id: number; codigo: string; nombre: string }
export type GrupoParte = { id: number; codigo: string; nombre: string }
export type UnidadMedidaOpt = { id: number; unidad: string; simbolo: string }

const schema = z.object({
  codigo: z.string().min(1).max(50),
  id_tipo: z.coerce.number().int().positive(),
  id_grupo: z.coerce.number().int().positive(),
  detalle: z.string().min(1).max(255),
  largo_alto: z.coerce.number().optional(),
  ancho: z.coerce.number().optional(),
  espesor_profundidad: z.coerce.number().optional(),
  activo: z.boolean().optional(),
})

export function ParteForm({
  initial,
  tipos,
  grupos,
  onSubmit,
  onCancel,
}: {
  initial: ParteRow | null
  tipos: TipoParte[]
  grupos: GrupoParte[]
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  onCancel: () => void
}) {
  const { register, handleSubmit, setValue, watch, formState: { errors } } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      codigo: initial?.codigo ?? '',
      id_tipo: initial?.id_tipo ?? 0,
      id_grupo: initial?.id_grupo ?? 0,
      detalle: initial?.detalle ?? '',
      largo_alto: initial?.largo_alto ?? undefined,
      ancho: initial?.ancho ?? undefined,
      espesor_profundidad: initial?.espesor_profundidad ?? undefined,
      activo: initial?.activo ?? true,
    },
  })

  return (
    <form
      onSubmit={handleSubmit((values) => onSubmit(values as Record<string, unknown>))}
      className="space-y-4"
    >
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>Código</Label>
          <Input {...register('codigo')} placeholder="P-001" />
          {errors.codigo ? <p className="text-sm text-destructive">{errors.codigo.message}</p> : null}
        </div>
        <div className="space-y-2">
          <Label>Detalle</Label>
          <Input {...register('detalle')} placeholder="Descripción de la parte" />
          {errors.detalle ? <p className="text-sm text-destructive">{errors.detalle.message}</p> : null}
        </div>
        <div className="space-y-2">
          <Label>Tipo</Label>
          <Select
            value={String(watch('id_tipo') ?? 0)}
            onValueChange={(v) => setValue('id_tipo', Number(v))}
          >
            <SelectTrigger>
              <SelectValue placeholder="Tipo de parte" />
            </SelectTrigger>
            <SelectContent>
              {tipos.map((t) => (
                <SelectItem key={t.id} value={String(t.id)}>
                  {t.nombre} ({t.codigo})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label>Grupo</Label>
          <Select
            value={String(watch('id_grupo') ?? 0)}
            onValueChange={(v) => setValue('id_grupo', Number(v))}
          >
            <SelectTrigger>
              <SelectValue placeholder="Grupo de partes" />
            </SelectTrigger>
            <SelectContent>
              {grupos.map((g) => (
                <SelectItem key={g.id} value={String(g.id)}>
                  {g.nombre} ({g.codigo})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label>Largo / Alto</Label>
          <Input type="number" step="any" {...register('largo_alto')} />
        </div>
        <div className="space-y-2">
          <Label>Ancho</Label>
          <Input type="number" step="any" {...register('ancho')} />
        </div>
        <div className="space-y-2">
          <Label>Espesor / Profundidad</Label>
          <Input type="number" step="any" {...register('espesor_profundidad')} />
        </div>
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
