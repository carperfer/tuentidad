import { createContext, useContext } from 'react'
import type { User } from '../api/auth'

export type AuthState = {
  /** Usuario con sesión iniciada, o null. */
  user: User | null
  /** true mientras se comprueba si hay sesión al cargar la página. */
  loading: boolean
  login: (email: string, password: string, remember: boolean) => Promise<User>
  logout: () => Promise<void>
  /** Fija el usuario tras un registro (que ya inicia sesión). */
  setUser: (user: User | null) => void
}

export const AuthContext = createContext<AuthState | null>(null)

export function useAuth(): AuthState {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth debe usarse dentro de <AuthProvider>')
  return context
}
