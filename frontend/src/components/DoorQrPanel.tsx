import { useEffect, useState } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { fetchDoorQrToken } from '@/lib/api'
import { useAuth } from '@/lib/auth'

export function DoorQrPanel() {
  const { state } = useAuth()
  const token = state.phase === 'signed-in' ? state.token : null

  const [doorUrl, setDoorUrl] = useState<string | null>(null)
  const [error, setError] = useState(false)

  useEffect(() => {
    if (!token) return
    fetchDoorQrToken(token)
      .then((doorQrToken) => setDoorUrl(`${window.location.origin}/checkin/door/${doorQrToken}`))
      .catch(() => setError(true))
  }, [token])

  return (
    <Card className="max-w-md">
      <CardHeader>
        <CardTitle>Door check-in QR</CardTitle>
        <CardDescription>Display or print this at the entrance. Members scan it to check in.</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col items-center gap-3">
        {error && <p className="text-destructive text-sm">Could not load the Door QR code.</p>}
        {!error && doorUrl && (
          <>
            <div className="rounded-lg bg-white p-4">
              <QRCodeSVG value={doorUrl} size={192} />
            </div>
            <p className="break-all text-center text-muted-foreground text-xs">{doorUrl}</p>
          </>
        )}
        {!error && !doorUrl && <p className="text-muted-foreground text-sm">Loading…</p>}
      </CardContent>
    </Card>
  )
}
