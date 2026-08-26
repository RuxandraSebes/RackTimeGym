import { OccupancyGauge } from '@/components/OccupancyGauge'
import { OccupancyHeatmapPanel } from '@/components/OccupancyHeatmapPanel'
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export function MemberLanding() {
  return (
    <div className="flex flex-col gap-6">
      <Card className="max-w-md">
        <CardHeader>
          <CardTitle>Welcome back</CardTitle>
          <CardDescription>
            Scan the Door QR at the front desk to check in. Booking a Class or reserving an Equipment Unit will live
            here once those are built.
          </CardDescription>
        </CardHeader>
      </Card>
      <OccupancyGauge />
      <OccupancyHeatmapPanel />
    </div>
  )
}
