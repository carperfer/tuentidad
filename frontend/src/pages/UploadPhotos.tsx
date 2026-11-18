import { useEffect } from 'react'
import { useNavigate } from 'react-router'
import { listAlbums } from '../api/photos'
import { useAuth } from '../auth/context'
import { Columns } from '../components/ui/Columns'

/**
 * Destino de los botones «Subir fotos»: con un solo álbum, entra en él para subir
 * directamente; con varios (o ninguno), va a la lista de álbumes.
 */
export function UploadPhotos() {
  const { user } = useAuth()
  const navigate = useNavigate()

  useEffect(() => {
    if (!user) return
    const albumsPage = `/perfil/${user.id}/fotos`
    listAlbums(user.id)
      .then(({ albums }) => navigate(albums.length === 1 ? `/albumes/${albums[0].id}` : albumsPage, { replace: true }))
      .catch(() => navigate(albumsPage, { replace: true }))
  }, [user, navigate])

  return (
    <Columns>
      <p className="status">Abriendo tus álbumes…</p>
    </Columns>
  )
}
