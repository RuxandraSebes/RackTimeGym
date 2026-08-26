import { useEffect, useState, type FormEvent } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  cancelClass,
  createClass,
  fetchClassQrToken,
  fetchUpcomingClasses,
  updateClass,
  type GymClass,
} from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { formatSchedule } from '@/lib/format'

function toDateTimeLocal(iso: string): string {
  const date = new Date(iso)
  const localMs = date.getTime() - date.getTimezoneOffset() * 60_000
  return new Date(localMs).toISOString().slice(0, 16)
}

type ClassFormValues = { name: string; startsAt: string; capacity: string }

const EMPTY_FORM: ClassFormValues = { name: '', startsAt: '', capacity: '' }

export function ClassesPanel() {
  const { state } = useAuth()
  const token = state.phase === 'signed-in' ? state.token : null

  const [classes, setClasses] = useState<GymClass[] | null>(null)

  const [form, setForm] = useState<ClassFormValues>(EMPTY_FORM)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [editingId, setEditingId] = useState<number | null>(null)
  const [editForm, setEditForm] = useState<ClassFormValues>(EMPTY_FORM)
  const [editError, setEditError] = useState<string | null>(null)

  const [visibleQrId, setVisibleQrId] = useState<number | null>(null)
  const [qrTokens, setQrTokens] = useState<Record<number, string>>({})
  const [cancellingId, setCancellingId] = useState<number | null>(null)

  useEffect(() => {
    if (!token) return
    fetchUpcomingClasses(token).then(setClasses).catch(() => setClasses([]))
  }, [token])

  async function handleCreate(event: FormEvent) {
    event.preventDefault()
    if (!token) return
    setError(null)
    setSubmitting(true)

    try {
      const created = await createClass(token, {
        name: form.name,
        starts_at: new Date(form.startsAt).toISOString(),
        capacity: Number(form.capacity),
      })
      setClasses((current) => [...(current ?? []), created].sort((a, b) => a.starts_at.localeCompare(b.starts_at)))
      setForm(EMPTY_FORM)
    } catch {
      setError('Could not schedule that Class. Check the details and try again.')
    } finally {
      setSubmitting(false)
    }
  }

  function startEditing(gymClass: GymClass) {
    setEditingId(gymClass.id)
    setEditError(null)
    setEditForm({
      name: gymClass.name,
      startsAt: toDateTimeLocal(gymClass.starts_at),
      capacity: String(gymClass.capacity),
    })
  }

  async function handleUpdate(event: FormEvent) {
    event.preventDefault()
    if (!token || editingId === null) return
    setEditError(null)

    try {
      const updated = await updateClass(token, editingId, {
        name: editForm.name,
        starts_at: new Date(editForm.startsAt).toISOString(),
        capacity: Number(editForm.capacity),
      })
      setClasses((current) => current?.map((gymClass) => (gymClass.id === updated.id ? updated : gymClass)) ?? null)
      setEditingId(null)
    } catch {
      setEditError('Could not save those changes. Check the details and try again.')
    }
  }

  async function handleCancel(gymClass: GymClass) {
    if (!token) return
    setCancellingId(gymClass.id)

    try {
      await cancelClass(token, gymClass.id)
      setClasses((current) => current?.filter((existing) => existing.id !== gymClass.id) ?? null)
    } finally {
      setCancellingId(null)
    }
  }

  async function handleToggleQr(gymClass: GymClass) {
    if (visibleQrId === gymClass.id) {
      setVisibleQrId(null)
      return
    }

    setVisibleQrId(gymClass.id)
    if (!token || qrTokens[gymClass.id]) return

    const qrToken = await fetchClassQrToken(token, gymClass.id)
    setQrTokens((current) => ({ ...current, [gymClass.id]: qrToken }))
  }

  return (
    <Card className="max-w-2xl">
      <CardHeader>
        <CardTitle>Classes</CardTitle>
        <CardDescription>Schedule Classes and manage upcoming sessions.</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-6">
        <ul className="flex flex-col gap-3">
          {classes === null && <li className="text-muted-foreground text-sm">Loading…</li>}
          {classes?.length === 0 && <li className="text-muted-foreground text-sm">No upcoming Classes yet.</li>}
          {classes?.map((gymClass) =>
            editingId === gymClass.id ? (
              <li key={gymClass.id}>
                <form className="flex flex-col gap-2 rounded-md border p-3" onSubmit={handleUpdate}>
                  <Input
                    aria-label="Name"
                    required
                    value={editForm.name}
                    onChange={(event) => setEditForm((current) => ({ ...current, name: event.target.value }))}
                  />
                  <Input
                    aria-label="Starts at"
                    type="datetime-local"
                    required
                    value={editForm.startsAt}
                    onChange={(event) => setEditForm((current) => ({ ...current, startsAt: event.target.value }))}
                  />
                  <Input
                    aria-label="Capacity"
                    type="number"
                    min={1}
                    required
                    value={editForm.capacity}
                    onChange={(event) => setEditForm((current) => ({ ...current, capacity: event.target.value }))}
                  />
                  {editError && <p className="text-destructive text-sm">{editError}</p>}
                  <div className="flex gap-2">
                    <Button type="submit" size="sm">
                      Save
                    </Button>
                    <Button type="button" size="sm" variant="outline" onClick={() => setEditingId(null)}>
                      Cancel
                    </Button>
                  </div>
                </form>
              </li>
            ) : (
              <li key={gymClass.id} className="flex flex-col gap-2 rounded-md border p-3 text-sm">
                <div className="flex items-center justify-between gap-4">
                  <div>
                    <p className="font-medium">{gymClass.name}</p>
                    <p className="text-muted-foreground">
                      {formatSchedule(gymClass.starts_at)} · {gymClass.remaining_capacity}/{gymClass.capacity} spots
                      open
                    </p>
                  </div>
                  <div className="flex shrink-0 gap-2">
                    <Button size="sm" variant="outline" onClick={() => startEditing(gymClass)}>
                      Edit
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={cancellingId === gymClass.id}
                      onClick={() => handleCancel(gymClass)}
                    >
                      {cancellingId === gymClass.id ? 'Cancelling…' : 'Cancel'}
                    </Button>
                  </div>
                </div>
                <Button size="sm" variant="ghost" className="w-fit" onClick={() => handleToggleQr(gymClass)}>
                  {visibleQrId === gymClass.id ? 'Hide QR' : 'Show QR'}
                </Button>
                {visibleQrId === gymClass.id && (
                  <div className="flex w-fit flex-col items-center gap-2 rounded-lg bg-white p-4">
                    {qrTokens[gymClass.id] ? (
                      <QRCodeSVG
                        value={`${window.location.origin}/checkin/class/${qrTokens[gymClass.id]}`}
                        size={160}
                      />
                    ) : (
                      <p className="text-muted-foreground text-sm">Loading…</p>
                    )}
                  </div>
                )}
              </li>
            ),
          )}
        </ul>

        <form className="flex flex-col gap-4" onSubmit={handleCreate}>
          <div className="flex flex-col gap-2">
            <Label htmlFor="class-name">Name</Label>
            <Input
              id="class-name"
              required
              value={form.name}
              onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="class-starts-at">Starts at</Label>
            <Input
              id="class-starts-at"
              type="datetime-local"
              required
              value={form.startsAt}
              onChange={(event) => setForm((current) => ({ ...current, startsAt: event.target.value }))}
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="class-capacity">Capacity</Label>
            <Input
              id="class-capacity"
              type="number"
              min={1}
              required
              value={form.capacity}
              onChange={(event) => setForm((current) => ({ ...current, capacity: event.target.value }))}
            />
          </div>
          {error && <p className="text-destructive text-sm">{error}</p>}
          <Button type="submit" disabled={submitting}>
            {submitting ? 'Scheduling…' : 'Schedule Class'}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}
