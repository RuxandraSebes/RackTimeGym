import type { ReactNode } from 'react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { AuthUser } from '@/lib/api'

const ROLE_LABEL: Record<AuthUser['role'], string> = {
  member: 'Member',
  staff: 'Staff',
  owner: 'Owner',
}

export function AppShell({
  user,
  onLogout,
  children,
}: {
  user: AuthUser
  onLogout: () => void
  children: ReactNode
}) {
  return (
    <div className="min-h-svh">
      <header className="flex items-center justify-between border-b px-6 py-4">
        <div className="flex items-center gap-3">
          <span className="font-semibold">{user.gym.name}</span>
          <Badge variant="secondary">{ROLE_LABEL[user.role]}</Badge>
        </div>
        <div className="flex items-center gap-3">
          <span className="text-muted-foreground text-sm">{user.name}</span>
          <Button variant="outline" size="sm" onClick={onLogout}>
            Sign out
          </Button>
        </div>
      </header>
      <main className="p-6">{children}</main>
    </div>
  )
}
