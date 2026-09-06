import { redirect } from 'next/navigation'
import { getSession } from '@/lib/session'
import { fetchMenu } from '@/lib/menu'
import { Sidebar } from '@/components/dashboard/sidebar'
import { Navbar } from '@/components/dashboard/navbar'

export default async function DashboardLayout({
  children,
}: {
  children: React.ReactNode
}) {
  const session = await getSession()
  if (!session) redirect('/login')

  const items = await fetchMenu(session.accessToken)

  return (
    <div className="flex min-h-screen">
      <Sidebar session={session} items={items} />
      <div className="flex flex-1 flex-col">
        <Navbar session={session} />
        <main className="flex-1 p-6">{children}</main>
      </div>
    </div>
  )
}
