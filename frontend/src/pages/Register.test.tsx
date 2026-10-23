import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ana, mockApi, renderAt, sentBody } from '../test/utils'
import { Register } from './Register'

const TOKEN = 'a'.repeat(64)

function renderRegister() {
  return renderAt(
    <Routes>
      <Route path="/registro/:token" element={<Register />} />
      <Route path="/inicio" element={<p>Página de inicio</p>} />
    </Routes>,
    `/registro/${TOKEN}`,
  )
}

describe('Register', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('avisa si la invitación no es válida', async () => {
    mockApi({
      [`GET /api/invitations/${TOKEN}`]: () => ({ status: 404, body: { message: 'La invitación ha caducado.' } }),
    })
    renderRegister()

    expect(await screen.findByRole('heading', { name: 'Invitación no válida' })).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent('La invitación ha caducado.')
  })

  it('muestra quién invita y los errores de cada campo', async () => {
    mockApi({
      [`GET /api/invitations/${TOKEN}`]: () => ({ body: { email: 'bea@example.com', inviter: 'Ana García', welcome: false } }),
      'POST /api/auth/register': () => ({
        status: 422,
        body: { message: 'Revisa', errors: { birthdate: 'Tienes que tener al menos 14 años para usar tuentidad.' } },
      }),
    })
    renderRegister()

    expect(await screen.findByText('Ana García')).toBeInTheDocument()
    expect(screen.getByText('bea@example.com')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Crear cuenta' }))

    const birthdate = screen.getByLabelText('Fecha de nacimiento')
    expect(await screen.findByText(/al menos 14 años/)).toBeInTheDocument()
    expect(birthdate).toHaveAttribute('aria-invalid', 'true')
  })

  it('si viene de un perfil de bienvenida, avisa de que serán amigos', async () => {
    mockApi({
      [`GET /api/invitations/${TOKEN}`]: () => ({
        body: { email: 'marta@example.com', inviter: 'Lucía Martín', welcome: true },
      }),
    })
    renderRegister()

    expect(await screen.findByText(/ha aceptado tu solicitud de amistad/)).toBeInTheDocument()
  })

  it('crea la cuenta y entra en el inicio', async () => {
    const fetchMock = mockApi({
      [`GET /api/invitations/${TOKEN}`]: () => ({ body: { email: 'bea@example.com', inviter: null, welcome: false } }),
      'POST /api/auth/register': () => ({ status: 201, body: { user: { ...ana, email: 'bea@example.com' } } }),
    })
    renderRegister()

    await userEvent.type(await screen.findByLabelText('Nombre'), 'Bea')
    await userEvent.type(screen.getByLabelText('Apellidos'), 'Ruiz')
    await userEvent.type(screen.getByLabelText('Fecha de nacimiento'), '2000-02-29')
    await userEvent.type(screen.getByLabelText('Contraseña'), 'Montaña-Roja-42')
    await userEvent.type(screen.getByLabelText('Repite la contraseña'), 'Montaña-Roja-42')
    await userEvent.click(screen.getByLabelText(/Acepto las condiciones/))
    await userEvent.click(screen.getByRole('button', { name: 'Crear cuenta' }))

    expect(await screen.findByText('Página de inicio')).toBeInTheDocument()
    const call = fetchMock.mock.calls.find(([url]) => url === '/api/auth/register')!
    expect(sentBody(call)).toEqual({
      token: TOKEN,
      first_name: 'Bea',
      last_name: 'Ruiz',
      birthdate: '2000-02-29',
      password: 'Montaña-Roja-42',
      password_confirm: 'Montaña-Roja-42',
      accept_terms: true,
    })
  })
})
