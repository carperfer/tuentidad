import { screen } from '@testing-library/react'
import { Route, Routes } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ana, mockApi, renderAt } from '../test/utils'
import { RequireAuth } from './RequireAuth'

function renderProtected() {
  return renderAt(
    <Routes>
      <Route path="/" element={<p>Portada</p>} />
      <Route
        path="/inicio"
        element={
          <RequireAuth>
            <p>Contenido privado</p>
          </RequireAuth>
        }
      />
    </Routes>,
    '/inicio',
  )
}

describe('RequireAuth', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('sin sesión lleva a la portada', async () => {
    mockApi({})
    renderProtected()

    expect(await screen.findByText('Portada')).toBeInTheDocument()
    expect(screen.queryByText('Contenido privado')).not.toBeInTheDocument()
  })

  it('con sesión muestra el contenido', async () => {
    mockApi({ 'GET /api/auth/me': () => ({ body: { user: ana } }) })
    renderProtected()

    expect(await screen.findByText('Contenido privado')).toBeInTheDocument()
  })
})
