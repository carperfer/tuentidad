import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { ana, mockApi, renderAt, sentBody } from '../test/utils'
import { Home } from './Home'

const baseRoutes = {
  'GET /api/auth/me': () => ({ body: { user: ana } }),
  'GET /api/dashboard': () => ({ body: { invitations_sent: 1, invitations_accepted: 0, friends_count: 3 } }),
  'GET /api/invitations': () => ({ body: { remaining: 9, invitations: [] } }),
  'GET /api/profiles/1': () => ({
    body: { profile: { id: 1, first_name: 'Ana', last_name: 'García', status: '', status_updated_at: null, relationship: 'self' } },
  }),
  'GET /api/friends/activity': () => ({
    body: {
      activity: [
        { id: 2, first_name: 'Bea', last_name: 'López', avatar_url: null, status: 'Berlín: 2 días', updated_at: '2026-10-25 10:00:00' },
      ],
    },
  }),
  'GET /api/friend-requests': () => ({
    body: {
      incoming: [{ id: 7, user_id: 3, first_name: 'Carla', last_name: 'Ruiz', avatar_url: null, created_at: '' }],
      outgoing: [],
    },
  }),
}

describe('Home', () => {
  it('places the user card, the feed and the requests in their columns', async () => {
    mockApi(baseRoutes)
    renderAt(<Home />, '/inicio')

    const feed = await screen.findByRole('region', { name: 'Novedades de tus amigos' })
    expect(await within(feed).findByText('Berlín: 2 días')).toBeInTheDocument()
    expect(within(feed).getByText(/^hace /)).toBeInTheDocument()

    const left = screen.getByRole('complementary', { name: 'Columna izquierda' })
    expect(await within(left).findByText('3 amigos')).toBeInTheDocument()
    expect(within(left).getByRole('link', { name: '1 solicitud de amistad' })).toHaveAttribute('href', '/gente')
    expect(await within(left).findByText('invitaciones disponibles', { exact: false })).toHaveTextContent('9')

    const right = screen.getByRole('complementary', { name: 'Columna derecha' })
    expect(within(right).getByRole('link', { name: 'Buscar amigos' })).toHaveAttribute('href', '/gente')
    expect(within(right).getByRole('button', { name: 'Aceptar a Carla' })).toBeInTheDocument()
  })

  it('changes the status from the status bar', async () => {
    const fetchMock = mockApi({
      ...baseRoutes,
      'PUT /api/profile/status': () => ({
        body: { profile: { id: 1, status: 'De viaje', status_updated_at: '2026-10-25 10:00:00' } },
      }),
    })
    const user = userEvent.setup()
    renderAt(<Home />, '/inicio')

    await user.type(await screen.findByRole('textbox', { name: 'Tu estado' }), 'De viaje')
    await user.click(screen.getByRole('button', { name: 'Cambiar' }))

    expect(await screen.findByText(/^hace /, { selector: '.statusbar time' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Cambiar' })).not.toBeInTheDocument()
    const call = fetchMock.mock.calls.find(([path]) => path === '/api/profile/status')
    expect(sentBody(call ?? [])).toEqual({ status: 'De viaje' })
  })
})
