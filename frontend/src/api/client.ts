/**
 * Cliente HTTP de la API de tuentidad.
 *
 * - Envía siempre la cookie de sesión (mismo dominio).
 * - En peticiones que modifican datos añade el token CSRF, que obtiene
 *   de /api/csrf la primera vez y reutiliza durante la sesión.
 */

export class ApiError extends Error {
  readonly status: number
  readonly body: unknown

  constructor(status: number, body: unknown) {
    super(`Error ${status} en la API`)
    this.name = 'ApiError'
    this.status = status
    this.body = body
  }
}

type Csrf = { header: string; token: string }

const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS']

let csrfPromise: Promise<Csrf> | null = null

function getCsrf(): Promise<Csrf> {
  csrfPromise ??= request<Csrf>('/api/csrf').catch((error: unknown) => {
    csrfPromise = null
    throw error
  })
  return csrfPromise
}

/** Olvida el token CSRF en caché (p. ej. tras cerrar sesión). */
export function resetCsrf(): void {
  csrfPromise = null
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(path, { credentials: 'same-origin', ...init })
  const isJson = response.headers.get('Content-Type')?.includes('application/json')
  const body: unknown = isJson ? await response.json() : await response.text()

  if (!response.ok) {
    throw new ApiError(response.status, body)
  }

  return body as T
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const method = (init.method ?? 'GET').toUpperCase()
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')

  if (init.body !== undefined && !(init.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json')
  }

  if (SAFE_METHODS.includes(method)) {
    return request<T>(path, { ...init, method, headers })
  }

  const send = async () => {
    const csrf = await getCsrf()
    headers.set(csrf.header, csrf.token)
    return request<T>(path, { ...init, method, headers })
  }

  try {
    return await send()
  } catch (error) {
    // El token CSRF va ligado a la sesión: si esta ha cambiado, se pide uno nuevo y se reintenta
    if (error instanceof ApiError && error.status === 403) {
      resetCsrf()
      return send()
    }
    throw error
  }
}

/** POST con cuerpo JSON. */
export function post<T>(path: string, data?: unknown): Promise<T> {
  return api<T>(path, {
    method: 'POST',
    body: data === undefined ? undefined : JSON.stringify(data),
  })
}

/** Errores de validación por campo de una respuesta 422. */
export function fieldErrors(error: unknown): Record<string, string> {
  if (error instanceof ApiError && error.status === 422) {
    const errors = (error.body as { errors?: Record<string, string> } | null)?.errors
    if (errors) return errors
  }
  return {}
}

/** Mensaje legible de un error de la API. */
export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    const message = (error.body as { message?: unknown } | null)?.message
    if (typeof message === 'string' && message !== '') return message
    if (error.status === 429) return 'Demasiados intentos. Espera un poco y vuelve a probar.'
  }
  return 'Algo ha fallado. Inténtalo de nuevo en unos minutos.'
}
