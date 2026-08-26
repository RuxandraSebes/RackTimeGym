import { useEffect, useState, type FormEvent } from 'react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { createGymAccount, fetchGymAccounts, type GymAccount, type Role } from '@/lib/api'
import { useAuth } from '@/lib/auth'

const ROLE_LABEL: Record<Role, string> = {
  member: 'Member',
  staff: 'Staff',
  owner: 'Owner',
}

export function GymAccountsPanel({ creatableRoles }: { creatableRoles: Role[] }) {
  const { state } = useAuth()
  const token = state.phase === 'signed-in' ? state.token : null

  const [accounts, setAccounts] = useState<GymAccount[] | null>(null)
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [role, setRole] = useState<Role>(creatableRoles[0])
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    if (!token) return
    fetchGymAccounts(token).then(setAccounts).catch(() => setAccounts([]))
  }, [token])

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (!token) return
    setError(null)
    setSubmitting(true)

    try {
      const account = await createGymAccount(token, { name, email, password, role })
      setAccounts((current) => [...(current ?? []), account])
      setName('')
      setEmail('')
      setPassword('')
    } catch {
      setError('Could not create that account. Check the details and try again.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Card className="max-w-md">
      <CardHeader>
        <CardTitle>Gym accounts</CardTitle>
        <CardDescription>Everyone with access to this Gym.</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-6">
        <ul className="flex flex-col gap-2">
          {accounts === null && <li className="text-muted-foreground text-sm">Loading…</li>}
          {accounts?.map((account) => (
            <li key={account.id} className="flex items-center justify-between text-sm">
              <span>
                {account.name} <span className="text-muted-foreground">({account.email})</span>
              </span>
              <Badge variant="secondary">{ROLE_LABEL[account.role]}</Badge>
            </li>
          ))}
        </ul>

        <form className="flex flex-col gap-4" onSubmit={handleSubmit}>
          <div className="flex flex-col gap-2">
            <Label htmlFor="account-name">Name</Label>
            <Input id="account-name" required value={name} onChange={(event) => setName(event.target.value)} />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="account-email">Email</Label>
            <Input
              id="account-email"
              type="email"
              required
              value={email}
              onChange={(event) => setEmail(event.target.value)}
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="account-password">Password</Label>
            <Input
              id="account-password"
              type="password"
              required
              value={password}
              onChange={(event) => setPassword(event.target.value)}
            />
          </div>
          {creatableRoles.length > 1 && (
            <div className="flex flex-col gap-2">
              <Label htmlFor="account-role">Role</Label>
              <Select value={role} onValueChange={(value) => setRole(value as Role)}>
                <SelectTrigger id="account-role" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {creatableRoles.map((creatableRole) => (
                    <SelectItem key={creatableRole} value={creatableRole}>
                      {ROLE_LABEL[creatableRole]}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}
          {error && <p className="text-destructive text-sm">{error}</p>}
          <Button type="submit" disabled={submitting}>
            {submitting ? 'Adding…' : 'Add account'}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}
