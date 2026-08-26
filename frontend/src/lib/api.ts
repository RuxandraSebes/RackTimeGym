export type Role = 'member' | 'staff' | 'owner'

export type AuthUser = {
  id: number
  name: string
  email: string
  role: Role
  gym_id: number
  gym: { id: number; name: string }
}

const API_URL = import.meta.env.VITE_API_URL ?? '/api'

export class ApiError extends Error {
  status: number

  constructor(message: string, status: number) {
    super(message)
    this.status = status
  }
}

async function request<T>(path: string, options: RequestInit = {}, token?: string): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  })

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    throw new ApiError(body?.message ?? `Request failed with status ${response.status}`, response.status)
  }

  if (response.status === 204) {
    return undefined as T
  }

  return response.json() as Promise<T>
}

export function login(email: string, password: string): Promise<{ token: string; user: AuthUser }> {
  return request('/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  })
}

export function logout(token: string): Promise<void> {
  return request('/logout', { method: 'POST' }, token)
}

export async function fetchMe(token: string): Promise<AuthUser> {
  const { data } = await request<{ data: AuthUser }>('/me', {}, token)
  return data
}

export type GymAccount = Pick<AuthUser, 'id' | 'name' | 'email' | 'role'>

export async function fetchGymAccounts(token: string): Promise<GymAccount[]> {
  const { data } = await request<{ data: GymAccount[] }>('/gym/users', {}, token)
  return data
}

export async function createGymAccount(
  token: string,
  account: { name: string; email: string; password: string; role: Role },
): Promise<GymAccount> {
  const { data } = await request<{ data: GymAccount }>(
    '/gym/users',
    { method: 'POST', body: JSON.stringify(account) },
    token,
  )
  return data
}

export type CheckIn = {
  id: number
  gym_id: number
  member: { id: number; name: string }
  checked_in_at: string
}

export async function fetchDoorQrToken(token: string): Promise<string> {
  const { door_qr_token } = await request<{ door_qr_token: string }>('/gym/door-qr', {}, token)
  return door_qr_token
}

export async function checkInAtDoor(token: string, doorQrToken: string): Promise<CheckIn> {
  const { data } = await request<{ data: CheckIn }>(
    `/checkins/door/${encodeURIComponent(doorQrToken)}`,
    { method: 'POST' },
    token,
  )
  return data
}

export async function checkInMemberManually(token: string, userId: number): Promise<CheckIn> {
  const { data } = await request<{ data: CheckIn }>(
    '/gym/checkins',
    { method: 'POST', body: JSON.stringify({ user_id: userId }) },
    token,
  )
  return data
}
