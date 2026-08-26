import { GymAccountsPanel } from '@/components/GymAccountsPanel'
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export function StaffLanding() {
  return (
    <div className="flex flex-col gap-6">
      <Card className="max-w-md">
        <CardHeader>
          <CardTitle>Staff dashboard</CardTitle>
          <CardDescription>
            Manually check Members in and manage Class rosters from here once those are live.
          </CardDescription>
        </CardHeader>
      </Card>
      <GymAccountsPanel creatableRoles={['member']} />
    </div>
  )
}
