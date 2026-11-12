import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { describe, expect, it } from 'vitest'
import { AuthProvider } from '../auth/AuthContext'
import { ana, mockApi, sentBody } from '../test/utils'
import { PhotoViewer } from './PhotoViewer'

const person = (id: number, firstName: string) => ({ id, first_name: firstName, last_name: 'López', avatar_url: null })

const photo = {
  id: 10,
  album_id: 5,
  width: 800,
  height: 400,
  url: '/api/photos/10/image',
  thumb_url: '/api/photos/10/thumb',
  created_at: null,
}

const album = {
  id: 5,
  title: 'Verano',
  description: '',
  owner: { id: 2, first_name: 'Bea', last_name: 'López' },
  is_owner: false,
  created_at: null,
  updated_at: null,
  photos: [photo],
}

const tag = (id: number, user: ReturnType<typeof person>, canRemove: boolean) => ({
  id,
  user,
  x: 10,
  y: 10,
  width: 20,
  height: 40,
  can_remove: canRemove,
})

const comment = { id: 50, author: person(2, 'Bea'), body: '¡Qué día!', created_at: '2026-11-12 10:00:00', can_delete: false }

function routes(overrides = {}) {
  return {
    'GET /api/auth/me': () => ({ body: { user: ana } }),
    'GET /api/albums/5': () => ({ body: { album } }),
    'GET /api/profiles/2': () => ({ status: 404, body: { message: 'No encontrado' } }),
    'GET /api/photos/10': () => ({
      body: {
        photo: { ...photo, album_title: 'Verano', owner_id: 2 },
        tags: [tag(1, person(2, 'Bea'), false)],
        comments: [comment],
      },
    }),
    ...overrides,
  }
}

describe('PhotoViewer tags and comments', () => {
  it('shows who is in the photo and its comments', async () => {
    mockApi(routes())
    renderViewer()

    expect(await screen.findByText('En esta foto:')).toBeInTheDocument()
    const inPhoto = screen.getByText('En esta foto:').closest('p') as HTMLElement
    expect(within(inPhoto).getByRole('link', { name: 'Bea López' })).toHaveAttribute('href', '/perfil/2')
    expect(screen.queryByRole('button', { name: /Quitar/ })).not.toBeInTheDocument()

    const comments = screen.getByRole('region', { name: /Comentarios/ })
    expect(comments).toHaveTextContent('(1)')
    expect(within(comments).getByText('¡Qué día!')).toBeInTheDocument()
    expect(within(comments).queryByRole('button', { name: /Borrar/ })).not.toBeInTheDocument()
  })

  it('tags a friend in a square area by default', async () => {
    const fetchMock = mockApi(
      routes({
        'GET /api/photos/10/taggable': () => ({ body: { people: [person(1, 'Ana'), person(3, 'Carla')] } }),
        'POST /api/photos/10/tags': () => ({
          status: 201,
          body: { tags: [tag(1, person(2, 'Bea'), false), tag(2, person(3, 'Carla'), true)] },
        }),
      }),
    )
    const user = userEvent.setup()
    renderViewer()

    await user.click(await screen.findByRole('button', { name: /Etiquetar a alguien/ }))
    const select = await screen.findByLabelText('¿Quién sale?')
    expect(within(select).getByRole('option', { name: 'Yo' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Etiquetar' })).toBeDisabled()

    await user.selectOptions(select, '3')
    await user.click(screen.getByRole('button', { name: 'Etiquetar' }))

    expect(await screen.findByRole('link', { name: 'Carla López' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Quitar la etiqueta de Carla López' })).toBeInTheDocument()
    const call = fetchMock.mock.calls.find(([path]) => path === '/api/photos/10/tags')
    // Foto apaisada 2:1: la zona cuadrada ocupa el 20 % del ancho y el 40 % del alto, centrada
    expect(call && sentBody(call)).toEqual({ user_id: 3, x: 40, y: 30, width: 20, height: 40 })
  })

  it('lets the tagged person remove their tag', async () => {
    mockApi(
      routes({
        'GET /api/photos/10': () => ({
          body: { photo: { ...photo, album_title: 'Verano', owner_id: 2 }, tags: [tag(4, person(1, 'Ana'), true)], comments: [] },
        }),
        'DELETE /api/photo-tags/4': () => ({ body: { message: 'Etiqueta quitada.', tags: [] } }),
      }),
    )
    const user = userEvent.setup()
    renderViewer()

    await user.click(await screen.findByRole('button', { name: 'Quitar mi etiqueta' }))

    expect(await screen.findByText('Etiqueta quitada.')).toBeInTheDocument()
    expect(screen.queryByText('En esta foto:')).not.toBeInTheDocument()
  })

  it('posts and deletes a comment', async () => {
    const mine = { id: 51, author: person(1, 'Ana'), body: 'Me encanta', created_at: '2026-11-12 11:00:00', can_delete: true }
    const fetchMock = mockApi(
      routes({
        'POST /api/photos/10/comments': () => ({ status: 201, body: { comments: [comment, mine] } }),
        'DELETE /api/photo-comments/51': () => ({ body: { message: 'Comentario borrado.', comments: [comment] } }),
      }),
    )
    const user = userEvent.setup()
    renderViewer()

    const comments = await screen.findByRole('region', { name: /Comentarios/ })
    expect(within(comments).getByRole('button', { name: 'Comentar' })).toBeDisabled()
    await user.type(within(comments).getByLabelText('Escribe un comentario'), 'Me encanta')
    await user.click(within(comments).getByRole('button', { name: 'Comentar' }))

    expect(await within(comments).findByText('Me encanta')).toBeInTheDocument()
    expect(within(comments).getByLabelText('Escribe un comentario')).toHaveValue('')
    const call = fetchMock.mock.calls.find(([path]) => path === '/api/photos/10/comments')
    expect(call && sentBody(call)).toEqual({ body: 'Me encanta' })

    await user.click(within(comments).getByRole('button', { name: 'Borrar el comentario de Ana' }))
    expect(within(comments).queryByText('Me encanta')).not.toBeInTheDocument()
  })
})

function renderViewer() {
  return render(
    <MemoryRouter initialEntries={['/albumes/5/fotos/10']}>
      <AuthProvider>
        <Routes>
          <Route path="/albumes/:albumId/fotos/:photoId" element={<PhotoViewer />} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}
