import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { api, ApiError, errorMessage, fieldErrors, resetCsrf } from './client'

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

describe('api', () => {
  const fetchMock = vi.fn<typeof fetch>()

  beforeEach(() => {
    resetCsrf()
    fetchMock.mockReset()
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('hace GET sin pedir token CSRF', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ status: 'ok' }))

    await expect(api('/api/health')).resolves.toEqual({ status: 'ok' })
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(fetchMock.mock.calls[0][0]).toBe('/api/health')
  })

  it('añade la cabecera CSRF en peticiones que modifican datos y la reutiliza', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse({ header: 'X-CSRF-TOKEN', token: 'abc' }))
      .mockResolvedValueOnce(jsonResponse({ ok: true }))
      .mockResolvedValueOnce(jsonResponse({ ok: true }))

    await api('/api/algo', { method: 'POST', body: '{}' })
    await api('/api/algo', { method: 'DELETE' })

    expect(fetchMock).toHaveBeenCalledTimes(3)
    expect(fetchMock.mock.calls[0][0]).toBe('/api/csrf')
    for (const call of fetchMock.mock.calls.slice(1)) {
      const headers = new Headers(call[1]?.headers)
      expect(headers.get('X-CSRF-TOKEN')).toBe('abc')
    }
  })

  it('si el token CSRF ya no es válido (403), pide uno nuevo y reintenta una vez', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse({ header: 'X-CSRF-TOKEN', token: 'viejo' }))
      .mockResolvedValueOnce(jsonResponse({ message: 'no permitido' }, 403))
      .mockResolvedValueOnce(jsonResponse({ header: 'X-CSRF-TOKEN', token: 'nuevo' }))
      .mockResolvedValueOnce(jsonResponse({ ok: true }))

    await expect(api('/api/algo', { method: 'POST', body: '{}' })).resolves.toEqual({ ok: true })
    expect(new Headers(fetchMock.mock.calls[3][1]?.headers).get('X-CSRF-TOKEN')).toBe('nuevo')
  })

  it('lanza ApiError con el estado y el cuerpo si la respuesta no es correcta', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ message: 'no' }, 403))

    const error = await api('/api/privado').catch((e: unknown) => e)
    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({ status: 403, body: { message: 'no' } })
  })

  it('extrae errores de campo y mensajes de las respuestas de error', () => {
    const invalid = new ApiError(422, { message: 'Revisa', errors: { email: 'Email no válido' } })

    expect(fieldErrors(invalid)).toEqual({ email: 'Email no válido' })
    expect(errorMessage(invalid)).toBe('Revisa')
    expect(fieldErrors(new ApiError(500, 'x'))).toEqual({})
    expect(errorMessage(new ApiError(429, ''))).toMatch(/Demasiados intentos/)
    expect(errorMessage(new TypeError('red'))).toMatch(/Algo ha fallado/)
  })
})
