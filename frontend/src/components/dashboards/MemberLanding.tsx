import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export function MemberLanding() {
  return (
    <Card className="max-w-md">
      <CardHeader>
        <CardTitle>Welcome back</CardTitle>
        <CardDescription>
          Check in at the door, book a Class, or reserve an Equipment Unit from here once those are live.
        </CardDescription>
      </CardHeader>
    </Card>
  )
}
