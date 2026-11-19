import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { describe, expect, it } from 'vitest'
import { Layout } from '../components/Layout'
import { ana, mockApi, renderAt, sentBody } from '../test/utils'
import { Conversation } from './Conversation'
import { Messages } from './Messages'
import { Videos } from './Videos'

const bea = { id: 2, first_name: 'Bea', last_name: 'López', avatar_url: null }
const message = (id: number, mine: boolean, body: string) => ({
  id,
  sender_id: mine ? 1 : 2,
  mine,
  body,
  created_at: '2026-11-19 10:00:00',
})

function renderShell(path: string) {
  return renderAt(
    <Routes>
      <Route element={<Layout />}>
        <Route path="/mensajes" element={<Messages />} />
        <Route path="/mensajes/:id" element={<Conversation />} />
        <Route path="/videos" element={<Videos />} />
      </Route>
    </Routes>,
    path,
  )
}

describe('Mensajes', () => {
  it('lists the conversations with their last message', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/chat': () => ({ body: { contacts: [{ ...bea, online: true, unread: 1 }], online: 1, unread: 1 } }),
      'GET /api/chat/conversations': () => ({
        body: { conversations: [{ person: bea, last_message: message(9, false, '¿Quedamos?'), unread: 1 }] },
      }),
    })
    renderShell('/mensajes')

    // Se busca el <main> cuando ya hay datos: al cargar la sesión, el layout lo vuelve a montar
    expect(await screen.findByText('¿Quedamos?')).toBeInTheDocument()
    const main = screen.getByRole('main')
    expect(within(main).getByRole('link', { name: 'Bea López' })).toHaveAttribute('href', '/mensajes/2')
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Mensajes (1) sin leer')
  })

  it('shows a conversation, loads older messages and sends a new one', async () => {
    const latest = Array.from({ length: 50 }, (_, index) => message(100 + index, index % 2 === 0, `Mensaje ${index + 51}`))
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/chat': () => ({ body: { contacts: [{ ...bea, online: true, unread: 0 }], online: 1, unread: 0 } }),
      'GET /api/chat/2/messages': () => ({ body: { messages: latest } }),
      'GET /api/chat/2/messages?before=100': () => ({ body: { messages: [message(50, false, 'El primero')] } }),
      'POST /api/chat/2/messages': () => ({ status: 201, body: { message: message(200, true, 'Vale, nos vemos') } }),
    })
    const user = userEvent.setup()
    renderShell('/mensajes/2')

    expect(await screen.findByText('Mensaje 51')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Ver mensajes anteriores' }))
    expect(await screen.findByText('El primero')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Ver mensajes anteriores' })).not.toBeInTheDocument()

    await user.type(screen.getByLabelText('Mensaje para Bea López'), 'Vale, nos vemos')
    await user.click(screen.getByRole('button', { name: 'Enviar' }))
    expect(await screen.findByText('Vale, nos vemos')).toBeInTheDocument()
    const call = fetchMock.mock.calls.find(([path, init]) => path === '/api/chat/2/messages' && init?.method === 'POST')
    expect(call && sentBody(call)).toEqual({ body: 'Vale, nos vemos' })
  })
})

describe('Vídeos', () => {
  it('plays a video only when asked and shares a new one', async () => {
    const video = (id: number, title: string, isOwner = false) => ({
      id,
      youtube_id: 'dQw4w9WgXcQ',
      title,
      created_at: '2026-11-19 10:00:00',
      owner: isOwner ? { ...ana, avatar_url: null } : bea,
      is_owner: isOwner,
    })
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/videos': () => ({ body: { videos: [video(1, 'Echo Beach')], next_before: null, total: 1 } }),
      'POST /api/videos': () => ({ status: 201, body: { video: video(2, 'Mi canción', true) } }),
    })
    const user = userEvent.setup()
    renderShell('/videos')

    const play = await screen.findByRole('button', { name: /Reproducir «Echo Beach»/ })
    expect(document.querySelector('iframe')).toBeNull()
    await user.click(play)
    expect(screen.getByTitle('Echo Beach')).toHaveAttribute(
      'src',
      'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1',
    )

    await user.type(screen.getByLabelText('Enlace de YouTube'), 'https://youtu.be/dQw4w9WgXcQ')
    await user.type(screen.getByLabelText('Título'), 'Mi canción')
    await user.click(screen.getByRole('button', { name: 'Compartir' }))
    expect(await screen.findByRole('button', { name: 'Borrar el vídeo «Mi canción»' })).toBeInTheDocument()
    const call = fetchMock.mock.calls.find(([path, init]) => path === '/api/videos' && init?.method === 'POST')
    expect(call && sentBody(call)).toEqual({ url: 'https://youtu.be/dQw4w9WgXcQ', title: 'Mi canción' })
  })
})
