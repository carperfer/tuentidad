import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { api, ApiError, resetCsrf } from './client'

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

  it('lanza ApiError con el estado y el cuerpo si la respuesta no es correcta', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ message: 'no' }, 403))

    const error = await api('/api/privado').catch((e: unknown) => e)
    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({ status: 403, body: { message: 'no' } })
  })
})
