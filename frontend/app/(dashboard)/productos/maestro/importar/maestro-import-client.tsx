'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { toast } from 'sonner'

type RowResult = { line: number; status: string; message: string }
type Report = {
  total_rows: number; ok_rows: number; error_rows: number;
  skipped_rows: number; created_links: number;
  rows: RowResult[]; fatal_error: string | null;
}

export function MaestroImportClient({ token }: { token: string }) {
  const [file, setFile] = useState<File | null>(null)
  const [busy, setBusy] = useState(false)
  const [report, setReport] = useState<Report | null>(null)
  const [error, setError] = useState('')

  const handleImport = async () => {
    setError('')
    setReport(null)
    if (!file) return setError('Seleccioná un archivo CSV')
    setBusy(true)
    const form = new FormData()
    form.append('archivo', file)
    try {
      const res = await fetch('/api/v1/import-export/maestro/import', {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}` },
        body: form,
      })
      const body = await res.json()
      setReport(body.data)
      if (body.data?.created_links > 0) toast.success(`${body.data.created_links} vínculos creados`)
    } catch (e) {
      setError((e as Error).message)
    }
    setBusy(false)
  }

  const statusBadge = (s: string) =>
    s === 'ok' ? 'bg-emerald-100 text-emerald-800' : s === 'skip' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800'

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-4">
        <input
          type="file"
          accept=".csv,text/csv"
          onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          className="text-sm"
        />
        <Button onClick={handleImport} disabled={busy || !file}>
          {busy ? 'Importando...' : 'Importar CSV'}
        </Button>
        <a
          href="#"
          className="text-sm text-primary underline"
          onClick={(e) => {
            e.preventDefault()
            fetch('/api/v1/import-export/maestro/template', { headers: { Authorization: `Bearer ${token}` } })
              .then((r) => r.blob())
              .then((b) => {
                const url = URL.createObjectURL(b)
                const a = document.createElement('a')
                a.href = url
                a.download = 'plantilla_import_maestro.csv'
                a.click()
                URL.revokeObjectURL(url)
              })
          }}
        >
          Descargar plantilla
        </a>
        <a
          href="#"
          className="text-sm text-primary underline"
          onClick={(e) => {
            e.preventDefault()
            fetch('/api/v1/import-export/maestro/export', { headers: { Authorization: `Bearer ${token}` } })
              .then((r) => r.blob())
              .then((b) => {
                const url = URL.createObjectURL(b)
                const a = document.createElement('a')
                a.href = url
                a.download = 'export_maestro.csv'
                a.click()
                URL.revokeObjectURL(url)
              })
          }}
        >
          Exportar CSV
        </a>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {report?.fatal_error ? <p className="text-sm text-destructive">{report.fatal_error}</p> : null}

      {report ? (
        <div className="space-y-4">
          <div className="grid gap-2 sm:grid-cols-5">
            <div className="rounded-md border p-3"><p className="text-xs text-muted-foreground">Total</p><p className="text-xl font-bold">{report.total_rows}</p></div>
            <div className="rounded-md border p-3"><p className="text-xs text-muted-foreground">OK</p><p className="text-xl font-bold text-emerald-600">{report.ok_rows}</p></div>
            <div className="rounded-md border p-3"><p className="text-xs text-muted-foreground">Errores</p><p className="text-xl font-bold text-red-600">{report.error_rows}</p></div>
            <div className="rounded-md border p-3"><p className="text-xs text-muted-foreground">Omitidas (dup)</p><p className="text-xl font-bold text-amber-600">{report.skipped_rows}</p></div>
            <div className="rounded-md border p-3"><p className="text-xs text-muted-foreground">Vínculos creados</p><p className="text-xl font-bold">{report.created_links}</p></div>
          </div>
          <div className="rounded-md border">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-muted/50 text-left">
                  <th className="p-2 font-medium">Línea</th>
                  <th className="p-2 font-medium">Estado</th>
                  <th className="p-2 font-medium">Detalle</th>
                </tr>
              </thead>
              <tbody>
                {report.rows.map((r, i) => (
                  <tr key={i} className="border-b">
                    <td className="p-2">{r.line}</td>
                    <td className="p-2">
                      <span className={`rounded px-2 py-0.5 text-xs font-medium ${statusBadge(r.status)}`}>{r.status}</span>
                    </td>
                    <td className="p-2">{r.message}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}
    </div>
  )
}
