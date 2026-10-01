import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApi } from '../test/utils'
import { login } from './auth'
import { post, resetCsrf } from './client'

describe('auth', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    resetCsrf()
  })

  it('tras iniciar sesión pide un token CSRF nuevo, porque la sesión cambia', async () => {
    let csrfRequests = 0
    const fetchMock = mockApi({
      'GET /api/csrf': () => ({ body: { header: 'X-CSRF-TOKEN', token: `token-${++csrfRequests}` } }),
      'POST /api/auth/login': () => ({ body: { user: { id: 1 } } }),
      'POST /api/invitations': () => ({ status: 201, body: {} }),
    })

    await login('ana@example.com', 'secreta', false)
    await post('/api/invitations', { email: 'bea@example.com' })

    const invite = fetchMock.mock.calls.find(([url]) => url === '/api/invitations')!
    expect(new Headers(invite[1]?.headers).get('X-CSRF-TOKEN')).toBe('token-2')
  })
})
