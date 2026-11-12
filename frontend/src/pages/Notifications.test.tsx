import { screen, waitFor } from '@testing-library/react'
import { Route, Routes } from 'react-router'
import { describe, expect, it } from 'vitest'
import { Layout } from '../components/Layout'
import { ana, mockApi, noUnread, renderAt } from '../test/utils'
import { Notifications } from './Notifications'

const bea = { id: 2, first_name: 'Bea', last_name: 'López', avatar_url: null }

describe('Notifications', () => {
  it('lists the notifications, marks them as read and clears the topbar badge', async () => {
    let unread = { ...noUnread, total: 2, photo_tag: 1, friend_request: 1 }
    const fetchMock = mockApi({
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
              created_at: '2026-11-12 10:00:00',
              read: false,
            },
            { id: 1, type: 'friend_request', actor: bea, photo: null, created_at: '2026-11-11 10:00:00', read: false },
          ],
          unread,
        },
      }),
      'POST /api/notifications/read': () => {
        unread = noUnread
        return { body: { unread } }
      },
    })
    renderAt(
      <Routes>
        <Route element={<Layout />}>
          <Route path="/avisos" element={<Notifications />} />
        </Route>
      </Routes>,
      '/avisos',
    )

    expect(await screen.findByRole('link', { name: 'te ha etiquetado en una foto' })).toHaveAttribute(
      'href',
      '/albumes/5/fotos/10',
    )
    expect(screen.getByRole('link', { name: 'te ha enviado una solicitud de amistad' })).toHaveAttribute('href', '/gente')

    await waitFor(() =>
      expect(fetchMock).toHaveBeenCalledWith('/api/notifications/read', expect.objectContaining({ method: 'POST' })),
    )
    await waitFor(() => expect(screen.getByRole('link', { name: 'Avisos' })).not.toHaveTextContent('sin leer'))
  })

  it('shows the unread count in the topbar and the new tags in the user card', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/notifications/unread': () => ({ body: { unread: { ...noUnread, total: 3, photo_tag: 2, photo_comment: 1 } } }),
      'GET /api/notifications': () => ({ body: { notifications: [], unread: noUnread } }),
    })
    renderAt(
      <Routes>
        <Route element={<Layout />}>
          <Route path="/avisos" element={<Notifications />} />
        </Route>
      </Routes>,
      '/avisos',
    )

    expect(await screen.findByRole('link', { name: 'Avisos 3 sin leer' })).toHaveAttribute('href', '/avisos')
    expect(screen.getByRole('link', { name: '2 etiquetas nuevas' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: '1 comentario nuevo' })).toBeInTheDocument()
    expect(screen.getByText(/No tienes avisos/)).toBeInTheDocument()
  })
})
