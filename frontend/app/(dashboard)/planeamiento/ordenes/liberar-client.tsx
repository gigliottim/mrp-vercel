'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { cambiarEstado } from '@/app/(dashboard)/produccion/ordenes/actions'

export function AccionLiberar({ id }: { id: number }) {
  const router = useRouter()
  const [busy, setBusy] = useState(false)

  const liberar = async () => {
    setBusy(true)
    const res = await cambiarEstado(id, 'liberada')
    setBusy(false)
    if (res.ok) {
      toast.success('Orden liberada')
      router.refresh()
    } else {
      toast.error(res.error ?? 'Error')
    }
  }

  return (
    <Button variant="outline" size="sm" onClick={liberar} disabled={busy}>
      {busy ? '...' : 'Liberar'}
    </Button>
  )
}