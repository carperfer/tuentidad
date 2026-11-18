import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router'
import { describe, expect, it } from 'vitest'
import { AuthProvider } from '../auth/AuthContext'
import { ana, mockApi } from '../test/utils'
import { UploadPhotos } from './UploadPhotos'

const album = (id: number, title: string) => ({ id, title, description: '', updated_at: null, photo_count: 0, cover_photo_id: null })

function renderUpload() {
  return render(
    <MemoryRouter initialEntries={['/subir-fotos']}>
      <AuthProvider>
        <Routes>
          <Route path="/subir-fotos" element={<UploadPhotos />} />
          <Route path="/albumes/:id" element={<p>Álbum abierto</p>} />
          <Route path="/perfil/:id/fotos" element={<p>Lista de álbumes</p>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

describe('UploadPhotos', () => {
  it('opens the only album directly', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/users/1/albums': () => ({ body: { albums: [album(5, 'Mis fotos')] } }),
    })
    renderUpload()

    expect(await screen.findByText('Álbum abierto')).toBeInTheDocument()
  })

  it('goes to the albums list when there are several', async () => {
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/users/1/albums': () => ({ body: { albums: [album(5, 'Mis fotos'), album(6, 'Verano')] } }),
    })
    renderUpload()

    expect(await screen.findByText('Lista de álbumes')).toBeInTheDocument()
  })
})
