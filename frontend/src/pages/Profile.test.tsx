import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { describe, expect, it } from 'vitest'
import { AuthProvider } from '../auth/AuthContext'
import { ana, mockApi } from '../test/utils'
import { Profile } from './Profile'

const person = (id: number, firstName: string) => ({ id, first_name: firstName, last_name: 'López', avatar_url: null })

const beaProfile = {
  id: 2,
  first_name: 'Bea',
  last_name: 'López',
  birthdate: '1992-05-04',
  bio: 'Me gusta viajar.',
  status: 'De exámenes',
  status_updated_at: '2026-10-25 10:00:00',
  avatar_url: null,
  is_owner: false,
  relationship: 'friend',
  request_id: null,
}

function friendRoutes(overrides = {}) {
  return {
    'GET /api/auth/me': () => ({ body: { user: ana } }),
    'GET /api/profiles/2': () => ({ body: { profile: beaProfile } }),
    'GET /api/users/2/photos?limit=6': () => ({
      body: {
        photos: [{ id: 10, album_id: 5, width: 800, height: 600, url: '/api/photos/10/image', thumb_url: '/api/photos/10/thumb', created_at: null }],
        total: 12,
      },
    }),
    'GET /api/users/2/albums': () => ({
      body: { albums: [{ id: 5, title: 'Verano', description: '', updated_at: null, photo_count: 12, cover_photo_id: 10 }] },
    }),
    'GET /api/users/2/friends?limit=6': () => ({ body: { friends: [person(1, 'Ana'), person(3, 'Carla')], total: 9 } }),
    'GET /api/users/2/mutual-friends?limit=6': () => ({ body: { friends: [person(3, 'Carla')], total: 1 } }),
    'GET /api/users/2/tagged-photos?limit=10': () => ({
      body: {
        photos: [{ id: 30, album_id: 7, width: 800, height: 600, url: '/api/photos/30/image', thumb_url: '/api/photos/30/thumb', created_at: null }],
        total: 14,
      },
    }),
    ...overrides,
  }
}

describe('Profile', () => {
  it('lays out a friend profile in the three columns of the guide', async () => {
    mockApi(friendRoutes())
    renderProfile('/perfil/2')

    expect(await screen.findByRole('heading', { level: 1, name: /Bea López/ })).toHaveTextContent('Sois amigos')
    expect(screen.getByText('De exámenes')).toBeInTheDocument()

    const left = screen.getByRole('complementary', { name: 'Columna izquierda' })
    expect(within(left).getByText('4 de mayo de 1992')).toBeInTheDocument()
    expect(within(left).getByText('Me gusta viajar.')).toBeInTheDocument()

    const right = screen.getByRole('complementary', { name: 'Columna derecha' })
    const photos = await within(right).findByRole('region', { name: 'Fotos' })
    expect(within(photos).getByRole('link', { name: 'Foto 1 de Bea' })).toHaveAttribute('href', '/albumes/5/fotos/10')
    expect(within(photos).getByRole('link', { name: /Ver todas/ })).toHaveTextContent('(12)')
    expect(within(right).getByRole('region', { name: 'Amigos en común' })).toHaveTextContent('Carla')
    expect(within(right).getByRole('link', { name: /Ver todos/ })).toHaveAttribute('href', '/perfil/2/amigos')

    const main = screen.getByRole('main')
    const tagged = within(main).getByRole('region', { name: /Fotos en las que sale Bea/ })
    expect(within(tagged).getByRole('link', { name: /foto 1/ })).toHaveAttribute('href', '/albumes/7/fotos/30')
    expect(within(tagged).getByRole('link', { name: /Ver todas/ })).toHaveAttribute('href', '/perfil/2/etiquetas')
  })

  it('opens the albums dropdown', async () => {
    mockApi(friendRoutes())
    const user = userEvent.setup()
    renderProfile('/perfil/2')

    await user.click(await screen.findByRole('button', { name: /Álbumes/ }))

    expect(screen.getByRole('link', { name: /Verano/ })).toHaveAttribute('href', '/albumes/5')
    await user.keyboard('{Escape}')
    expect(screen.queryByRole('link', { name: /Verano/ })).not.toBeInTheDocument()
  })

  it('lets a stranger send a friend request and hides friends-only modules', async () => {
    let requested = false
    const stranger = { ...beaProfile, relationship: 'none', bio: null, birthdate: null }
    mockApi(
      friendRoutes({
        'GET /api/profiles/2': () => ({
          body: { profile: requested ? { ...stranger, relationship: 'outgoing', request_id: 8 } : stranger },
        }),
        'POST /api/friend-requests': () => {
          requested = true
          return { status: 201, body: { message: 'Solicitud de amistad enviada.' } }
        },
      }),
    )
    const user = userEvent.setup()
    renderProfile('/perfil/2')

    await user.click(await screen.findByRole('button', { name: 'Añadir como amigo' }))

    expect(await screen.findByText('Solicitud de amistad enviada.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Cancelar solicitud' })).toBeInTheDocument()
    expect(screen.getByText('Solo sus amigos pueden ver sus fotos y su lista de amigos.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Fotos' })).not.toBeInTheDocument()
  })

  it('shows the owner actions on their own profile', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/profiles/1': () => ({ body: { profile: { ...beaProfile, id: 1, first_name: 'Ana', is_owner: true, relationship: 'self' } } }),
      'GET /api/users/1/photos?limit=6': () => ({ body: { photos: [], total: 0 } }),
      'GET /api/users/1/albums': () => ({ body: { albums: [] } }),
      'GET /api/users/1/friends?limit=6': () => ({ body: { friends: [], total: 0 } }),
      'GET /api/users/1/tagged-photos?limit=10': () => ({ body: { photos: [], total: 0 } }),
    })
    renderProfile('/perfil/1')

    expect(await screen.findByRole('link', { name: 'Editar mi perfil' })).toHaveAttribute('href', '/perfil/editar')
    expect(screen.getByRole('link', { name: /Mis álbumes de fotos/ })).toHaveTextContent('(0)')
    expect(await screen.findByRole('region', { name: 'Mis amigos' })).toHaveTextContent('Todavía no tienes amigos.')
    expect(screen.queryByRole('region', { name: 'Amigos en común' })).not.toBeInTheDocument()
    expect(screen.getByRole('region', { name: /Fotos en las que salgo/ })).toHaveTextContent(
      'Todavía no te han etiquetado en ninguna foto.',
    )
  })
})

function renderProfile(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>
        <Routes>
          <Route path="/perfil/:id" element={<Profile />} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}
