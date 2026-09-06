'use client'

import { useState } from 'react'
import { useSession } from '@/lib/use-session'
import { Button } from '@/components/ui/button'
import { toast } from 'sonner'
import { FileDown } from 'lucide-react'

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'https://api.mimrp.com.ar'

export function ExportButtons({ path, filename }: { path: string; filename: string }) {
  const { session } = useSession()
  const [busy, setBusy] = useState(false)

  const descargar = async (formato: 'xlsx' | 'pdf') => {
    if (!session) {
      toast.error('Sesión no disponible')
      return
    }
    setBusy(true)
    try {
      const sep = path.includes('?') ? '&' : '?'
      const url = `${API_URL}${path}${sep}formato=${formato}`
      const res = await fetch(url, {
        headers: { Authorization: `Bearer ${session.accessToken}` },
      })
      if (!res.ok) {
        toast.error('Error al exportar')
        return
      }
      const blob = await res.blob()
      const a = document.createElement('a')
      a.href = URL.createObjectURL(blob)
      a.download = `${filename}.${formato}`
      a.click()
      URL.revokeObjectURL(a.href)
      toast.success(`Exportado ${formato.toUpperCase()}`)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="flex gap-2">
      <Button type="button" variant="outline" size="sm" onClick={() => descargar('xlsx')} disabled={busy}>
        <FileDown data-icon="inline-start" /> Excel
      </Button>
      <Button type="button" variant="outline" size="sm" onClick={() => descargar('pdf')} disabled={busy}>
        <FileDown data-icon="inline-start" /> PDF
      </Button>
    </div>
  )
}
