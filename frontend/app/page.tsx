import Link from 'next/link'
import { Button } from '@/components/ui/button'
import { Boxes, Factory, ShoppingCart, ChartLine } from 'lucide-react'

const FEATURES = [
  {
    icon: Boxes,
    title: 'Catálogo y variantes',
    desc: 'Partes, variantes, unidades de medida y BOM multinivel con árbol de composición.',
  },
  {
    icon: Factory,
    title: 'Producción',
    desc: 'Órdenes con máquina de estados, rutas con operaciones, centros de trabajo y planificación con Gantt.',
  },
  {
    icon: ShoppingCart,
    title: 'Inventario y compras',
    desc: 'Stock por depósito con matriz de validaciones, movimientos y compras con recepción automática.',
  },
  {
    icon: ChartLine,
    title: 'Sugerencias MRP',
    desc: 'Cálculo de fabricabilidad y requerimientos de materiales con reportes exportables.',
  },
]

export default function LandingPage() {
  return (
    <main className="min-h-screen">
      <section className="mx-auto max-w-5xl px-6 py-24 text-center space-y-6">
        <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">
          MRP · Control de Producción e Inventario
        </h1>
        <p className="text-lg text-muted-foreground max-w-2xl mx-auto">
          Planificá producción, controlá stock y comprá justo: del catálogo al Gantt en un solo
          sistema.
        </p>
        <div className="flex justify-center gap-3">
          <Button size="lg" render={<Link href="/registro" />}>
            Crear cuenta gratis
          </Button>
          <Button variant="outline" size="lg" render={<Link href="/login" />}>
            Iniciar sesión
          </Button>
        </div>
      </section>
      <section className="mx-auto max-w-5xl px-6 pb-24 grid gap-6 sm:grid-cols-2">
        {FEATURES.map((f) => (
          <div key={f.title} className="rounded-lg border p-6">
            <f.icon className="h-6 w-6 mb-3" />
            <h3 className="font-semibold">{f.title}</h3>
            <p className="text-sm text-muted-foreground mt-1">{f.desc}</p>
          </div>
        ))}
      </section>
    </main>
  )
}
