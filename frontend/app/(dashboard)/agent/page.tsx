import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { AgentChat } from './agent-chat'

export const dynamic = 'force-dynamic'

type Suggestion = { intent: string; label: string; description: string; icon: string; offline_safe: boolean }

export default async function AgentPage() {
  const session = await getSession()
  if (!session) return null

  const [sug, status] = await Promise.all([
    apiFetch<{ data: Suggestion[] }>('/api/v1/agent/suggestions', session.accessToken).catch(() => null),
    apiFetch<{ data: { isOnline: boolean } }>('/api/v1/agent/status', session.accessToken).catch(() => null),
  ])

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Agente AI</h1>
        <p className="text-sm text-muted-foreground">
          Asistente guiado para crear partes, BOMs y entidades
          {status?.data.isOnline ? ' · IA conectada' : ' · modo offline'}
        </p>
      </div>
      <AgentChat
        token={session.accessToken}
        initialSuggestions={(sug?.data ?? []).map((s) => ({ label: s.label, intent: s.intent }))}
        isOnline={status?.data.isOnline ?? false}
      />
    </div>
  )
}
