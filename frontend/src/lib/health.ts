export type HealthStatus = {
  status: string
  database: string
}

const API_URL = import.meta.env.VITE_API_URL ?? '/api'

export async function fetchHealth(): Promise<HealthStatus> {
  const response = await fetch(`${API_URL}/health`)

  if (!response.ok) {
    throw new Error(`Health check failed with status ${response.status}`)
  }

  return response.json()
}
