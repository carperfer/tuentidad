import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes, useLocation } from 'react-router'
import { describe, expect, it } from 'vitest'
import { ana, mockApi, renderAt } from '../test/utils'
import { Layout } from './Layout'

function Location() {
  const location = useLocation()
  return <p>Ruta: {location.pathname + location.search}</p>
}

function renderShell(path: string) {
  return renderAt(
    <Routes>
      <Route element={<Layout />}>
        <Route path="*" element={<Location />} />
      </Route>
    </Routes>,
    path,
  )
}

describe('Topbar', () => {
  it('marks the active section and searches people', async () => {
    mockApi({ 'GET /api/auth/me': () => ({ body: { user: ana } }) })
    const user = userEvent.setup()
    renderShell('/perfil/1')

    const nav = await screen.findByRole('navigation', { name: 'Principal' })
    expect(screen.getByRole('link', { name: 'Perfil' })).toHaveClass('active')
    expect(nav).not.toHaveTextContent('Mensajes')
    expect(screen.getByRole('link', { name: /Subir fotos/ })).toHaveAttribute('href', '/subir-fotos')

    await user.type(screen.getByRole('searchbox', { name: 'Buscar personas' }), 'Bea{Enter}')

    expect(screen.getByText('Ruta: /gente?q=Bea')).toBeInTheDocument()
  })

  it('shows the guest header without session', async () => {
    mockApi({})
    renderShell('/')

    expect(await screen.findByRole('link', { name: 'tuentidad' })).toHaveAttribute('href', '/')
    expect(screen.queryByRole('navigation', { name: 'Principal' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Condiciones de uso' })).toBeInTheDocument()
  })
})
