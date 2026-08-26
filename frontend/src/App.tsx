import { AppShell } from '@/components/AppShell'
import { MemberLanding } from '@/components/dashboards/MemberLanding'
import { OwnerLanding } from '@/components/dashboards/OwnerLanding'
import { StaffLanding } from '@/components/dashboards/StaffLanding'
import { DoorCheckInPage } from '@/components/DoorCheckInPage'
import { LoginForm } from '@/components/LoginForm'
import { AuthProvider, useAuth } from '@/lib/auth'

const DOOR_CHECK_IN_PATH = /^\/checkin\/door\/([^/]+)$/

function Landing({ role }: { role: 'member' | 'staff' | 'owner' }) {
  switch (role) {
    case 'staff':
      return <StaffLanding />
    case 'owner':
      return <OwnerLanding />
    default:
      return <MemberLanding />
  }
}

function AppContent() {
  const { state, logout } = useAuth()

  if (state.phase === 'loading') {
    return <main className="flex min-h-svh items-center justify-center p-6 text-muted-foreground">Loading…</main>
  }

  if (state.phase === 'signed-out') {
    return <LoginForm />
  }

  return (
    <AppShell user={state.user} onLogout={logout}>
      <Landing role={state.user.role} />
    </AppShell>
  )
}

function App() {
  const doorCheckInMatch = window.location.pathname.match(DOOR_CHECK_IN_PATH)

  return (
    <AuthProvider>
      {doorCheckInMatch ? <DoorCheckInPage token={decodeURIComponent(doorCheckInMatch[1])} /> : <AppContent />}
    </AuthProvider>
  )
}

export default App
