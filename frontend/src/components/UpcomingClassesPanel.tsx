import { useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { fetchUpcomingClasses, type GymClass } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { formatSchedule } from '@/lib/format'

export function UpcomingClassesPanel() {
  const { state } = useAuth()
  const token = state.phase === 'signed-in' ? state.token : null

  const [classes, setClasses] = useState<GymClass[] | null>(null)

  useEffect(() => {
    if (!token) return
    fetchUpcomingClasses(token).then(setClasses).catch(() => setClasses([]))
  }, [token])

  return (
    <Card className="max-w-md">
      <CardHeader>
        <CardTitle>Upcoming Classes</CardTitle>
        <CardDescription>Booking a spot will live here once Class booking is built.</CardDescription>
      </CardHeader>
      <CardContent>
        <ul className="flex flex-col gap-2">
          {classes === null && <li className="text-muted-foreground text-sm">Loading…</li>}
          {classes?.length === 0 && <li className="text-muted-foreground text-sm">No upcoming Classes yet.</li>}
          {classes?.map((gymClass) => (
            <li key={gymClass.id} className="flex items-center justify-between text-sm">
              <span>
                {gymClass.name} <span className="text-muted-foreground">— {formatSchedule(gymClass.starts_at)}</span>
              </span>
              <span className="text-muted-foreground">{gymClass.remaining_capacity} spots left</span>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  )
}
