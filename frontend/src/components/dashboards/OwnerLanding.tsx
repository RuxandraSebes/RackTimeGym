import { GymAccountsPanel } from '@/components/GymAccountsPanel'
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export function OwnerLanding() {
  return (
    <div className="flex flex-col gap-6">
      <Card className="max-w-md">
        <CardHeader>
          <CardTitle>Owner dashboard</CardTitle>
          <CardDescription>
            Gym-level configuration, Utilization, and Churn Signals will live here once those are built.
          </CardDescription>
        </CardHeader>
      </Card>
      <GymAccountsPanel creatableRoles={['member', 'staff', 'owner']} />
    </div>
  )
}
