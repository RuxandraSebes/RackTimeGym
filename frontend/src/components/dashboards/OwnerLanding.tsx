import { DoorQrPanel } from '@/components/DoorQrPanel'
import { GymAccountsPanel } from '@/components/GymAccountsPanel'
import { ManualCheckInPanel } from '@/components/ManualCheckInPanel'
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export function OwnerLanding() {
  return (
    <div className="flex flex-col gap-6">
      <Card className="max-w-md">
        <CardHeader>
          <CardTitle>Owner dashboard</CardTitle>
          <CardDescription>Utilization and Churn Signals will live here once those are built.</CardDescription>
        </CardHeader>
      </Card>
      <DoorQrPanel />
      <ManualCheckInPanel />
      <GymAccountsPanel creatableRoles={['member', 'staff', 'owner']} />
    </div>
  )
}
