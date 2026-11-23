import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import type { LinkPreview, WallPost, WallReply } from '../../api/wall'
import { ana, mockApi, renderAt, sentBody } from '../../test/utils'
import { WallModule } from '../profile/WallModule'

const bea = { id: 2, first_name: 'Bea', last_name: 'López', avatar_url: null }

const entry = (id: number, body: string, overrides: Partial<WallReply> = {}): WallReply => ({
  id,
  parent_id: null,
  author: bea,
  body,
  link_preview: null,
  created_at: '2026-11-20 10:00:00',
  can_delete: false,
  ...overrides,
})

const thread = (id: number, body: string, replies: WallReply[] = []): WallPost => ({ ...entry(id, body), replies })

const card: LinkPreview = {
  id: 7,
  url: 'https://example.com/receta',
  title: 'Tortilla de patatas',
  description: 'Con cebolla, claro',
  site_name: 'Cocina',
  image_url: '/api/link-previews/7/image',
}

describe('Tablón: hilos, emoticonos y enlaces', () => {
  it('shows threads, collapses long ones and replies in the same thread', async () => {
    const replies = [1, 2, 3, 4].map((n) => entry(10 + n, `Respuesta ${n}`, { parent_id: 1 }))
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/users/2/wall': () => ({ body: { posts: [thread(1, 'Mira www.example.com', replies)], next_before: null } }),
      'POST /api/users/2/wall': () => ({
        status: 201,
        body: { post: entry(20, '¡Yo también!', { parent_id: 1, author: { ...ana, avatar_url: null }, can_delete: true }) },
      }),
    })
    const user = userEvent.setup()
    renderAt(<WallModule userId={2} own={false} firstName="Bea" />)

    expect(await screen.findByRole('link', { name: 'www.example.com' })).toHaveAttribute('href', 'https://www.example.com')
    expect(screen.queryByText('Respuesta 1')).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Ver las 4 respuestas' }))
    expect(screen.getByText('Respuesta 1')).toBeInTheDocument()

    await user.click(screen.getAllByRole('button', { name: 'Responder al mensaje de Bea' })[0])
    await user.type(screen.getByLabelText('Responde a Bea'), '¡Yo también!')
    await user.click(screen.getByRole('button', { name: 'Responder' }))

    const items = await screen.findAllByRole('listitem')
    expect(items.at(-1)).toHaveTextContent('¡Yo también!')
    const sent = fetchMock.mock.calls.find(([path, init]) => path === '/api/users/2/wall' && init?.method === 'POST')
    expect(sent && sentBody(sent)).toEqual({ body: '¡Yo también!', parent_id: 1, link_preview: true })
  })

  it('adds emoticons where the cursor is', async () => {
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/users/2/wall': () => ({ body: { posts: [], next_before: null } }),
      'POST /api/users/2/wall': () => ({ status: 201, body: { post: thread(3, 'Hola 😄 Bea') } }),
    })
    const user = userEvent.setup()
    renderAt(<WallModule userId={2} own={false} firstName="Bea" />)

    const field = await screen.findByLabelText('Escribe en el tablón de Bea')
    await user.type(field, 'Hola Bea')
    ;(field as HTMLTextAreaElement).setSelectionRange(5, 5)
    await user.click(screen.getByRole('button', { name: 'Emoticonos' }))
    await user.click(within(screen.getByRole('group', { name: 'Emoticonos' })).getByRole('button', { name: '😄' }))
    expect(field).toHaveValue('Hola 😄Bea')

    // 😄 ocupa dos unidades: el cursor queda en la posición 7
    await user.type(field, ' ', { initialSelectionStart: 7, initialSelectionEnd: 7 })
    await user.keyboard('{Control>}{Enter}{/Control}')
    expect(await screen.findByText('Hola 😄 Bea')).toBeInTheDocument()
    const sent = fetchMock.mock.calls.find(([path, init]) => path === '/api/users/2/wall' && init?.method === 'POST')
    expect(sent && sentBody(sent)).toMatchObject({ body: 'Hola 😄 Bea' })
  })

  it('previews a link while writing and publishes the card unless it is removed', async () => {
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/users/2/wall': () => ({ body: { posts: [], next_before: null } }),
      'POST /api/link-previews': () => ({ body: { preview: card } }),
      'POST /api/users/2/wall': (init) => {
        const { link_preview } = JSON.parse(String(init?.body)) as { link_preview: boolean }
        return { status: 201, body: { post: { ...thread(4, 'Receta: https://example.com/receta'), link_preview: link_preview ? card : null } } }
      },
    })
    const user = userEvent.setup()
    renderAt(<WallModule userId={2} own={false} firstName="Bea" />)

    await user.type(await screen.findByLabelText('Escribe en el tablón de Bea'), 'Receta: https://example.com/receta')
    expect(await screen.findByText('Tortilla de patatas', {}, { timeout: 2000 })).toBeInTheDocument()
    expect(screen.getByText('Con cebolla, claro')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Quitar la vista previa del enlace' }))
    expect(screen.queryByText('Tortilla de patatas')).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Publicar' }))

    expect(await screen.findByRole('link', { name: 'https://example.com/receta' })).toHaveAttribute('rel', 'noopener noreferrer nofollow ugc')
    expect(screen.queryByText('Tortilla de patatas')).not.toBeInTheDocument()
    const sent = fetchMock.mock.calls.find(([path, init]) => path === '/api/users/2/wall' && init?.method === 'POST')
    expect(sent && sentBody(sent)).toEqual({ body: 'Receta: https://example.com/receta', parent_id: null, link_preview: false })
    expect(fetchMock.mock.calls.filter(([path]) => path === '/api/link-previews')).toHaveLength(1)
  })

  it('shows the card of a published link', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/users/2/wall': () => ({ body: { posts: [{ ...thread(5, 'https://example.com/receta'), link_preview: card }], next_before: null } }),
    })
    renderAt(<WallModule userId={2} own={false} firstName="Bea" />)

    const title = await screen.findByText('Tortilla de patatas')
    const link = title.closest('a')
    expect(link).toHaveAttribute('href', 'https://example.com/receta')
    expect(link?.querySelector('img')).toHaveAttribute('src', '/api/link-previews/7/image')
    expect(link).toHaveTextContent('Cocina')
  })
})
