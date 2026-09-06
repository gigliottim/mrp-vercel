import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Boxes, Factory, Layers, Building2 } from 'lucide-react'

const ICONS: Record<string, React.ReactNode> = {
  puzzle: <Boxes className="h-5 w-5" />,
  layers: <Layers className="h-5 w-5" />,
  factory: <Factory className="h-5 w-5" />,
  building: <Building2 className="h-5 w-5" />,
}

export function KpiCard({
  title,
  value,
  icon,
  subtitle,
}: {
  title: string
  value: number | string
  icon: string
  subtitle?: string
}) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
        <div className="text-muted-foreground">{ICONS[icon] ?? null}</div>
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold">{value}</div>
        {subtitle ? <p className="text-xs text-muted-foreground">{subtitle}</p> : null}
      </CardContent>
    </Card>
  )
}
