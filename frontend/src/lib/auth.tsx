import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { fetchMe, login as apiLogin, logout as apiLogout, type AuthUser } from '@/lib/api'

type AuthState =
  | { phase: 'loading' }
  | { phase: 'signed-out' }
  | { phase: 'signed-in'; user: AuthUser; token: string }

type AuthContextValue = {
  state: AuthState
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

const TOKEN_STORAGE_KEY = 'racktimegym.token'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AuthState>({ phase: 'loading' })

  useEffect(() => {
    const token = localStorage.getItem(TOKEN_STORAGE_KEY)
    if (!token) {
      setState({ phase: 'signed-out' })
      return
    }

    fetchMe(token)
      .then((user) => setState({ phase: 'signed-in', user, token }))
      .catch(() => {
        localStorage.removeItem(TOKEN_STORAGE_KEY)
        setState({ phase: 'signed-out' })
      })
  }, [])

  async function login(email: string, password: string) {
    const { token, user } = await apiLogin(email, password)
    localStorage.setItem(TOKEN_STORAGE_KEY, token)
    setState({ phase: 'signed-in', user, token })
  }

  async function logout() {
    if (state.phase === 'signed-in') {
      await apiLogout(state.token).catch(() => {})
    }
    localStorage.removeItem(TOKEN_STORAGE_KEY)
    setState({ phase: 'signed-out' })
  }

  return <AuthContext.Provider value={{ state, login, logout }}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}
