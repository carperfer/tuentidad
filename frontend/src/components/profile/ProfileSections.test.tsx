import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import type { Profile } from '../../api/profile'
import { ana, mockApi, renderAt, sentBody } from '../../test/utils'
import { NetworksEditor } from './NetworksEditor'
import { PersonalSpace } from './PersonalSpace'
import { ProfileNetworks } from './ProfileNetworks'
import { StatusEditor } from './StatusEditor'
import { WallModule } from './WallModule'

const profile = (overrides: Partial<Profile> = {}): Profile =>
  ({
    id: 1,
    first_name: 'Ana',
    last_name: 'García',
    birthdate: null,
    bio: null,
    status: 'De exámenes',
    status_updated_at: '2026-11-19 10:00:00',
    avatar_url: null,
    is_owner: true,
    relationship: 'self',
    request_id: null,
    networks: { university: [], work: [], school: [] },
    links: [],
    visibility: { birthdate: 'friends', bio: 'public', status: 'public', avatar: 'public', networks: 'friends', links: 'friends' },
    ...overrides,
  }) as Profile

const bea = { id: 2, first_name: 'Bea', last_name: 'López', avatar_url: null }

describe('Perfil: Redes, estado, espacio personal y tablón', () => {
  it('shows studies and work with their years and the social links', () => {
    mockApi({})
    renderAt(
      <ProfileNetworks
        profile={profile({
          networks: {
            university: [{ name: 'UNED', detail: 'Antropología', start_year: 2006, end_year: null }],
            work: [{ name: 'Abogados Sauceda', detail: '', start_year: null, end_year: 2001 }],
            school: [{ name: 'San José', detail: '', start_year: 1990, end_year: 1992 }],
          },
          links: [{ url: 'https://instagram.com/ana', network: 'Instagram' }],
        })}
      />,
    )

    const redes = screen.getByRole('region', { name: 'Redes' })
    expect(within(redes).getByText('Estudios universitarios')).toBeInTheDocument()
    expect(within(redes).getByText('2006-actualmente')).toBeInTheDocument()
    expect(within(redes).getByText('Hasta 2001')).toBeInTheDocument()
    expect(within(redes).getByText('1990-1992')).toBeInTheDocument()
    expect(within(redes).getByRole('link', { name: 'Instagram' })).toHaveAttribute('href', 'https://instagram.com/ana')
  })

  it('changes the status from the own profile', async () => {
    const fetchMock = mockApi({
      'PUT /api/profile/status': () => ({ body: { profile: profile({ status: 'De vacaciones' }) } }),
    })
    const user = userEvent.setup()
    let saved: Profile | null = null
    renderAt(<StatusEditor profile={profile()} onChange={(result) => (saved = result)} />)

    expect(screen.getByRole('button', { name: 'Cambiar' })).toBeDisabled()
    await user.clear(screen.getByLabelText('Tu estado'))
    await user.type(screen.getByLabelText('Tu estado'), 'De vacaciones')
    await user.click(screen.getByRole('button', { name: 'Cambiar' }))

    await screen.findByRole('button', { name: 'Cambiar' })
    expect(saved).not.toBeNull()
    const call = fetchMock.mock.calls.find(([path]) => path === '/api/profile/status')
    expect(call && sentBody(call)).toEqual({ status: 'De vacaciones' })
  })

  it('writes on a friend wall and lets the author delete it', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/users/2/wall': () => ({ body: { posts: [], next_before: null } }),
      'POST /api/users/2/wall': () => ({
        status: 201,
        body: { post: { id: 5, author: { ...ana, avatar_url: null }, body: '¡Felicidades!', created_at: '2026-11-19 10:00:00', can_delete: true } },
      }),
      'DELETE /api/wall-posts/5': () => ({ body: { message: 'Mensaje borrado.' } }),
    })
    const user = userEvent.setup()
    renderAt(<WallModule userId={2} own={false} firstName="Bea" />)

    expect(await screen.findByText('Sé el primero en escribir a Bea.')).toBeInTheDocument()
    await user.type(screen.getByLabelText('Escribe en el tablón de Bea'), '¡Felicidades!')
    await user.click(screen.getByRole('button', { name: 'Publicar' }))
    expect(await screen.findByText('¡Felicidades!')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Borrar el mensaje de Ana' }))
    expect(screen.queryByText('¡Felicidades!')).not.toBeInTheDocument()
  })

  it('pages through the personal space one video at a time', async () => {
    const video = (id: number, title: string) => ({ id, youtube_id: 'dQw4w9WgXcQ', title, created_at: '2026-11-19 10:00:00', owner: bea, is_owner: false })
    mockApi({
      'GET /api/users/2/videos': () => ({ body: { videos: [video(9, 'Echo Beach'), video(8, 'Otro vídeo')], next_before: null, total: 2 } }),
    })
    const user = userEvent.setup()
    renderAt(<PersonalSpace userId={2} own={false} firstName="Bea" />)

    expect(await screen.findByRole('region', { name: 'Espacio personal de Bea' })).toHaveTextContent('Echo Beach')
    expect(screen.getByText('1 de 2')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Siguiente' }))
    expect(screen.getByText('2 de 2')).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Espacio personal de Bea' })).toHaveTextContent('Otro vídeo')
  })

  it('saves studies, work and social links with their privacy', async () => {
    const fetchMock = mockApi({
      'PUT /api/profile/networks': () => ({ body: { profile: profile() } }),
      'PUT /api/profile/links': () => ({ body: { profile: profile({ links: [{ url: 'https://instagram.com/ana', network: 'Instagram' }] }) } }),
    })
    const user = userEvent.setup()
    renderAt(<NetworksEditor profile={profile()} onSaved={() => undefined} />)

    await user.click(screen.getByRole('button', { name: 'Añadir estudios o trabajo' }))
    await user.type(screen.getByLabelText('Nombre (universidad, empresa o colegio)'), 'UNED')
    await user.type(screen.getByLabelText('Desde (año)'), '2006')
    await user.type(screen.getByLabelText('Enlace 1'), 'https://instagram.com/ana')
    await user.selectOptions(screen.getAllByLabelText('Quién puede verlos')[1], 'Todos los usuarios')
    await user.click(screen.getByRole('button', { name: 'Guardar redes' }))

    expect(await screen.findByText('Redes guardadas.')).toBeInTheDocument()
    const networks = fetchMock.mock.calls.find(([path]) => path === '/api/profile/networks')
    expect(networks && sentBody(networks)).toEqual({
      visibility: 'friends',
      items: [{ kind: 'university', name: 'UNED', detail: '', start_year: 2006, end_year: null }],
    })
    const links = fetchMock.mock.calls.find(([path]) => path === '/api/profile/links')
    expect(links && sentBody(links)).toEqual({ visibility: 'public', links: ['https://instagram.com/ana'] })
  })
})
