'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { actualizarEmpresa } from './actions'

export type Empresa = {
  id: number
  name: string
  slug: string
  tax_id: string | null
  contact_email: string
  phone: string | null
  address: string | null
  city: string | null
  province: string | null
  country: string | null
  zip_code: string | null
}

export function EmpresaForm({ empresa }: { empresa: Empresa }) {
  const [name, setName] = useState(empresa.name)
  const [taxId, setTaxId] = useState(empresa.tax_id ?? '')
  const [contactEmail, setContactEmail] = useState(empresa.contact_email)
  const [phone, setPhone] = useState(empresa.phone ?? '')
  const [address, setAddress] = useState(empresa.address ?? '')
  const [city, setCity] = useState(empresa.city ?? '')
  const [province, setProvince] = useState(empresa.province ?? '')
  const [country, setCountry] = useState(empresa.country ?? '')
  const [zipCode, setZipCode] = useState(empresa.zip_code ?? '')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async () => {
    setError('')
    setBusy(true)
    const res = await actualizarEmpresa({
      name,
      tax_id: taxId || null,
      contact_email: contactEmail,
      phone: phone || null,
      address: address || null,
      city: city || null,
      province: province || null,
      country: country || null,
      zip_code: zipCode || null,
    })
    setBusy(false)
    if (!res.ok) setError(res.error ?? 'Error')
  }

  return (
    <div className="max-w-2xl space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>Nombre</Label>
          <Input value={name} onChange={(e) => setName(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Slug</Label>
          <Input value={empresa.slug} disabled />
        </div>
        <div className="space-y-2">
          <Label>CUIT / Tax ID</Label>
          <Input value={taxId} onChange={(e) => setTaxId(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Email de contacto</Label>
          <Input type="email" value={contactEmail} onChange={(e) => setContactEmail(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Teléfono</Label>
          <Input value={phone} onChange={(e) => setPhone(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Dirección</Label>
          <Input value={address} onChange={(e) => setAddress(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Ciudad</Label>
          <Input value={city} onChange={(e) => setCity(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Provincia</Label>
          <Input value={province} onChange={(e) => setProvince(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>País</Label>
          <Input value={country} onChange={(e) => setCountry(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Código postal</Label>
          <Input value={zipCode} onChange={(e) => setZipCode(e.target.value)} />
        </div>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      <Button onClick={handleSubmit} disabled={busy}>{busy ? 'Guardando...' : 'Guardar'}</Button>
    </div>
  )
}
