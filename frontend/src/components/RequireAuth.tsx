import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router'
import { useAuth } from '../auth/context'

/** Muestra el contenido solo con sesión iniciada; si no, vuelve a la portada. */
export function RequireAuth({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) return <p className="status">Cargando…</p>
  if (!user) return <Navigate to="/" replace state={{ from: location.pathname }} />

  return children
}

/** Muestra el contenido solo sin sesión; si la hay, lleva al inicio. */
export function RequireGuest({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth()

  if (loading) return <p className="status">Cargando…</p>
  if (user) return <Navigate to="/inicio" replace />

  return children
}
