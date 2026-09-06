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
  redirect('/panel')
}

export async function signOut() {
  const supabase = await createClient()
  await supabase.auth.signOut()
  revalidatePath('/', 'layout')
  redirect('/login')
}

// Cambio de contraseña forzado (usuarios creados con password temporal):
// valida la actual, setea la nueva y limpia el flag must_change_password.
// El JWT recién deja de traer mustChangePassword tras refrescar la sesión.
export async function changePassword(
  current: string,
  nueva: string
): Promise<{ ok: boolean; error?: string }> {
  const supabase = await createClient()
  const { data: { user } } = await supabase.auth.getUser()
  if (!user) return { ok: false, error: 'Sesión expirada' }

  // La password temporal sirve de credential actual: verificar que la sepa
  const { error: credErr } = await supabase.auth.signInWithPassword({
    email: user.email ?? '',
    password: current,
  })
  if (credErr) return { ok: false, error: 'La contraseña actual es incorrecta' }

  if (nueva.length < 8) return { ok: false, error: 'La nueva contraseña debe tener al menos 8 caracteres' }

  const { error } = await supabase.auth.updateUser({ password: nueva })
  if (error) return { ok: false, error: error.message }

  // Limpia el flag en user_metadata (merge por claves en updateUser)
  await supabase.auth.updateUser({ data: { must_change_password: false } })
  // Refresca el JWT para que mustChangePassword llegue false en la sesión
  await supabase.auth.refreshSession()

  revalidatePath('/', 'layout')
  redirect('/panel')
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
