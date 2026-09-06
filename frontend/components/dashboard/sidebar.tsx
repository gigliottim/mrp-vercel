'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import { groupBySection, type MenuItem } from '@/lib/menu'
import type { SessionInfo } from '@/lib/session'
import { Sheet, SheetClose, SheetContent, SheetTrigger } from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
import { Menu } from 'lucide-react'

// Mapeo de iconos Font Awesome del dump a lucide-react
const ICONS: Record<string, string> = {
  'fa-solid fa-gauge': 'LayoutDashboard',
  'fa-solid fa-gauge-high': 'Gauge',
  'fa-solid fa-clipboard-list': 'ClipboardList',
  'fa-solid fa-arrow-right-arrow-left': 'ArrowLeftRight',
  'fa-solid fa-triangle-exclamation': 'TriangleAlert',
  'fa-solid fa-chart-gantt': 'BarChart3',
  'fa-solid fa-sitemap': 'Network',
  'fa-solid fa-list-check': 'ListChecks',
  'fa-solid fa-calendar-days': 'CalendarDays',
  'fa-solid fa-layer-group': 'Layers',
  'fa-solid fa-puzzle-piece': 'Puzzle',
  'fa-solid fa-wrench': 'Wrench',
  'fa-solid fa-diagram-project': 'GitBranch',
  'fa-solid fa-building': 'Building2',
  'fa-solid fa-users': 'Users',
  'fa-solid fa-user-shield': 'ShieldCheck',
  'fa-solid fa-key': 'KeyRound',
  'fa-solid fa-address-book': 'BookUser',
  'fa-solid fa-calendar-check': 'CalendarCheck',
  'fa-solid fa-calendar-alt': 'Calendar',
  'fa-solid fa-shopping-cart': 'ShoppingCart',
  'fa-solid fa-sliders': 'SlidersHorizontal',
  'fa-solid fa-ruler-combined': 'Ruler',
  'fa-solid fa-tags': 'Tags',
  'fa-solid fa-warehouse': 'Warehouse',
  'fa-solid fa-industry': 'Factory',
  'fa-solid fa-route': 'Route',
  'fa-solid fa-copy': 'Copy',
  'fa-solid fa-shuffle': 'Shuffle',
}

function iconFor(item: MenuItem): string {
  if (!item.icon) return 'Circle'
  return ICONS[item.icon] ?? 'Circle'
}

function SidebarContent({
  items,
  pathname,
  companyId,
  mobile = false,
}: {
  items: MenuItem[]
  pathname: string
  companyId: number
  mobile?: boolean
}) {
  const groups = groupBySection(items)
  return (
    <div className="flex h-full flex-col gap-4 py-4">
      <div className="px-4">
        <Link href="/" className="flex items-center gap-2 text-lg font-bold">
          <span className="bg-primary text-primary-foreground rounded-md px-2 py-1">M</span>
          MRP
        </Link>
      </div>
      <nav className="flex-1 space-y-4 overflow-y-auto px-2">
        {groups.map((group) => (
          <div key={group.key}>
            <p className="text-muted-foreground mb-1 px-2 text-xs font-semibold uppercase tracking-wider">
              {group.label}
            </p>
            <div className="space-y-0.5">
              {group.items.map((item) => {
                if (!item.route) return null
                const active = pathname === item.route
                const link = (
                  <Link
                    href={item.route}
                    className={cn(
                      'flex items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors',
                      active
                        ? 'bg-primary/10 font-medium text-primary'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    )}
                  >
                    <span className="w-4 text-center text-base" data-icon={iconFor(item)} />
                    {item.label}
                  </Link>
                )
                return mobile ? (
                  <SheetClose render={link} key={item.id} />
                ) : (
                  <div key={item.id}>{link}</div>
                )
              })}
            </div>
          </div>
        ))}
      </nav>
      <div className="px-4 text-xs text-muted-foreground">Empresa #{companyId}</div>
    </div>
  )
}

export function Sidebar({
  session,
  items,
}: {
  session: SessionInfo
  items: MenuItem[]
}) {
  const pathname = usePathname()

  return (
    <>
      {/* Desktop */}
      <aside className="bg-sidebar hidden w-64 shrink-0 border-r md:block">
        <SidebarContent items={items} pathname={pathname} companyId={session.companyId} />
      </aside>

      {/* Mobile */}
      <div className="md:hidden">
        <Sheet>
          <SheetTrigger
            render={
              <Button variant="outline" size="icon">
                <Menu className="h-4 w-4" />
              </Button>
            }
          />
          <SheetContent side="left" className="w-64 p-0">
            <SidebarContent items={items} pathname={pathname} companyId={session.companyId} mobile />
          </SheetContent>
        </Sheet>
      </div>
    </>
  )
}
