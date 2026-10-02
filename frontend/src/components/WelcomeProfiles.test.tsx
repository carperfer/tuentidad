import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApi, renderAt, sentBody } from '../test/utils'
import { WelcomeProfiles } from './WelcomeProfiles'

const PROFILES = [
  { id: 1, first_name: 'Lucía', last_name: 'Martín', bio: 'Organiza quedadas.', avatar: '/bienvenida/lucia.svg' },
  { id: 2, first_name: 'Dani', last_name: 'Romero', bio: 'Música a tope.', avatar: '/bienvenida/dani.svg' },
]

describe('WelcomeProfiles', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('muestra los perfiles marcados como demostración', async () => {
    mockApi({ 'GET /api/welcome-profiles': () => ({ body: { profiles: PROFILES } }) })
    renderAt(<WelcomeProfiles />)

    const lucia = await screen.findByRole('article', { name: 'Lucía Martín' })
    expect(within(lucia).getByText('Perfil de demostración')).toBeInTheDocument()
    expect(screen.getByRole('article', { name: 'Dani Romero' })).toBeInTheDocument()
  })

  it('envía la solicitud de amistad con el email y el consentimiento', async () => {
    const fetchMock = mockApi({
      'GET /api/welcome-profiles': () => ({ body: { profiles: PROFILES } }),
      'POST /api/welcome-profiles/1/friend-requests': () => ({ body: { message: '¡Hecho! Revisa tu correo.' } }),
    })
    renderAt(<WelcomeProfiles />)

    const lucia = await screen.findByRole('article', { name: 'Lucía Martín' })
    await userEvent.click(within(lucia).getByRole('button', { name: 'Pedir amistad a Lucía' }))
    await userEvent.type(within(lucia).getByLabelText('Tu email'), 'marta@example.com')
    await userEvent.click(within(lucia).getByLabelText(/acepto la política de privacidad/))
    await userEvent.click(within(lucia).getByRole('button', { name: 'Enviar solicitud' }))

    expect(await within(lucia).findByRole('status')).toHaveTextContent('¡Hecho! Revisa tu correo.')
    const call = fetchMock.mock.calls.find(([url]) => url === '/api/welcome-profiles/1/friend-requests')!
    expect(sentBody(call)).toEqual({ email: 'marta@example.com', accept_privacy: true, website: '' })
  })

  it('muestra el error si no se acepta la privacidad', async () => {
    mockApi({
      'GET /api/welcome-profiles': () => ({ body: { profiles: PROFILES } }),
      'POST /api/welcome-profiles/2/friend-requests': () => ({
        status: 422,
        body: { message: 'Revisa', errors: { accept_privacy: 'Tienes que aceptar la política de privacidad.' } },
      }),
    })
    renderAt(<WelcomeProfiles />)

    const dani = await screen.findByRole('article', { name: 'Dani Romero' })
    await userEvent.click(within(dani).getByRole('button', { name: 'Pedir amistad a Dani' }))
    await userEvent.type(within(dani).getByLabelText('Tu email'), 'marta@example.com')
    await userEvent.click(within(dani).getByRole('button', { name: 'Enviar solicitud' }))

    expect(await within(dani).findByText('Tienes que aceptar la política de privacidad.')).toBeInTheDocument()
  })

  it('el campo trampa no está accesible para las personas', async () => {
    mockApi({ 'GET /api/welcome-profiles': () => ({ body: { profiles: PROFILES } }) })
    renderAt(<WelcomeProfiles />)

    const lucia = await screen.findByRole('article', { name: 'Lucía Martín' })
    await userEvent.click(within(lucia).getByRole('button', { name: 'Pedir amistad a Lucía' }))

    expect(within(lucia).queryByRole('textbox', { name: 'Web' })).not.toBeInTheDocument()
  })
})
