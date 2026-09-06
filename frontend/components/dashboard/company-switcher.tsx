'use client'

import { useEffect, useState, useTransition } from 'react'
import { useRouter } from 'next/navigation'
import { Building2, Check, ChevronsUpDown } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { apiFetch } from '@/lib/api'
import { switchCompany } from '@/app/actions/auth'
import { toast } from 'sonner'

type Company = { company_id: number; companies: { id: number; name: string } }

export function CompanySwitcher({ currentCompanyId, accessToken }: { currentCompanyId: number; accessToken: string }) {
  const [empresas, setEmpresas] = useState<Company[]>([])
  const [pending, startTransition] = useTransition()
  const router = useRouter()

  useEffect(() => {
    apiFetch<{ data: Company[] }>('/api/v1/companies', accessToken)
      .then((r) => setEmpresas(r.data ?? []))
      .catch(() => {})
  }, [accessToken])

  if (empresas.length <= 1) {
    const nombre = empresas[0]?.companies?.name
    return <span className="text-sm font-medium">{nombre ?? `Empresa #${currentCompanyId}`}</span>
  }

  const cambiar = (id: number) => {
    startTransition(async () => {
      const res = await switchCompany(id)
      if (res.ok) {
        toast.success('Empresa cambiada')
        router.refresh()
      } else {
        toast.error(res.error ?? 'Error')
      }
    })
  }

  const actual = empresas.find((e) => e.company_id === currentCompanyId)
  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={
          <Button variant="ghost" size="sm" disabled={pending} className="gap-1">
            <Building2 className="h-4 w-4" />
            {actual?.companies?.name ?? `Empresa #${currentCompanyId}`}
            <ChevronsUpDown className="h-3 w-3 opacity-50" />
          </Button>
        }
      />
      <DropdownMenuContent align="start">
        <DropdownMenuLabel>Ingresar a empresa</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {empresas.map((e) => (
          <DropdownMenuItem key={e.company_id} onClick={() => cambiar(e.company_id)}>
            {e.companies?.name ?? `Empresa #${e.company_id}`}
            {e.company_id === currentCompanyId ? <Check className="ml-auto h-4 w-4" /> : null}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
