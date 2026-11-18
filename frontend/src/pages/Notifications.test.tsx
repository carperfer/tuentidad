import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { describe, expect, it } from 'vitest'
import { Layout } from '../components/Layout'
import { ana, mockApi, noUnread, renderAt } from '../test/utils'
import { Notifications } from './Notifications'

const bea = { id: 2, first_name: 'Bea', last_name: 'López', avatar_url: null }

describe('Notifications', () => {
  it('lists the notifications and marks them all as read from the button', async () => {
    let unread = { ...noUnread, total: 2, photo_tag: 1, friend_request: 1 }
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/notifications/unread': () => ({ body: { unread } }),
      'GET /api/notifications': () => ({
        body: {
          notifications: [
            {
              id: 2,
              type: 'photo_tag',
              actor: bea,
              photo: { id: 10, album_id: 5, thumb_url: '/api/photos/10/thumb' },
              event: null,
              created_at: '2026-11-12 10:00:00',
              read: false,
            },
            {
              id: 1,
              type: 'friend_request',
              actor: bea,
              photo: null,
              event: null,
              created_at: '2026-11-11 10:00:00',
              read: false,
            },
          ],
          next_before: null,
          unread,
        },
      }),
      'POST /api/notifications/read': () => {
        unread = noUnread
        return { body: { unread } }
      },
    })
    const user = userEvent.setup()
    renderNotifications()

    expect(await screen.findByRole('link', { name: 'te ha etiquetado en una foto' })).toHaveAttribute(
      'href',
      '/albumes/5/fotos/10',
    )
    expect(screen.getByRole('link', { name: 'te ha enviado una solicitud de amistad' })).toHaveAttribute('href', '/gente')
    expect(screen.getByRole('link', { name: /Avisos 2 sin leer/ })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Marcar todos como leídos' }))

    await waitFor(() => expect(screen.getByRole('link', { name: 'Avisos' })).not.toHaveTextContent('sin leer'))
    expect(screen.queryByText('(nuevo)')).not.toBeInTheDocument()
  })

  it('filters by type, loads more and marks a notification as read when opened', async () => {
    const accepted = (id: number) => ({
      id,
      type: 'friend_accepted',
      actor: bea,
      photo: null,
      event: null,
      created_at: '2026-11-11 10:00:00',
      read: false,
    })
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/notifications': () => ({ body: { notifications: [accepted(30)], next_before: 30, unread: noUnread } }),
      'GET /api/notifications?before=30': () => ({
        body: { notifications: [accepted(12)], next_before: null, unread: noUnread },
      }),
      'GET /api/notifications?group=events': () => ({ body: { notifications: [], next_before: null, unread: noUnread } }),
      'POST /api/notifications/30/read': () => ({ body: { unread: noUnread } }),
    })
    const user = userEvent.setup()
    renderNotifications()

    // Como con RequireAuth en la app, se espera a la sesión (la topbar) antes de usar la página
    await screen.findByRole('link', { name: 'Avisos' })
    await user.click(await screen.findByRole('button', { name: 'Eventos' }))
    expect(await screen.findByText('No tienes avisos de este tipo.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Eventos', pressed: true })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Todos' }))

    await user.click(await screen.findByRole('button', { name: 'Ver más' }))
    expect(await screen.findAllByRole('link', { name: 'ha aceptado tu solicitud de amistad' })).toHaveLength(2)
    expect(screen.queryByRole('button', { name: 'Ver más' })).not.toBeInTheDocument()

    await user.click(screen.getAllByRole('link', { name: 'ha aceptado tu solicitud de amistad' })[0])
    expect(fetchMock).toHaveBeenCalledWith('/api/notifications/30/read', expect.objectContaining({ method: 'POST' }))
    expect(await screen.findByText('Otra página')).toBeInTheDocument()
  })

  it('shows the unread count in the topbar and the new tags in the user card', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/notifications/unread': () => ({ body: { unread: { ...noUnread, total: 3, photo_tag: 2, photo_comment: 1 } } }),
      'GET /api/notifications': () => ({ body: { notifications: [], next_before: null, unread: noUnread } }),
    })
    renderNotifications()

    expect(await screen.findByRole('link', { name: 'Avisos 3 sin leer' })).toHaveAttribute('href', '/avisos')
    expect(screen.getByRole('link', { name: '2 etiquetas nuevas' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: '1 comentario nuevo' })).toBeInTheDocument()
    expect(screen.getByText(/No tienes avisos/)).toBeInTheDocument()
  })
})

function renderNotifications() {
  return renderAt(
    <Routes>
      <Route element={<Layout />}>
        <Route path="/avisos" element={<Notifications />} />
        <Route path="*" element={<p>Otra página</p>} />
      </Route>
    </Routes>,
    '/avisos',
  )
}
