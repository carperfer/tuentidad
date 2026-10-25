import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { errorMessage } from '../api/client'
import { deletePhoto, getAlbum, type Album } from '../api/photos'
import { Alert } from '../components/Alert'
import { OwnerCard } from '../components/ProfileSidebar'
import { Columns } from '../components/ui/Columns'
import { Module } from '../components/ui/Module'
import { Pager } from '../components/ui/Pager'

/** Visor de una foto con navegación anterior/siguiente (también con las flechas del teclado). */
export function PhotoViewer() {
  const { albumId: albumParam, photoId: photoParam } = useParams()
  const albumId = Number(albumParam)
  const photoId = Number(photoParam)
  const navigate = useNavigate()
  const [album, setAlbum] = useState<Album | null>(null)
  const [error, setError] = useState('')
  // Foto cuyo borrado se está confirmando; al cambiar de foto deja de aplicar
  const [confirmingId, setConfirmingId] = useState<number | null>(null)

  useEffect(() => {
    if (!Number.isInteger(albumId) || albumId < 1) return

    getAlbum(albumId)
      .then(({ album: result }) => setAlbum(result))
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [albumId])

  const photos = album?.photos ?? []
  const index = photos.findIndex((photo) => photo.id === photoId)
  const previous = index > 0 ? photos[index - 1] : null
  const next = index >= 0 && index < photos.length - 1 ? photos[index + 1] : null

  useEffect(() => {
    function handleKey(event: KeyboardEvent) {
      if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) return
      if (event.key === 'ArrowLeft' && previous) navigate(`/albumes/${albumId}/fotos/${previous.id}`)
      if (event.key === 'ArrowRight' && next) navigate(`/albumes/${albumId}/fotos/${next.id}`)
    }
    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [albumId, previous, next, navigate])

  async function handleDelete() {
    setError('')
    try {
      await deletePhoto(photoId)
      const target = next ?? previous
      setAlbum((current) => current && { ...current, photos: current.photos.filter((photo) => photo.id !== photoId) })
      navigate(target ? `/albumes/${albumId}/fotos/${target.id}` : `/albumes/${albumId}`, { replace: true })
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  if (error && album === null) return <Columns><Alert>{error}</Alert></Columns>
  if (album === null) return <Columns><p className="status">Cargando foto…</p></Columns>
  if (index < 0) return <Columns><Alert>No se ha encontrado la foto.</Alert></Columns>

  const photo = photos[index]

  return (
    <Columns left={<OwnerCard userId={album.owner.id} />}>
      <Module
        title={album.title}
        as="h1"
        className="photo-viewer"
        action={<Link to={`/albumes/${album.id}`}>Volver al álbum</Link>}
      >
        <Pager
          index={index}
          total={photos.length}
          onChange={(target) => navigate(`/albumes/${album.id}/fotos/${photos[target].id}`)}
        />
        {error && <Alert>{error}</Alert>}
        <img
          key={photo.id}
          className="photo-viewer__image"
          src={photo.url}
          width={photo.width}
          height={photo.height}
          alt={`Foto ${index + 1} del álbum ${album.title}`}
        />
        <p className="muted photo-viewer__hint">Usa las flechas del teclado para pasar de foto.</p>
        {album.is_owner && (
          <div className="danger-zone">
            {confirmingId === photoId ? (
              <>
                <span>¿Borrar esta foto? </span>
                <button type="button" className="button button--danger" onClick={handleDelete}>
                  Sí, borrarla
                </button>{' '}
                <button type="button" className="link-button" onClick={() => setConfirmingId(null)}>
                  Cancelar
                </button>
              </>
            ) : (
              <button type="button" className="link-button" onClick={() => setConfirmingId(photoId)}>
                Borrar foto
              </button>
            )}
          </div>
        )}
      </Module>
    </Columns>
  )
}
