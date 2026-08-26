import { useEffect, useState } from 'react'
import { Badge } from '@/components/ui/badge'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { fetchHealth } from '@/lib/health'

type CheckState =
  | { phase: 'loading' }
  | { phase: 'connected' }
  | { phase: 'error'; message: string }

function App() {
  const [state, setState] = useState<CheckState>({ phase: 'loading' })

  useEffect(() => {
    let cancelled = false

    fetchHealth()
      .then((health) => {
        if (cancelled) return
        setState(
          health.database === 'connected'
            ? { phase: 'connected' }
            : { phase: 'error', message: 'Backend cannot reach the database.' },
        )
      })
      .catch((error: unknown) => {
        if (cancelled) return
        const message = error instanceof Error ? error.message : 'Unknown error'
        setState({ phase: 'error', message })
      })

    return () => {
      cancelled = true
    }
  }, [])

  return (
    <main className="flex min-h-svh items-center justify-center p-6">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle>RackTimeGym</CardTitle>
          <CardDescription>Backend connectivity check</CardDescription>
        </CardHeader>
        <CardContent>
          {state.phase === 'loading' && <Badge variant="secondary">Checking…</Badge>}
          {state.phase === 'connected' && <Badge>Connected</Badge>}
          {state.phase === 'error' && (
            <div className="flex flex-col gap-2">
              <Badge variant="destructive">Not connected</Badge>
              <p className="text-muted-foreground text-sm">{state.message}</p>
            </div>
          )}
        </CardContent>
      </Card>
    </main>
  )
}

export default App
