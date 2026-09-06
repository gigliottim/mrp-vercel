'use client'

import { useState, useRef, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { toast } from 'sonner'

type Suggestion = { label: string; value?: string; intent?: string }
type GuidedState = { intent: string; data: Record<string, unknown>; step: number } | null
type Msg = { role: 'user' | 'agent'; content: string; suggestions?: Suggestion[]; status?: string; lookup?: string | null }

export function AgentChat({ token, apiUrl, initialSuggestions, isOnline }: { token: string; apiUrl: string; initialSuggestions: Suggestion[]; isOnline: boolean }) {
  const [messages, setMessages] = useState<Msg[]>([
    {
      role: 'agent',
      content: isOnline
        ? 'Hola, soy el asistente de MRP. ¿En qué te ayudo?'
        : 'Hola, soy el asistente de MRP (modo offline). Puedo guiarte para crear partes, BOMs o entidades.',
      suggestions: initialSuggestions,
    },
  ])
  const [input, setInput] = useState('')
  const [convId, setConvId] = useState('')
  const [guided, setGuided] = useState<GuidedState>(null)
  const [busy, setBusy] = useState(false)
  const [lookupOptions, setLookupOptions] = useState<Array<{ value: string; label: string }>>([])
  const [activeLookup, setActiveLookup] = useState<string | null>(null)
  const bottomRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  const send = async (text: string, fieldValue?: string, fieldLabel?: string) => {
    if (!text.trim() && !fieldValue) return
    setBusy(true)
    if (text.trim()) {
      setMessages((m) => [...m, { role: 'user', content: text }])
    }
    try {
      const res = await fetch(`${apiUrl}/api/v1/agent/message`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({
          conversation_id: convId || undefined,
          message: text,
          field_value: fieldValue,
          field_label: fieldLabel,
        }),
      })
      const body = await res.json()
      if (!res.ok) {
        setMessages((m) => [...m, { role: 'agent', content: body.error?.message ?? 'Error' }])
        return
      }
      const d = body.data
      setConvId(d.conversation_id)
      setGuided(d.guided_state)
      setMessages((m) => [
        ...m,
        {
          role: 'agent',
          content: d.message,
          suggestions: d.suggestions ?? [],
          status: d.status,
          lookup: d.lookup ?? null,
        },
      ])
      // Si el siguiente campo tiene lookup, cargar opciones
      if (d.lookup) {
        setActiveLookup(d.lookup)
        await loadLookup(d.lookup)
      } else {
        setActiveLookup(null)
        setLookupOptions([])
      }
    } catch (e) {
      setMessages((m) => [...m, { role: 'agent', content: 'Error de conexión: ' + (e as Error).message }])
    }
    setBusy(false)
  }

  const loadLookup = async (type: string) => {
    try {
      const res = await fetch(`${apiUrl}/api/v1/agent/lookup/${type}`, {
        headers: { Authorization: `Bearer ${token}` },
      })
      const body = await res.json()
      setLookupOptions(body.data ?? [])
    } catch {
      setLookupOptions([])
    }
  }

  const handleSuggestion = (s: Suggestion) => {
    if (s.intent) {
      send(s.label, undefined, undefined)
    } else if (s.value) {
      send(s.value)
    } else {
      send(s.label)
    }
  }

  const handleConfirm = async () => {
    if (!guided) return
    setBusy(true)
    try {
      const res = await fetch(`${apiUrl}/api/v1/agent/confirm`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: convId, intent: guided.intent, data: guided.data }),
      })
      const body = await res.json()
      const d = body.data
      setMessages((m) => [
        ...m,
        {
          role: 'agent',
          content: d.success ? d.message : `No se pudo guardar: ${d.message}`,
          suggestions: d.success ? [{ label: 'Crear otra parte', value: 'crear otra parte' }, { label: 'Volver al menú', value: 'menu' }] : [],
          status: d.success ? 'saved' : 'error',
        },
      ])
      if (d.success) {
        setGuided(null)
        setConvId('')
        setActiveLookup(null)
        setLookupOptions([])
      }
    } catch (e) {
      setMessages((m) => [...m, { role: 'agent', content: 'Error: ' + (e as Error).message }])
    }
    setBusy(false)
  }

  const handleCancel = async () => {
    if (convId) {
      await fetch(`${apiUrl}/api/v1/agent/cancel`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: convId }),
      })
    }
    setGuided(null)
    setConvId('')
    setActiveLookup(null)
    setLookupOptions([])
    setMessages((m) => [...m, { role: 'agent', content: 'Operación cancelada. ¿En qué más te ayudo?' }])
  }

  const handleLookupSelect = (value: string, label: string) => {
    send('', value, label)
    setActiveLookup(null)
    setLookupOptions([])
  }

  return (
    <div className="flex h-[70vh] flex-col rounded-md border">
      <div className="flex-1 space-y-3 overflow-y-auto p-4">
        {messages.map((m, i) => (
          <div key={i} className={`flex ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}>
            <div
              className={`max-w-[80%] rounded-lg px-3 py-2 text-sm ${
                m.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted'
              }`}
            >
              <p className="whitespace-pre-wrap">{m.content}</p>
              {m.suggestions && m.suggestions.length > 0 ? (
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {m.suggestions.map((s, j) => (
                    <button
                      key={j}
                      onClick={() => handleSuggestion(s)}
                      className="rounded-full border bg-background px-2.5 py-1 text-xs hover:bg-accent"
                    >
                      {s.label}
                    </button>
                  ))}
                </div>
              ) : null}
            </div>
          </div>
        ))}
        {busy ? (
          <div className="flex justify-start">
            <div className="rounded-lg bg-muted px-3 py-2 text-sm">Escribiendo...</div>
          </div>
        ) : null}
        <div ref={bottomRef} />
      </div>

      {activeLookup ? (
        <div className="border-t p-2">
          <p className="mb-1.5 text-xs text-muted-foreground">Seleccioná una opción:</p>
          <div className="flex max-h-32 flex-wrap gap-1.5 overflow-y-auto">
            {lookupOptions.map((o) => (
              <button
                key={o.value}
                onClick={() => handleLookupSelect(o.value, o.label)}
                className="rounded-full border bg-background px-2.5 py-1 text-xs hover:bg-accent"
              >
                {o.label}
              </button>
            ))}
          </div>
        </div>
      ) : null}

      {guided ? (
        <div className="flex items-center gap-2 border-t p-2">
          <Button variant="outline" size="sm" onClick={handleCancel} disabled={busy}>
            Cancelar
          </Button>
          <Button size="sm" onClick={handleConfirm} disabled={busy}>
            Confirmar y guardar
          </Button>
        </div>
      ) : null}

      <form
        className="flex items-center gap-2 border-t p-2"
        onSubmit={(e) => {
          e.preventDefault()
          const v = input
          setInput('')
          send(v)
        }}
      >
        <Input
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Escribí un mensaje o Enter para omitir..."
          disabled={busy}
        />
        <Button type="submit" disabled={busy || !input.trim()}>
          Enviar
        </Button>
      </form>
    </div>
  )
}
