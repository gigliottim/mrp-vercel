'use client'

import { signOut } from '@/app/actions/auth'
import type { SessionInfo } from '@/lib/session'
import { ThemeToggle } from '@/components/dashboard/theme-toggle'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { LogOut, User } from 'lucide-react'

export function Navbar({ session }: { session: SessionInfo }) {
  return (
    <header className="sticky top-0 z-10 flex h-14 items-center justify-between border-b bg-background px-6">
      <div className="text-sm text-muted-foreground">
        {session.role} · Empresa #{session.companyId}
      </div>
      <div className="flex items-center gap-2">
        <ThemeToggle />
        <DropdownMenu>
          <DropdownMenuTrigger
            render={
              <Button variant="ghost" size="icon" aria-label="Usuario">
                <User className="h-4 w-4" />
              </Button>
            }
          />
          <DropdownMenuContent align="end">
            <DropdownMenuLabel>{session.email}</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={() => signOut()}>
              <LogOut className="mr-2 h-4 w-4" />
              Cerrar sesión
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  )
}
