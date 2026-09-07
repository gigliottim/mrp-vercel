'use client'

import { useEffect } from 'react'
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
  id_um_largo_alto: number | null
  ancho: number | null
  id_um_ancho: number | null
  espesor_profundidad: number | null
  id_um_espesor: number | null
  superficie: number | null
  id_um_superficie: number | null
  volumen: number | null
  id_um_volumen: number | null
  id_um_compra: number | null
  id_um_uso: number | null
  factor_conversion: number | null
  activo: boolean
}

export type TipoParte = { id: number; codigo: string; nombre: string }
export type GrupoParte = { id: number; codigo: string; nombre: string }
export type UmOpt = { id: number; unidad: string; simbolo: string; tipo: string }

const schema = z
  .object({
    codigo: z.string().min(1).max(50),
    id_tipo: z.coerce.number().int().positive(),
    id_grupo: z.coerce.number().int().positive(),
    detalle: z.string().min(1).max(255),
    largo_alto: z.coerce.number().optional(),
    id_um_largo_alto: z.coerce.number().optional(),
    ancho: z.coerce.number().optional(),
    id_um_ancho: z.coerce.number().optional(),
    espesor_profundidad: z.coerce.number().optional(),
    id_um_espesor: z.coerce.number().optional(),
    superficie: z.coerce.number().optional(),
    id_um_superficie: z.coerce.number().optional(),
    volumen: z.coerce.number().optional(),
    id_um_volumen: z.coerce.number().optional(),
    activo: z.boolean().optional(),
    id_um_compra: z.coerce.number().optional(),
    id_um_uso: z.coerce.number().optional(),
    factor_conversion: z.coerce.number().optional(),
  })
  .superRefine((data, ctx) => {
    // Paridad con validatePart() del PHP: factor requerido si compra != uso
    if (
      data.id_um_compra &&
      data.id_um_uso &&
      data.id_um_compra !== data.id_um_uso &&
      (!data.factor_conversion || data.factor_conversion <= 0)
    ) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['factor_conversion'],
        message: 'El factor de conversión es requerido cuando las unidades son diferentes.',
      })
    }
  })

export function ParteForm({
  initial,
  tipos = [],
  grupos = [],
  unidades = [],
  onSubmit,
  onCancel,
}: {
  initial: ParteRow | null
  tipos?: TipoParte[]
  grupos?: GrupoParte[]
  unidades?: UmOpt[]
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  onCancel: () => void
}) {
  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      codigo: initial?.codigo ?? '',
      id_tipo: initial?.id_tipo ?? 0,
      id_grupo: initial?.id_grupo ?? 0,
      detalle: initial?.detalle ?? '',
      largo_alto: initial?.largo_alto ?? undefined,
      id_um_largo_alto: initial?.id_um_largo_alto ?? undefined,
      ancho: initial?.ancho ?? undefined,
      id_um_ancho: initial?.id_um_ancho ?? undefined,
      espesor_profundidad: initial?.espesor_profundidad ?? undefined,
      id_um_espesor: initial?.id_um_espesor ?? undefined,
      superficie: initial?.superficie ?? undefined,
      id_um_superficie: initial?.id_um_superficie ?? undefined,
      volumen: initial?.volumen ?? undefined,
      id_um_volumen: initial?.id_um_volumen ?? undefined,
      activo: initial?.activo ?? true,
      id_um_compra: initial?.id_um_compra ?? undefined,
      id_um_uso: initial?.id_um_uso ?? undefined,
      factor_conversion: initial?.factor_conversion ?? undefined,
    },
  })

  const umCompra = watch('id_um_compra')
  const umUso = watch('id_um_uso')
  const unidadesDistintas = Boolean(umCompra && umUso && umCompra !== umUso)

  // Factor forzado a 1 si las unidades son iguales (paridad con el PHP)
  useEffect(() => {
    if (umCompra && umUso && umCompra === umUso) {
      setValue('factor_conversion', 1)
    }
  }, [umCompra, umUso, setValue])

  const umsPor = (tipo: string) => unidades.filter((u) => u.tipo === tipo)
  const umSelect = (
    name:
      | 'id_um_largo_alto'
      | 'id_um_ancho'
      | 'id_um_espesor'
      | 'id_um_superficie'
      | 'id_um_volumen'
      | 'id_um_compra'
      | 'id_um_uso',
    lista: UmOpt[]
  ) => (
    <Select
      value={String(watch(name) ?? '')}
      onValueChange={(v) => setValue(name, v ? Number(v) : undefined)}
    >
      <SelectTrigger>
        <SelectValue placeholder="UM" />
      </SelectTrigger>
      <SelectContent>
        {lista.map((u) => (
          <SelectItem key={u.id} value={String(u.id)}>
            {u.simbolo}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )

  return (
    <form
      onSubmit={handleSubmit((values) =>
        onSubmit({
          ...values,
          codigo: String(values.codigo ?? '').toUpperCase(),
          // factor 1 explícito cuando unidades iguales
          factor_conversion: unidadesDistintas ? values.factor_conversion : 1,
        } as Record<string, unknown>)
      )}
      className="space-y-4"
    >
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>Código</Label>
          <Input {...register('codigo')} placeholder="P-001" style={{ textTransform: 'uppercase' }} />
          {errors.codigo ? <p className="text-sm text-destructive">{errors.codigo.message}</p> : null}
        </div>
        <div className="space-y-2">
          <Label>Detalle</Label>
          <Input {...register('detalle')} placeholder="Descripción de la parte" />
          {errors.detalle ? <p className="text-sm text-destructive">{errors.detalle.message}</p> : null}
        </div>
        <div className="space-y-2">
          <Label>Tipo</Label>
          <Select value={String(watch('id_tipo') ?? 0)} onValueChange={(v) => setValue('id_tipo', Number(v))}>
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
          {errors.id_tipo ? <p className="text-sm text-destructive">{errors.id_tipo.message}</p> : null}
        </div>
        <div className="space-y-2">
          <Label>Grupo</Label>
          <Select value={String(watch('id_grupo') ?? 0)} onValueChange={(v) => setValue('id_grupo', Number(v))}>
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
          {errors.id_grupo ? <p className="text-sm text-destructive">{errors.id_grupo.message}</p> : null}
        </div>
      </div>

      <div className="space-y-2">
        <Label>Detalle extendido</Label>
        <Textarea {...register('detalle')} rows={2} placeholder="Descripción de la parte" />
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="space-y-2">
          <Label>UM Compra</Label>
          {umSelect('id_um_compra', unidades)}
        </div>
        <div className="space-y-2">
          <Label>UM Uso</Label>
          {umSelect('id_um_uso', unidades)}
        </div>
        {unidadesDistintas ? (
          <div className="space-y-2">
            <Label>Factor de conversión (1 compra = ? uso)</Label>
            <Input type="number" step="any" min="0" {...register('factor_conversion')} />
            {errors.factor_conversion ? (
              <p className="text-sm text-destructive">{errors.factor_conversion.message}</p>
            ) : null}
          </div>
        ) : null}
      </div>

      <div className="grid grid-cols-5 gap-4">
        <div className="space-y-2">
          <Label>Largo / Alto</Label>
          <Input type="number" step="any" {...register('largo_alto')} />
          {umSelect('id_um_largo_alto', umsPor('longitud'))}
        </div>
        <div className="space-y-2">
          <Label>Ancho</Label>
          <Input type="number" step="any" {...register('ancho')} />
          {umSelect('id_um_ancho', umsPor('longitud'))}
        </div>
        <div className="space-y-2">
          <Label>Espesor / Prof.</Label>
          <Input type="number" step="any" {...register('espesor_profundidad')} />
          {umSelect('id_um_espesor', umsPor('longitud'))}
        </div>
        <div className="space-y-2">
          <Label>Superficie</Label>
          <Input type="number" step="any" {...register('superficie')} />
          {umSelect('id_um_superficie', umsPor('superficie'))}
        </div>
        <div className="space-y-2">
          <Label>Volumen</Label>
          <Input type="number" step="any" {...register('volumen')} />
          {umSelect('id_um_volumen', umsPor('volumen'))}
        </div>
      </div>

      <div className="flex items-center gap-2">
        <Switch checked={watch('activo') ?? true} onCheckedChange={(v) => setValue('activo', v)} />
        <Label>Activo</Label>
      </div>
      <p className="text-xs text-muted-foreground">
        Al crear la parte se genera automáticamente una variante base con el mismo código.
      </p>
      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancelar
        </Button>
        <Button type="submit">Guardar</Button>
      </div>
    </form>
  )
}