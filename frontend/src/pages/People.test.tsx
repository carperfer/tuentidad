import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { ana, mockApi, renderAt } from '../test/utils'
import { People } from './People'

describe('People', () => {
  it('searches from the query string and sends a friend request', async () => {
    let requested = false
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/friends': () => ({ body: { friends: [] } }),
      'GET /api/friend-requests': () => ({
        body: {
          incoming: [],
          outgoing: requested ? [{ id: 8, user_id: 2, first_name: 'Bea', last_name: 'López', avatar_url: null, created_at: '' }] : [],
        },
      }),
      'GET /api/friends/search?q=Bea': () => ({
        body: { users: [{ id: 2, first_name: 'Bea', last_name: 'López', avatar_url: null, relationship: requested ? 'outgoing' : 'none' }] },
      }),
      'POST /api/friend-requests': () => {
        requested = true
        return { status: 201, body: { message: 'Solicitud de amistad enviada.' } }
      },
    })
    const user = userEvent.setup()
    renderAt(<People />, '/gente?q=Bea')

    const results = await screen.findByRole('list', { name: 'Resultados de búsqueda' })
    await user.click(within(results).getByRole('button', { name: 'Añadir amigo' }))

    expect(await screen.findByText('Solicitud de amistad enviada.')).toBeInTheDocument()
    expect(await within(results).findByText('Solicitud enviada')).toBeInTheDocument()
    const sent = screen.getByRole('region', { name: /Solicitudes enviadas/ })
    expect(within(sent).getByRole('link', { name: 'Bea López' })).toHaveAttribute('href', '/perfil/2')
  })

  it('accepts a request and asks before removing a friend', async () => {
    let accepted = false
    let removed = false
    const bea = { id: 2, first_name: 'Bea', last_name: 'López', avatar_url: null }
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/friends': () => ({ body: { friends: accepted && !removed ? [bea] : [] } }),
      'GET /api/friend-requests': () => ({
        body: { incoming: accepted ? [] : [{ ...bea, id: 7, user_id: 2, created_at: '' }], outgoing: [] },
      }),
      'POST /api/friend-requests/7/accept': () => {
        accepted = true
        return { body: { message: 'Solicitud aceptada. Ya sois amigos.' } }
      },
      'DELETE /api/friends/2': () => {
        removed = true
        return { body: { message: 'Amistad eliminada.' } }
      },
    })
    const user = userEvent.setup()
    renderAt(<People />, '/gente')

    await user.click(await screen.findByRole('button', { name: 'Aceptar a Bea' }))
    expect(await screen.findByText('Solicitud aceptada. Ya sois amigos.')).toBeInTheDocument()

    await user.click(await screen.findByRole('button', { name: 'Eliminar a Bea de tus amigos' }))
    await user.click(screen.getByRole('button', { name: 'Sí, eliminar' }))

    expect(await screen.findByText('Amistad eliminada.')).toBeInTheDocument()
    expect(await screen.findByText('Todavía no tienes amigos en tuentidad. Búscalos por su nombre.')).toBeInTheDocument()
  })
})
