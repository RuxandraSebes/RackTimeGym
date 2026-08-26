import { useEffect, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { checkInMemberManually, fetchGymAccounts, type GymAccount } from '@/lib/api'
import { useAuth } from '@/lib/auth'

export function ManualCheckInPanel() {
  const { state } = useAuth()
  const token = state.phase === 'signed-in' ? state.token : null

  const [members, setMembers] = useState<GymAccount[] | null>(null)
  const [checkingInId, setCheckingInId] = useState<number | null>(null)
  const [confirmation, setConfirmation] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!token) return
    fetchGymAccounts(token)
      .then((accounts) => setMembers(accounts.filter((account) => account.role === 'member')))
      .catch(() => setMembers([]))
  }, [token])

  async function handleCheckIn(member: GymAccount) {
    if (!token) return
    setError(null)
    setConfirmation(null)
    setCheckingInId(member.id)

    try {
      await checkInMemberManually(token, member.id)
      setConfirmation(`Checked in ${member.name}.`)
    } catch {
      setError(`Could not check in ${member.name}. Please try again.`)
    } finally {
      setCheckingInId(null)
    }
  }

  return (
    <Card className="max-w-md">
      <CardHeader>
        <CardTitle>Manual check-in</CardTitle>
        <CardDescription>Fallback for the front desk when a Member can't scan the Door QR.</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {confirmation && <p className="text-primary text-sm">{confirmation}</p>}
        {error && <p className="text-destructive text-sm">{error}</p>}
        <ul className="flex flex-col gap-2">
          {members === null && <li className="text-muted-foreground text-sm">Loading…</li>}
          {members?.length === 0 && <li className="text-muted-foreground text-sm">No Members yet.</li>}
          {members?.map((member) => (
            <li key={member.id} className="flex items-center justify-between text-sm">
              <span>
                {member.name} <span className="text-muted-foreground">({member.email})</span>
              </span>
              <Button
                size="sm"
                variant="outline"
                disabled={checkingInId === member.id}
                onClick={() => handleCheckIn(member)}
              >
                {checkingInId === member.id ? 'Checking in…' : 'Check in'}
              </Button>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  )
}
