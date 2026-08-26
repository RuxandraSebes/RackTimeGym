import { DoorQrPanel } from '@/components/DoorQrPanel'
import { GymAccountsPanel } from '@/components/GymAccountsPanel'
import { ManualCheckInPanel } from '@/components/ManualCheckInPanel'
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export function StaffLanding() {
  return (
    <div className="flex flex-col gap-6">
      <Card className="max-w-md">
        <CardHeader>
          <CardTitle>Staff dashboard</CardTitle>
          <CardDescription>Manage Class rosters from here once those are live.</CardDescription>
        </CardHeader>
      </Card>
      <DoorQrPanel />
      <ManualCheckInPanel />
      <GymAccountsPanel creatableRoles={['member']} />
    </div>
  )
}
