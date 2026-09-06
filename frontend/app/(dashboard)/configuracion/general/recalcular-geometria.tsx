'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { toast } from 'sonner'

export function RecalcularGeometria({ token }: { token: string }) {
  const [busy, setBusy] = useState(false)
  const [resultado, setResultado] = useState<{ actualizados: number; errores: number } | null>(null)

  const handleRecalcular = async () => {
    if (!confirm('¿Recalcular superficie y volumen de todas las partes con dimensiones completas?')) return
    setBusy(true)
    try {
      const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL ?? 'https://api.mimrp.com.ar'}/api/v1/import-export/geometria/recalcular`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ solo_dimensiones_completas: true }),
      })
      const body = await res.json()
      if (!res.ok) {
        toast.error(body.error?.message ?? 'Error')
      } else {
        setResultado(body.data)
        toast.success(`${body.data.actualizados} partes actualizadas`)
      }
    } catch (e) {
      toast.error((e as Error).message)
    }
    setBusy(false)
  }

  return (
    <div className="flex items-center gap-3">
      <Button variant="outline" onClick={handleRecalcular} disabled={busy}>
        {busy ? 'Recalculando...' : 'Recalcular dimensiones de partes'}
      </Button>
      {resultado ? (
        <p className="text-sm text-muted-foreground">
          {resultado.actualizados} actualizadas · {resultado.errores} errores
        </p>
      ) : null}
    </div>
  )
}
