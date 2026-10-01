import { render } from '@testing-library/react'
import type { ReactElement } from 'react'
import { MemoryRouter } from 'react-router'
import { vi } from 'vitest'
import { AuthProvider } from '../auth/AuthContext'

type Handler = (init?: RequestInit) => { status?: number; body?: unknown }

/**
 * Sustituye fetch por respuestas simuladas según "MÉTODO /ruta".
 * Devuelve el mock para inspeccionar las llamadas.
 */
export function mockApi(routes: Record<string, Handler>) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const key = `${(init?.method ?? 'GET').toUpperCase()} ${String(input)}`
    const handler = routes[key] ?? defaults[key]
    if (!handler) throw new Error(`Petición no simulada: ${key}`)
    const { status = 200, body = null } = handler(init)
    return new Response(status === 204 ? null : JSON.stringify(body), {
      status,
      headers: { 'Content-Type': 'application/json' },
    })
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

const defaults: Record<string, Handler> = {
  'GET /api/csrf': () => ({ body: { header: 'X-CSRF-TOKEN', token: 'csrf' } }),
  'GET /api/auth/me': () => ({ body: { user: null } }),
}

export const ana = { id: 1, email: 'ana@example.com', first_name: 'Ana', last_name: 'García' }

/** Renderiza con router en la ruta indicada y el proveedor de sesión. */
export function renderAt(ui: ReactElement, path = '/') {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>{ui}</AuthProvider>
    </MemoryRouter>,
  )
}

/** Cuerpo JSON enviado en una llamada de fetch. */
export function sentBody(call: unknown[]): unknown {
  const init = call[1] as RequestInit | undefined
  return init?.body ? JSON.parse(String(init.body)) : undefined
}
