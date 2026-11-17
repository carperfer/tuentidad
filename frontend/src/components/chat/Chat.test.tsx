import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { beforeEach, describe, expect, it } from 'vitest'
import { ana, mockApi, renderAt, sentBody } from '../../test/utils'
import { Layout } from '../Layout'

const bea = { id: 2, first_name: 'Bea', last_name: 'López', avatar_url: null, online: true, unread: 1 }
const carla = { id: 3, first_name: 'Carla', last_name: 'Ruiz', avatar_url: null, online: false, unread: 0 }

function renderShell() {
  return renderAt(
    <Routes>
      <Route element={<Layout />}>
        <Route path="*" element={<p>Página</p>} />
      </Route>
    </Routes>,
    '/inicio',
  )
}

describe('Chat', () => {
  beforeEach(() => sessionStorage.clear())

  it('opens a conversation from the topbar and sends a message', async () => {
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/chat': () => ({ body: { contacts: [bea, carla], online: 1, unread: 1 } }),
      'GET /api/chat/2/messages': () => ({
        body: { messages: [{ id: 7, sender_id: 2, mine: false, body: '¿Vienes?', created_at: '2026-11-17 10:00:00' }] },
      }),
      'POST /api/chat/2/messages': () => ({
        status: 201,
        body: { message: { id: 8, sender_id: 1, mine: true, body: 'Voy', created_at: '2026-11-17 10:01:00' } },
      }),
    })
    const user = userEvent.setup()
    renderShell()

    const toggle = await screen.findByRole('button', { name: /Chat \(1\) amigo conectado 1 mensaje sin leer/ })
    await user.click(toggle)
    expect(screen.getByRole('button', { name: /Carla Ruiz \(desconectado\)/ })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: /Bea López \(conectado\)/ }))

    const window = await screen.findByRole('region', { name: 'Chat con Bea López' })
    expect(await within(window).findByText('¿Vienes?')).toBeInTheDocument()
    expect(within(window).getByText('Bea:')).toBeInTheDocument()

    await user.type(within(window).getByLabelText('Mensaje para Bea'), 'Voy{Enter}')
    expect(await within(window).findByText('Voy')).toBeInTheDocument()
    expect(within(window).getByText('Ana:')).toBeInTheDocument()
    const call = fetchMock.mock.calls.find(([path, init]) => path === '/api/chat/2/messages' && init?.method === 'POST')
    expect(call && sentBody(call)).toEqual({ body: 'Voy' })
  })

  it('minimizes and closes a conversation from the dock', async () => {
    sessionStorage.setItem('tuentidad:chat-windows', JSON.stringify([{ friendId: 2, minimized: false }]))
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/chat': () => ({ body: { contacts: [bea], online: 1, unread: 0 } }),
      'GET /api/chat/2/messages': () => ({ body: { messages: [] } }),
    })
    const user = userEvent.setup()
    renderShell()

    expect(await screen.findByText('Escribe el primer mensaje a Bea.')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Minimizar el chat con Bea López' }))
    expect(screen.queryByRole('region', { name: 'Chat con Bea López' })).not.toBeInTheDocument()

    const tab = screen.getByRole('button', { name: /Bea/, expanded: false })
    await user.click(tab)
    await user.click(await screen.findByRole('button', { name: 'Cerrar el chat con Bea López' }))
    expect(screen.queryByRole('region', { name: 'Conversaciones abiertas' })).not.toBeInTheDocument()
  })
})
