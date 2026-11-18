import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { ana, mockApi, renderAt, sentBody } from '../test/utils'
import { DigestSettings } from './DigestSettings'

describe('DigestSettings', () => {
  it('saves the chosen frequency', async () => {
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'PUT /api/profile/settings': () => ({ body: { profile: { digest: 'weekly' } } }),
    })
    const user = userEvent.setup()
    renderAt(<DigestSettings initial="none" />)

    expect(screen.getByRole('button', { name: 'Guardar' })).toBeDisabled()
    await user.selectOptions(screen.getByLabelText('Recibir un resumen'), 'Cada semana')
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Guardado. El resumen solo llega si hay novedades.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Guardar' })).toBeDisabled()
    const call = fetchMock.mock.calls.find(([path]) => path === '/api/profile/settings')
    expect(call && sentBody(call)).toEqual({ digest: 'weekly' })
  })
})
