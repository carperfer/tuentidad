import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ana, mockApi, renderAt, sentBody } from '../test/utils'
import { ProfileEdit } from './ProfileEdit'

const ownProfile = {
  id: 1,
  first_name: 'Ana',
  last_name: 'García',
  birthdate: '1990-01-01',
  bio: 'Una bio',
  status: 'Hola',
  status_updated_at: null,
  avatar_url: null,
  is_owner: true,
  relationship: 'self',
  request_id: null,
  visibility: { birthdate: 'friends', bio: 'public', status: 'public', avatar: 'public' },
}

describe('ProfileEdit', () => {
  it('edits profile details with field-level privacy options', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/profiles/1': () => ({ body: { profile: ownProfile } }),
      'PUT /api/profile': () => ({ body: { profile: { ...ownProfile, bio: 'Me gusta viajar.' } } }),
    })
    const user = userEvent.setup()
    renderAt(<ProfileEdit />, '/perfil/editar')

    const bio = await screen.findByRole('textbox', { name: 'Sobre mí' })
    await user.clear(bio)
    await user.type(bio, 'Me gusta viajar.')
    await user.selectOptions(screen.getByLabelText('Visibilidad: Sobre mí'), 'friends')
    await user.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    await screen.findByText('Tu perfil se ha actualizado.')
    const fetchMock = vi.mocked(globalThis.fetch)
    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith('/api/profile', expect.objectContaining({ method: 'PUT' })))
    const updateCall = fetchMock.mock.calls.find(([, init]) => init?.method === 'PUT')
    expect(sentBody(updateCall ?? [])).toMatchObject({ bio: 'Me gusta viajar.', bio_visibility: 'friends' })
  })
})
