import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { describe, expect, it, vi } from 'vitest'
import { AuthProvider } from '../auth/AuthContext'
import { ana, mockApi } from '../test/utils'
import { Album } from './Album'
import { PhotoViewer } from './PhotoViewer'

const photo = (id: number) => ({
  id,
  album_id: 5,
  width: 800,
  height: 600,
  url: `/api/photos/${id}/image`,
  thumb_url: `/api/photos/${id}/thumb`,
  created_at: null,
})

const album = {
  id: 5,
  title: 'Verano',
  description: 'En la playa',
  owner: { id: 1, first_name: 'Ana', last_name: 'García' },
  is_owner: true,
  created_at: null,
  updated_at: null,
  photos: [photo(10), photo(11), photo(12)],
}

describe('Album', () => {
  it('uploads the selected photos one per request and skips invalid files', async () => {
    let uploads = 0
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/albums/5': () => ({ body: { album: { ...album, photos: [] } } }),
      'POST /api/albums/5/photos': () => ({ status: 201, body: { photo: photo(20 + uploads++) } }),
    })
    const user = userEvent.setup({ applyAccept: false })
    renderAt('/albumes/5')

    const input = await screen.findByLabelText(/Subir fotos/)
    await user.upload(input, [
      new File(['a'], 'uno.jpg', { type: 'image/jpeg' }),
      new File(['b'], 'dos.png', { type: 'image/png' }),
      new File(['c'], 'notas.txt', { type: 'text/plain' }),
    ])

    await screen.findByText('Se han subido 2 fotos.')
    expect(screen.getByText(/notas\.txt: usa imágenes JPG, PNG o WebP/)).toBeInTheDocument()
    expect(screen.getAllByRole('img', { name: /Foto \d de 2/ })).toHaveLength(2)
    const uploadCalls = fetchMock.mock.calls.filter(([path]) => path === '/api/albums/5/photos')
    expect(uploadCalls).toHaveLength(2)
    const body = uploadCalls[0][1]?.body
    expect(body instanceof FormData && body.get('photo')).toBeInstanceOf(File)
  })

  it('hides the owner tools from friends', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: { ...ana, id: 2 } } }),
      'GET /api/albums/5': () => ({ body: { album: { ...album, is_owner: false } } }),
    })
    renderAt('/albumes/5')

    await screen.findByRole('heading', { name: 'Verano' })
    expect(screen.getByRole('link', { name: 'Fotos de Ana' })).toBeInTheDocument()
    expect(screen.queryByLabelText(/Subir fotos/)).not.toBeInTheDocument()
  })
})

describe('PhotoViewer', () => {
  it('moves between photos with the links and the arrow keys', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/albums/5': () => ({ body: { album } }),
    })
    const user = userEvent.setup()
    renderAt('/albumes/5/fotos/10')

    await screen.findByText(/Foto 1 de 3/)
    expect(screen.getByText('« Anterior').tagName).toBe('SPAN')

    await user.click(screen.getByRole('link', { name: 'Siguiente »' }))
    expect(screen.getByText(/Foto 2 de 3/)).toBeInTheDocument()

    await user.keyboard('{ArrowRight}')
    expect(screen.getByText(/Foto 3 de 3/)).toBeInTheDocument()
    expect(screen.getByRole('img')).toHaveAttribute('src', '/api/photos/12/image')

    await user.keyboard('{ArrowLeft}')
    expect(screen.getByText(/Foto 2 de 3/)).toBeInTheDocument()
  })

  it('deletes a photo after confirming and shows the next one', async () => {
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/albums/5': () => ({ body: { album } }),
      'DELETE /api/photos/10': () => ({ body: { message: 'Foto borrada.' } }),
    })
    const user = userEvent.setup()
    renderAt('/albumes/5/fotos/10')

    await user.click(await screen.findByRole('button', { name: 'Borrar foto' }))
    await user.click(screen.getByRole('button', { name: 'Sí, borrarla' }))

    await screen.findByText(/Foto 1 de 2/)
    expect(screen.getByRole('img')).toHaveAttribute('src', '/api/photos/11/image')
    expect(vi.mocked(fetchMock)).toHaveBeenCalledWith('/api/photos/10', expect.objectContaining({ method: 'DELETE' }))
  })
})

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>
        <Routes>
          <Route path="/albumes/:id" element={<Album />} />
          <Route path="/albumes/:albumId/fotos/:photoId" element={<PhotoViewer />} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}
