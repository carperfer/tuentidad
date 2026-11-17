import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { describe, expect, it } from 'vitest'
import { Landing } from '../pages/Landing'
import { ana, mockApi, renderAt, sentBody } from '../test/utils'
import { AccountData } from './AccountData'

function renderAccount() {
  return renderAt(
    <Routes>
      <Route path="/perfil/editar" element={<AccountData />} />
      <Route path="/" element={<Landing />} />
    </Routes>,
    '/perfil/editar',
  )
}

describe('AccountData', () => {
  it('links to the data export', async () => {
    mockApi({ 'GET /api/auth/me': () => ({ body: { user: ana } }) })
    renderAccount()

    expect(await screen.findByRole('link', { name: /Descargar mis datos/ })).toHaveAttribute('href', '/api/account/export')
  })

  it('asks for the password and shows the error when it is wrong', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'DELETE /api/account': () => ({
        status: 422,
        body: { message: 'Revisa', errors: { password: 'La contraseña no es correcta.' } },
      }),
    })
    const user = userEvent.setup()
    renderAccount()

    await user.click(await screen.findByRole('button', { name: 'Borrar mi cuenta' }))
    expect(screen.getByRole('button', { name: 'Borrar mi cuenta para siempre' })).toBeDisabled()
    await user.type(screen.getByLabelText('Escribe tu contraseña para confirmar'), 'mala')
    await user.click(screen.getByRole('button', { name: 'Borrar mi cuenta para siempre' }))

    expect(await screen.findByText('La contraseña no es correcta.')).toBeInTheDocument()
  })

  it('deletes the account and goes to the landing with a notice', async () => {
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'DELETE /api/account': () => ({ body: { message: 'Tu cuenta y todos tus datos se han borrado.' } }),
    })
    const user = userEvent.setup()
    renderAccount()

    await user.click(await screen.findByRole('button', { name: 'Borrar mi cuenta' }))
    await user.type(screen.getByLabelText('Escribe tu contraseña para confirmar'), 'Gato.Azul.88')
    await user.click(screen.getByRole('button', { name: 'Borrar mi cuenta para siempre' }))

    expect(await screen.findByText('Tu cuenta y todos tus datos se han borrado.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Entrar' })).toBeInTheDocument()
    const call = fetchMock.mock.calls.find(([path]) => path === '/api/account')
    expect(call && sentBody(call)).toEqual({ password: 'Gato.Azul.88' })
  })
})
