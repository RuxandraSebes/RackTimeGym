import { Fragment, useEffect, useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { fetchOccupancyHeatmap, type OccupancyHeatmap, type OccupancyHeatmapBucket } from '@/lib/api'
import { useAuth } from '@/lib/auth'

const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

function formatHour(hour: number): string {
  if (hour === 0) return '12a'
  if (hour === 12) return '12p'
  return hour < 12 ? `${hour}a` : `${hour - 12}p`
}

function formatSlot(bucket: OccupancyHeatmapBucket): string {
  return `${DAY_LABELS[bucket.day_of_week]} ${formatHour(bucket.hour)}`
}

export function OccupancyHeatmapPanel() {
  const { state } = useAuth()
  const token = state.phase === 'signed-in' ? state.token : null

  const [heatmap, setHeatmap] = useState<OccupancyHeatmap | null>(null)
  const [error, setError] = useState(false)

  useEffect(() => {
    if (!token) return

    fetchOccupancyHeatmap(token)
      .then(setHeatmap)
      .catch(() => setError(true))
  }, [token])

  const byDayAndHour = new Map(heatmap?.data.map((bucket) => [`${bucket.day_of_week}-${bucket.hour}`, bucket]))
  const maxCount = heatmap ? Math.max(0, ...heatmap.data.map((bucket) => bucket.count)) : 0

  return (
    <Card className="max-w-3xl">
      <CardHeader>
        <CardTitle>Occupancy heatmap</CardTitle>
        <CardDescription>Door Check-ins by day and hour, so you can see when the gym is busiest.</CardDescription>
      </CardHeader>
      <CardContent>
        {error && <p className="text-destructive text-sm">Could not load the occupancy heatmap.</p>}
        {!error && !heatmap && <p className="text-muted-foreground text-sm">Loading…</p>}
        {!error && heatmap && (
          <div className="flex flex-col gap-4">
            <div className="overflow-x-auto">
              <div className="grid min-w-[720px] grid-cols-[3rem_repeat(24,1fr)] gap-[3px]">
                <div />
                {Array.from({ length: 24 }, (_, hour) => (
                  <div key={hour} className="text-center text-[10px] text-muted-foreground">
                    {hour % 3 === 0 ? formatHour(hour) : ''}
                  </div>
                ))}
                {DAY_LABELS.map((label, dayOfWeek) => (
                  <Fragment key={dayOfWeek}>
                    <div className="flex items-center text-muted-foreground text-xs">{label}</div>
                    {Array.from({ length: 24 }, (_, hour) => {
                      const bucket = byDayAndHour.get(`${dayOfWeek}-${hour}`)
                      const count = bucket?.count ?? 0
                      const intensity = maxCount === 0 ? 0 : count / maxCount
                      const isBusiest = heatmap.busiest?.day_of_week === dayOfWeek && heatmap.busiest?.hour === hour
                      const isQuietest =
                        heatmap.quietest?.day_of_week === dayOfWeek && heatmap.quietest?.hour === hour

                      return (
                        <div
                          key={`${dayOfWeek}-${hour}`}
                          title={`${DAY_LABELS[dayOfWeek]} ${formatHour(hour)}: ${count} check-in${count === 1 ? '' : 's'}`}
                          className={`aspect-square rounded-sm ${isBusiest ? 'ring-2 ring-primary' : ''} ${isQuietest ? 'ring-1 ring-border' : ''}`}
                          style={{ backgroundColor: `oklch(0.6 0.15 250 / ${0.08 + intensity * 0.87})` }}
                        />
                      )
                    })}
                  </Fragment>
                ))}
              </div>
            </div>
            {heatmap.busiest && heatmap.quietest ? (
              <p className="text-muted-foreground text-sm">
                Busiest: <span className="font-medium text-foreground">{formatSlot(heatmap.busiest)}</span> (
                {heatmap.busiest.count} check-ins) · Quietest:{' '}
                <span className="font-medium text-foreground">{formatSlot(heatmap.quietest)}</span> (
                {heatmap.quietest.count} check-ins)
              </p>
            ) : (
              <p className="text-muted-foreground text-sm">No Door Check-ins yet.</p>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
