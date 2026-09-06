'use client'

import { useState, type FormEvent } from 'react'
import Link from 'next/link'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'https://api.mimrp.com.ar'

type Step = 1 | 2 | 'done'

type FormState = {
  empresa_nombre: string
  empresa_cuit: string
  contacto_email: string
  admin_nombre: string
  admin_email: string
  password: string
  password_confirm: string
}

type FieldErrors = Partial<Record<keyof FormState, string>>

const INITIAL: FormState = {
  empresa_nombre: '',
  empresa_cuit: '',
  contacto_email: '',
  admin_nombre: '',
  admin_email: '',
  password: '',
  password_confirm: '',
}

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

function validateStep1(d: FormState): FieldErrors {
  const errors: FieldErrors = {}
  if (d.empresa_nombre.trim().length < 2 || d.empresa_nombre.trim().length > 100)
    errors.empresa_nombre = 'El nombre de la empresa debe tener entre 2 y 100 caracteres'
  if (d.empresa_cuit.trim().length > 20)
    errors.empresa_cuit = 'El CUIT no puede superar los 20 caracteres'
  if (!EMAIL_RE.test(d.contacto_email.trim()) || d.contacto_email.length > 150)
    errors.contacto_email = 'Ingresá un email de contacto válido'
  return errors
}

function validateStep2(d: FormState): FieldErrors {
  const errors: FieldErrors = {}
  if (d.admin_nombre.trim().length < 2 || d.admin_nombre.trim().length > 100)
    errors.admin_nombre = 'El nombre debe tener entre 2 y 100 caracteres'
  if (!EMAIL_RE.test(d.admin_email.trim()) || d.admin_email.length > 150)
    errors.admin_email = 'Ingresá un email válido'
  if (d.password.length < 8 || d.password.length > 72)
    errors.password = 'La contraseña debe tener al menos 8 caracteres'
  if (d.password !== d.password_confirm)
    errors.password_confirm = 'Las contraseñas no coinciden'
  return errors
}

export default function RegistroPage() {
  const [step, setStep] = useState<Step>(1)
  const [data, setData] = useState<FormState>(INITIAL)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [apiError, setApiError] = useState<string | null>(null)
  const [rateLimited, setRateLimited] = useState(false)
  const [submitting, setSubmitting] = useState(false)

  function update(field: keyof FormState, value: string) {
    setData((prev) => ({ ...prev, [field]: value }))
    setErrors((prev) => ({ ...prev, [field]: undefined }))
    setApiError(null)
    setRateLimited(false)
  }

  function nextStep(e: FormEvent<HTMLFormElement>) {
    e.preventDefault()
    const errs = validateStep1(data)
    setErrors(errs)
    if (Object.keys(errs).length === 0) setStep(2)
  }

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault()
    const errs = validateStep2(data)
    setErrors(errs)
    if (Object.keys(errs).length > 0) return

    setSubmitting(true)
    setApiError(null)
    try {
      const { password_confirm: _omit, ...payload } = data
      const res = await fetch(`${API_URL}/api/v1/register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
      const body = (await res.json().catch(() => null)) as
        | { data?: unknown; error?: { code?: string; message?: string } }
        | null

      if (res.status === 201) {
        setStep('done')
        return
      }

      // 429 rate limit (3 intentos/min): mostrarlo con máximo protagonismo
      if (res.status === 429) {
        setRateLimited(true)
        setApiError(
          `${body?.error?.message ?? 'Demasiados intentos'}. Esperá un minuto antes de volver a intentar.`
        )
        return
      }

      setApiError(body?.error?.message ?? `No se pudo crear la cuenta (HTTP ${res.status})`)
    } catch {
      setApiError('Error de conexión con el servidor. Intentá nuevamente.')
    } finally {
      setSubmitting(false)
    }
  }

  if (step === 'done') {
    return (
      <main className="flex min-h-screen items-center justify-center bg-background p-4">
        <Card className="w-full max-w-md text-center">
          <CardHeader>
            <CardTitle className="text-2xl">¡Empresa creada!</CardTitle>
            <CardDescription>
              Tu empresa <strong>{data.empresa_nombre}</strong> ya está lista. El usuario admin{' '}
              <strong>{data.admin_email}</strong> ya existe: podés iniciar sesión ahora.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button render={<Link href="/login" />} size="lg" className="w-full">
              Ir a iniciar sesión
            </Button>
          </CardContent>
        </Card>
      </main>
    )
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-background p-4">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="text-2xl">Crear cuenta</CardTitle>
          <CardDescription>
            {step === 1
              ? 'Paso 1 de 2 · Datos de la empresa'
              : 'Paso 2 de 2 · Usuario administrador'}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {step === 1 && (
            <form onSubmit={nextStep} className="space-y-4" noValidate>
              <div className="space-y-2">
                <Label htmlFor="empresa_nombre">Nombre de la empresa</Label>
                <Input
                  id="empresa_nombre"
                  value={data.empresa_nombre}
                  onChange={(e) => update('empresa_nombre', e.target.value)}
                  placeholder="Mi Empresa SRL"
                  required
                />
                {errors.empresa_nombre && (
                  <p className="text-sm text-destructive">{errors.empresa_nombre}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="empresa_cuit">
                  CUIT <span className="text-muted-foreground">(opcional)</span>
                </Label>
                <Input
                  id="empresa_cuit"
                  value={data.empresa_cuit}
                  onChange={(e) => update('empresa_cuit', e.target.value)}
                  placeholder="30-12345678-9"
                />
                {errors.empresa_cuit && (
                  <p className="text-sm text-destructive">{errors.empresa_cuit}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="contacto_email">Email de contacto</Label>
                <Input
                  id="contacto_email"
                  type="email"
                  value={data.contacto_email}
                  onChange={(e) => update('contacto_email', e.target.value)}
                  placeholder="contacto@miempresa.com.ar"
                  required
                />
                {errors.contacto_email && (
                  <p className="text-sm text-destructive">{errors.contacto_email}</p>
                )}
              </div>
              <Button type="submit" className="w-full">
                Continuar
              </Button>
            </form>
          )}

          {step === 2 && (
            <form onSubmit={submit} className="space-y-4" noValidate>
              <div className="space-y-2">
                <Label htmlFor="admin_nombre">Tu nombre</Label>
                <Input
                  id="admin_nombre"
                  value={data.admin_nombre}
                  onChange={(e) => update('admin_nombre', e.target.value)}
                  placeholder="Juan Pérez"
                  required
                />
                {errors.admin_nombre && (
                  <p className="text-sm text-destructive">{errors.admin_nombre}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="admin_email">Email del admin (usuario)</Label>
                <Input
                  id="admin_email"
                  type="email"
                  value={data.admin_email}
                  onChange={(e) => update('admin_email', e.target.value)}
                  placeholder="juan@miempresa.com.ar"
                  required
                />
                {errors.admin_email && (
                  <p className="text-sm text-destructive">{errors.admin_email}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="password">Contraseña</Label>
                <Input
                  id="password"
                  type="password"
                  value={data.password}
                  onChange={(e) => update('password', e.target.value)}
                  required
                  minLength={8}
                />
                {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
              </div>
              <div className="space-y-2">
                <Label htmlFor="password_confirm">Confirmar contraseña</Label>
                <Input
                  id="password_confirm"
                  type="password"
                  value={data.password_confirm}
                  onChange={(e) => update('password_confirm', e.target.value)}
                  required
                />
                {errors.password_confirm && (
                  <p className="text-sm text-destructive">{errors.password_confirm}</p>
                )}
              </div>

              {apiError && (
                <p
                  role={rateLimited ? 'alert' : undefined}
                  className={
                    rateLimited
                      ? 'rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm font-medium text-destructive'
                      : 'text-sm text-destructive'
                  }
                >
                  {apiError}
                </p>
              )}

              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="outline"
                  className="flex-1"
                  onClick={() => setStep(1)}
                  disabled={submitting}
                >
                  Atrás
                </Button>
                <Button type="submit" className="flex-1" disabled={submitting}>
                  {submitting ? 'Creando cuenta...' : 'Crear cuenta'}
                </Button>
              </div>
            </form>
          )}
        </CardContent>
      </Card>
    </main>
  )
}
