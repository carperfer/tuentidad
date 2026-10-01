import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { RequireGuest } from '../components/RequireAuth'
import { ana, mockApi, renderAt, sentBody } from '../test/utils'
import { Landing } from './Landing'

function renderLanding() {
  return renderAt(
    <Routes>
      <Route
        path="/"
        element={
          <RequireGuest>
            <Landing />
          </RequireGuest>
        }
      />
      <Route path="/inicio" element={<p>Página de inicio</p>} />
    </Routes>,
  )
}

describe('Landing', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('inicia sesión y lleva al inicio', async () => {
    const fetchMock = mockApi({ 'POST /api/auth/login': () => ({ body: { user: ana } }) })
    renderLanding()

    await userEvent.type(await screen.findByLabelText('Email'), 'ana@example.com')
    await userEvent.type(screen.getByLabelText('Contraseña'), 'secreta')
    await userEvent.click(screen.getByLabelText(/Recordarme/))
    await userEvent.click(screen.getByRole('button', { name: 'Entrar' }))

    expect(await screen.findByText('Página de inicio')).toBeInTheDocument()
    const login = fetchMock.mock.calls.find(([url]) => url === '/api/auth/login')!
    expect(sentBody(login)).toEqual({ email: 'ana@example.com', password: 'secreta', remember: true })
  })

  it('muestra el error si las credenciales no son correctas', async () => {
    mockApi({ 'POST /api/auth/login': () => ({ status: 401, body: { message: 'Email o contraseña incorrectos.' } }) })
    renderLanding()

    await userEvent.type(await screen.findByLabelText('Email'), 'ana@example.com')
    await userEvent.type(screen.getByLabelText('Contraseña'), 'mala')
    await userEvent.click(screen.getByRole('button', { name: 'Entrar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Email o contraseña incorrectos.')
  })

  it('con sesión iniciada redirige al inicio', async () => {
    mockApi({ 'GET /api/auth/me': () => ({ body: { user: ana } }) })
    renderLanding()

    expect(await screen.findByText('Página de inicio')).toBeInTheDocument()
  })
})
