import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { describe, expect, it } from 'vitest'
import { AuthProvider } from '../auth/AuthContext'
import { ana, mockApi, sentBody } from '../test/utils'
import { Albums } from './Albums'

const profile = (id: number, isOwner: boolean) => ({
  id,
  first_name: id === 1 ? 'Ana' : 'Bea',
  last_name: 'García',
  birthdate: null,
  bio: null,
  status: null,
  avatar_url: null,
  is_owner: isOwner,
})

describe('Albums', () => {
  it('lists albums and lets the owner create a new one', async () => {
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/profiles/1': () => ({ body: { profile: profile(1, true) } }),
      'GET /api/users/1/albums': () => ({
        body: {
          albums: [{ id: 5, title: 'Verano', description: '', updated_at: null, photo_count: 3, cover_photo_id: 10 }],
        },
      }),
      'POST /api/albums': () => ({ status: 201, body: { album: { id: 6 } } }),
    })
    const user = userEvent.setup()
    renderAt('/perfil/1/fotos')

    expect(await screen.findByRole('link', { name: /Verano/ })).toHaveAttribute('href', '/albumes/5')
    expect(screen.getByText('3 fotos')).toBeInTheDocument()

    await user.type(screen.getByRole('textbox', { name: 'Título' }), 'Navidad')
    await user.click(screen.getByRole('button', { name: 'Crear álbum' }))

    await screen.findByText('Página del álbum')
    const createCall = fetchMock.mock.calls.find(([path, init]) => path === '/api/albums' && init?.method === 'POST')
    expect(sentBody(createCall ?? [])).toEqual({ title: 'Navidad', description: '' })
  })

  it('explains that only friends can see the photos', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/profiles/2': () => ({ body: { profile: profile(2, false) } }),
      'GET /api/users/2/albums': () => ({ status: 403, body: { message: 'Solo sus amigos pueden ver sus fotos.' } }),
    })
    renderAt('/perfil/2/fotos')

    expect(await screen.findByRole('alert')).toHaveTextContent('Solo sus amigos pueden ver sus fotos.')
    expect(screen.queryByRole('button', { name: 'Crear álbum' })).not.toBeInTheDocument()
  })
})

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>
        <Routes>
          <Route path="/perfil/:id/fotos" element={<Albums />} />
          <Route path="/albumes/:id" element={<p>Página del álbum</p>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}
