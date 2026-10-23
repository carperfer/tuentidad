import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AuthProvider } from '../auth/AuthContext'
import { mockApi, sentBody } from '../test/utils'
import { Profile } from './Profile'

const ownerProfile = {
  id: 1,
  first_name: 'Ana',
  last_name: 'García',
  birthdate: '1990-01-01',
  bio: 'Una bio',
  status: 'Hola',
  avatar_url: null,
  is_owner: true,
  visibility: {
    birthdate: 'friends',
    bio: 'public',
    status: 'public',
    avatar: 'public',
  },
}

describe('Profile', () => {
  beforeEach(() => {
    mockApi({
      'GET /api/auth/me': () => ({
        body: { user: { id: 1, email: 'ana@example.com', first_name: 'Ana', last_name: 'García' } },
      }),
      'GET /api/profiles/1': () => ({ body: { profile: ownerProfile } }),
      'PUT /api/profile': () => ({ body: { profile: { ...ownerProfile, bio: 'Me gusta viajar.' } } }),
    })
  })

  it('edits profile details with field-level privacy options', async () => {
    const user = userEvent.setup()
    renderProfilePage()

    const bio = await screen.findByRole('textbox', { name: 'Sobre mí' })
    await user.clear(bio)
    await user.type(bio, 'Me gusta viajar.')
    await user.selectOptions(screen.getByLabelText('Visibilidad: bio'), 'friends')
    await user.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    await screen.findByText('Tu perfil se ha actualizado.')
    const fetchMock = vi.mocked(globalThis.fetch)
    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith('/api/profile', expect.objectContaining({ method: 'PUT' })))
    const updateCall = fetchMock.mock.calls.find(([, init]) => init?.method === 'PUT')
    expect(sentBody(updateCall ?? [])).toMatchObject({
      bio: 'Me gusta viajar.',
      bio_visibility: 'friends',
    })
  })

  it('does not show edit controls for a different user profile', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: null } }),
      'GET /api/profiles/2': () => ({
        body: {
          profile: {
            ...ownerProfile,
            id: 2,
            is_owner: false,
            bio: null,
            status: null,
            birthdate: null,
            visibility: undefined,
          },
        },
      }),
    })
    renderProfilePage('/perfil/2')

    await screen.findByText('Algunos datos solo son visibles para sus amigos.')
    expect(screen.queryByRole('button', { name: 'Guardar cambios' })).not.toBeInTheDocument()
  })
})

function renderProfilePage(path = '/perfil/1') {
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
