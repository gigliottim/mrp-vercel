'use server'

import { revalidatePath } from 'next/cache'
import { redirect } from 'next/navigation'
import { createClient } from '@/lib/supabase/server'

export type AuthState = { error: string } | null

export async function signInWithPassword(
  _prevState: AuthState,
  formData: FormData
): Promise<AuthState> {
  const supabase = await createClient()
  const email = String(formData.get('email'))
  const password = String(formData.get('password'))

  const { error } = await supabase.auth.signInWithPassword({ email, password })
  if (error) return { error: error.message }

  revalidatePath('/', 'layout')
  redirect('/')
}

export async function signOut() {
  const supabase = await createClient()
  await supabase.auth.signOut()
  revalidatePath('/', 'layout')
  redirect('/login')
}

export async function switchCompany(
  companyId: number
): Promise<{ ok: boolean; error?: string }> {
  const supabase = await createClient()
  const { data: { user } } = await supabase.auth.getUser()
  if (!user) return { ok: false, error: 'Sesión expirada' }

  // 1. Validar pertenencia vía API (RLS: el usuario ve sus propios vínculos)
  const { data: { session } } = await supabase.auth.getSession()
  if (!session) return { ok: false, error: 'Sesión expirada' }
  const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL ?? 'https://api.mimrp.com.ar'}/api/v1/companies`, {
    headers: { Authorization: `Bearer ${session.access_token}` },
  })
  const body = await res.json()
  const empresas: Array<{ company_id: number; companies: { id: number; name: string } }> = body.data ?? []
  if (!empresas.some((e) => e.company_id === companyId)) {
    return { ok: false, error: 'No pertenecés a esa empresa' }
  }

  // 2. Guardar empresa activa en user_metadata
  const { error: upErr } = await supabase.auth.updateUser({ data: { active_company_id: companyId } })
  if (upErr) return { ok: false, error: upErr.message }

  // 3. Forzar refresh del JWT para que el hook regenere los claims
  await supabase.auth.refreshSession()

  revalidatePath('/', 'layout')
  return { ok: true }
}
