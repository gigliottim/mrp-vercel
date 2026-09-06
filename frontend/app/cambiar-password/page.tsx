'use client'

import { changePassword } from '@/app/actions/auth'
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
import { useRouter } from 'next/navigation'
import { useState, useTransition } from 'react'

// Fuera del grupo (dashboard): el layout (dashboard) redirige acá cuando
// mustChangePassword=true; estar dentro causaría un loop infinito.
export default function CambiarPasswordPage() {
  const router = useRouter()
  const [pending, startTransition] = useTransition()
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault()
    const form = e.currentTarget
    const data = new FormData(form)
    const actual = String(data.get('current') ?? '')
    const nueva = String(data.get('nueva') ?? '')
    const confirmar = String(data.get('confirmar') ?? '')

    setError(null)
    if (nueva.length < 8) {
      setError('La nueva contraseña debe tener al menos 8 caracteres')
      return
    }
    if (nueva !== confirmar) {
      setError('Las contraseñas no coinciden')
      return
    }

    startTransition(async () => {
      const res = await changePassword(actual, nueva)
      if (!res.ok) {
        setError(res.error ?? 'No se pudo cambiar la contraseña')
        return
      }
      // La acción ya refrescó la sesión y redirigió a /panel; este fallback
      // cubre el caso en que redirect no navegó desde el cliente.
      router.replace('/panel')
    })
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle className="text-2xl">Cambiar contraseña</CardTitle>
          <CardDescription>
            Tu cuenta fue creada con una contraseña temporal. Elegí una nueva
            para continuar.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="current">Contraseña actual</Label>
              <Input id="current" name="current" type="password" required />
            </div>
            <div className="space-y-2">
              <Label htmlFor="nueva">Nueva contraseña</Label>
              <Input
                id="nueva"
                name="nueva"
                type="password"
                minLength={8}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="confirmar">Confirmar nueva contraseña</Label>
              <Input
                id="confirmar"
                name="confirmar"
                type="password"
                minLength={8}
                required
              />
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
            <Button type="submit" className="w-full" disabled={pending}>
              {pending ? 'Guardando...' : 'Cambiar contraseña'}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
