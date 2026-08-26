import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { fetchOccupancy } from '@/lib/api'
import { useAuth } from '@/lib/auth'

const POLL_INTERVAL_MS = 15_000

export function OccupancyGauge() {
  const { state } = useAuth()
  const token = state.phase === 'signed-in' ? state.token : null

  const [count, setCount] = useState<number | null>(null)
  const [error, setError] = useState(false)

  useEffect(() => {
    if (!token) return

    let cancelled = false

    const load = () => {
      fetchOccupancy(token)
        .then((value) => {
          if (!cancelled) {
            setCount(value)
            setError(false)
          }
        })
        .catch(() => {
          if (!cancelled) setError(true)
        })
    }

    load()
    const interval = setInterval(load, POLL_INTERVAL_MS)

    return () => {
      cancelled = true
      clearInterval(interval)
    }
  }, [token])

  return (
    <Card className="max-w-md">
      <CardHeader>
        <CardTitle>Occupancy</CardTitle>
        <CardDescription>Members currently on the floor, from Door Check-ins in the last 90 minutes.</CardDescription>
      </CardHeader>
      <CardContent>
        {error && <p className="text-destructive text-sm">Could not load occupancy.</p>}
        {!error && count === null && <p className="text-muted-foreground text-sm">Loading…</p>}
        {!error && count !== null && <p className="font-heading text-4xl font-medium">{count}</p>}
      </CardContent>
    </Card>
  )
}
