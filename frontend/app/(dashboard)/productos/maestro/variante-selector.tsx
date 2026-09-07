'use client'

import { useMemo, useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import type { VarianteOpt } from './maestro-client'

export function VarianteSelector({
  variantes,
  tipos,
  selectedId,
}: {
  variantes: VarianteOpt[]
  tipos: { codigo: string; nombre: string }[]
  selectedId: number | null
}) {
  const router = useRouter()
  const [query, setQuery] = useState('')
  const [tipoFilter, setTipoFilter] = useState('')
  const [open, setOpen] = useState(false)

  const seleccionada = variantes.find((v) => v.id === selectedId) ?? null

  const filtradas = useMemo(() => {
    const q = query.trim().toLowerCase()
    return variantes.filter((v) => {
      if (tipoFilter && v.tipo_codigo !== tipoFilter) return false
      if (!q) return true
      return (
        v.codigo_variante.toLowerCase().includes(q) ||
        (v.detalle ?? '').toLowerCase().includes(q) ||
        (v.parte_codigo ?? '').toLowerCase().includes(q)
      )
    })
  }, [variantes, query, tipoFilter])

  const handleSelect = (id: number) => {
    setOpen(false)
    setQuery('')
    router.push(`/productos/maestro?id_variante=${id}`)
  }

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap gap-2">
        <Button
          type="button"
          variant={tipoFilter === '' ? 'secondary' : 'outline'}
          size="sm"
          onClick={() => setTipoFilter('')}
        >
          Todos
        </Button>
        {tipos.map((t) => (
          <Button
            key={t.codigo}
            type="button"
            variant={tipoFilter === t.codigo ? 'secondary' : 'outline'}
            size="sm"
            title={t.nombre}
            onClick={() => setTipoFilter(tipoFilter === t.codigo ? '' : t.codigo)}
          >
            {t.codigo}
          </Button>
        ))}
      </div>
      <div className="relative w-full max-w-xl">
        <Input
          value={seleccionada && !open ? `${seleccionada.parte_codigo ?? ''} / ${seleccionada.codigo_variante}` : query}
          onChange={(e) => {
            setQuery(e.target.value)
            setOpen(true)
          }}
          onFocus={() => {
            setOpen(true)
            setQuery('')
          }}
          placeholder="Buscar producto maestro por código o descripción..."
        />
        {open ? (
          <div className="absolute top-full left-0 right-0 z-50 mt-1 max-h-80 overflow-y-auto rounded-md border bg-background shadow-md">
            {filtradas.length === 0 ? (
              <div className="p-3 text-sm text-muted-foreground">Sin resultados</div>
            ) : (
              filtradas.map((v) => (
                <button
                  key={v.id}
                  type="button"
                  className={cn(
                    'flex w-full flex-col items-start gap-0.5 border-b px-3 py-2 text-left text-sm hover:bg-muted',
                    v.id === selectedId && 'bg-muted font-medium'
                  )}
                  onClick={() => handleSelect(v.id)}
                >
                  <span className="font-medium">
                    {v.parte_codigo ? `${v.parte_codigo} / ` : ''}
                    {v.codigo_variante}
                  </span>
                  <span className="text-xs text-muted-foreground">{v.detalle}</span>
                </button>
              ))
            )}
          </div>
        ) : null}
      </div>
      {seleccionada ? (
        <p className="text-xs text-muted-foreground">
          Seleccionada: <strong>{seleccionada.codigo_variante}</strong>
          {seleccionada.detalle ? ` — ${seleccionada.detalle}` : ''}
        </p>
      ) : null}
    </div>
  )
}