import { useEffect, useRef, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { LoginForm } from '@/components/LoginForm'
import { ApiError, checkInAtDoor, type CheckIn } from '@/lib/api'
import { useAuth } from '@/lib/auth'

type Status = { phase: 'checking-in' } | { phase: 'success'; checkIn: CheckIn } | { phase: 'error'; message: string }

export function DoorCheckInPage({ token }: { token: string }) {
  const { state } = useAuth()

  if (state.phase === 'loading') {
    return <main className="flex min-h-svh items-center justify-center p-6 text-muted-foreground">Loading…</main>
  }

  if (state.phase === 'signed-out') {
    return <LoginForm />
  }

  if (state.user.role !== 'member') {
    return (
      <CheckInResult
        title="This QR code is for Members"
        description="Only Member accounts check in at the door. Staff can check a Member in manually from the front desk."
      />
    )
  }

  return <PerformCheckIn token={token} authToken={state.token} />
}

function PerformCheckIn({ token, authToken }: { token: string; authToken: string }) {
  const [status, setStatus] = useState<Status>({ phase: 'checking-in' })
  const requested = useRef(false)

  useEffect(() => {
    if (requested.current) return
    requested.current = true

    checkInAtDoor(authToken, token)
      .then((checkIn) => setStatus({ phase: 'success', checkIn }))
      .catch((error) => {
        const message =
          error instanceof ApiError && error.status === 404
            ? "We don't recognize this Door QR code."
            : error instanceof ApiError && error.status === 403
              ? 'This Door QR belongs to a different Gym.'
              : "We couldn't check you in. Please try again."
        setStatus({ phase: 'error', message })
      })
  }, [authToken, token])

  if (status.phase === 'checking-in') {
    return (
      <CheckInResult title="Checking you in…" description="Hang tight, this only takes a moment." />
    )
  }

  if (status.phase === 'error') {
    return <CheckInResult title="Check-in failed" description={status.message} />
  }

  const checkedInAt = new Date(status.checkIn.checked_in_at).toLocaleTimeString([], {
    hour: 'numeric',
    minute: '2-digit',
  })

  return <CheckInResult title="You're checked in!" description={`Recorded at ${checkedInAt}. Welcome in.`} success />
}

function CheckInResult({
  title,
  description,
  success = false,
}: {
  title: string
  description: string
  success?: boolean
}) {
  return (
    <main className="flex min-h-svh items-center justify-center p-6">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle className={success ? 'text-primary' : undefined}>{title}</CardTitle>
          <CardDescription>{description}</CardDescription>
        </CardHeader>
        <CardContent>
          <Button variant="outline" className="w-full" onClick={() => window.location.assign('/')}>
            Back to dashboard
          </Button>
        </CardContent>
      </Card>
    </main>
  )
}
