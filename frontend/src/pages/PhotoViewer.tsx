import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { errorMessage } from '../api/client'
import { deletePhoto, getAlbum, type Album } from '../api/photos'
import { Alert } from '../components/Alert'

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

  if (error && album === null) return <Alert>{error}</Alert>
  if (album === null) return <p className="status">Cargando foto…</p>
  if (index < 0) return <Alert>No se ha encontrado la foto.</Alert>

  const photo = photos[index]

  return (
    <section className="box photo-viewer">
      <h1 className="box__title">{album.title}</h1>
      <nav className="photo-viewer__nav" aria-label="Fotos del álbum">
        {previous ? (
          <Link to={`/albumes/${album.id}/fotos/${previous.id}`}>« Anterior</Link>
        ) : (
          <span className="photo-viewer__disabled">« Anterior</span>
        )}
        <span>
          Foto {index + 1} de {photos.length} · <Link to={`/albumes/${album.id}`}>Volver al álbum</Link>
        </span>
        {next ? (
          <Link to={`/albumes/${album.id}/fotos/${next.id}`}>Siguiente »</Link>
        ) : (
          <span className="photo-viewer__disabled">Siguiente »</span>
        )}
      </nav>
      {error && <Alert>{error}</Alert>}
      <img
        key={photo.id}
        className="photo-viewer__image"
        src={photo.url}
        width={photo.width}
        height={photo.height}
        alt={`Foto ${index + 1} del álbum ${album.title}`}
      />
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
    </section>
  )
}
