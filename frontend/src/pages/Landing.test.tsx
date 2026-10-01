import { render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { Landing } from './Landing'

describe('Landing', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('muestra el estado de la API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        new Response(JSON.stringify({ status: 'ok', database: 'ok', time: '' }), {
          headers: { 'Content-Type': 'application/json' },
        }),
      ),
    )

    render(<Landing />)

    expect(await screen.findByText('API: ok · Base de datos: ok')).toBeInTheDocument()
  })

  it('avisa si la API no está disponible', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('network')))

    render(<Landing />)

    expect(await screen.findByText('API no disponible')).toBeInTheDocument()
  })
})
