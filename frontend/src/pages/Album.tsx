import { useEffect, useState, type ChangeEvent, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { errorMessage, fieldErrors } from '../api/client'
import {
  deleteAlbum,
  getAlbum,
  MAX_PHOTO_SIZE,
  PHOTO_TYPES,
  updateAlbum,
  uploadPhoto,
  type Album as AlbumView,
} from '../api/photos'
import { Alert } from '../components/Alert'
import { Field } from '../components/Field'
import { OwnerCard } from '../components/ProfileSidebar'
import { Columns } from '../components/ui/Columns'
import { Counter, Module } from '../components/ui/Module'

/** Fotos de un álbum; el dueño puede subir fotos, editarlo y borrarlo. */
export function Album() {
  const { id } = useParams()
  const albumId = Number(id)
  const navigate = useNavigate()
  const [album, setAlbum] = useState<AlbumView | null>(null)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!Number.isInteger(albumId) || albumId < 1) return

    getAlbum(albumId)
      .then(({ album: result }) => setAlbum(result))
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [albumId])

  if (!Number.isInteger(albumId) || albumId < 1) return <Columns><Alert>No se ha encontrado el álbum.</Alert></Columns>
  if (error && album === null) return <Columns><Alert>{error}</Alert></Columns>
  if (album === null) return <Columns><p className="status">Cargando álbum…</p></Columns>

  return (
    <Columns
      left={
        <>
          <OwnerCard userId={album.owner.id} />
          {album.is_owner && (
            <AlbumOwnerTools
              album={album}
              onChange={setAlbum}
              onDeleted={() => navigate(`/perfil/${album.owner.id}/fotos`)}
            />
          )}
        </>
      }
    >
      <Module
        title={
          <>
            {album.title} <Counter value={album.photos.length} />
          </>
        }
        as="h1"
        action={
          <Link to={`/perfil/${album.owner.id}/fotos`}>
            {album.is_owner ? 'Mis álbumes' : `Álbumes de ${album.owner.first_name}`}
          </Link>
        }
      >
        {album.description && <p>{album.description}</p>}
        {album.photos.length === 0 ? (
          <p className="status">Este álbum todavía no tiene fotos.</p>
        ) : (
          <ul className="photo-grid">
            {album.photos.map((photo, index) => (
              <li key={photo.id}>
                <Link to={`/albumes/${album.id}/fotos/${photo.id}`}>
                  <img src={photo.thumb_url} alt={`Foto ${index + 1} de ${album.photos.length}`} loading="lazy" />
                </Link>
              </li>
            ))}
          </ul>
        )}
      </Module>
    </Columns>
  )
}

type OwnerToolsProps = {
  album: AlbumView
  onChange: (album: AlbumView) => void
  onDeleted: () => void
}

function AlbumOwnerTools({ album, onChange, onDeleted }: OwnerToolsProps) {
  const [title, setTitle] = useState(album.title)
  const [description, setDescription] = useState(album.description)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [saving, setSaving] = useState(false)
  const [progress, setProgress] = useState<{ done: number; total: number } | null>(null)
  const [uploadErrors, setUploadErrors] = useState<string[]>([])
  const [confirmDelete, setConfirmDelete] = useState(false)

  async function handleSave(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setErrors({})
    setError('')
    setNotice('')
    try {
      const result = await updateAlbum(album.id, { title, description })
      onChange(result.album)
      setNotice('El álbum se ha guardado.')
    } catch (caught) {
      setErrors(fieldErrors(caught))
      setError(errorMessage(caught))
    } finally {
      setSaving(false)
    }
  }

  // Las fotos se suben de una en una para no pasar los límites del hosting
  async function handleFiles(event: ChangeEvent<HTMLInputElement>) {
    const files = Array.from(event.target.files ?? [])
    event.target.value = ''
    if (files.length === 0) return

    setError('')
    setNotice('')
    setUploadErrors([])
    const failed: string[] = []
    let photos = album.photos
    setProgress({ done: 0, total: files.length })

    for (const [index, file] of files.entries()) {
      if (!PHOTO_TYPES.includes(file.type)) {
        failed.push(`${file.name}: usa imágenes JPG, PNG o WebP.`)
      } else if (file.size > MAX_PHOTO_SIZE) {
        failed.push(`${file.name}: la foto no puede superar los 10 MB.`)
      } else {
        try {
          const { photo } = await uploadPhoto(album.id, file)
          photos = [...photos, photo]
          onChange({ ...album, photos })
        } catch (caught) {
          failed.push(`${file.name}: ${fieldErrors(caught).photo ?? errorMessage(caught)}`)
        }
      }
      setProgress({ done: index + 1, total: files.length })
    }

    setProgress(null)
    setUploadErrors(failed)
    const uploaded = files.length - failed.length
    if (uploaded > 0) setNotice(uploaded === 1 ? 'Se ha subido 1 foto.' : `Se han subido ${uploaded} fotos.`)
  }

  async function handleDelete() {
    setError('')
    try {
      await deleteAlbum(album.id)
      onDeleted()
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  return (
    <>
      <Module title="Subir fotos">
        {notice && <Alert kind="ok">{notice}</Alert>}
        <label htmlFor="photo-upload" className="muted">
          JPG, PNG o WebP; máximo 10 MB cada una. Puedes elegir varias.
        </label>
        <input
          id="photo-upload"
          className="file-input"
          type="file"
          accept={PHOTO_TYPES.join(',')}
          multiple
          aria-label="Subir fotos"
          onChange={handleFiles}
          disabled={progress !== null}
        />
        {progress && (
          <p className="status" role="status">
            Subiendo {Math.min(progress.done + 1, progress.total)} de {progress.total}…
          </p>
        )}
        {uploadErrors.length > 0 && (
          <ul className="photo-upload__errors" role="alert">
            {uploadErrors.map((message) => (
              <li key={message}>{message}</li>
            ))}
          </ul>
        )}
      </Module>

      <Module title="Editar álbum">
        {error && <Alert>{error}</Alert>}
        <form onSubmit={handleSave} noValidate>
          <Field
            label="Título"
            value={title}
            onChange={(event) => setTitle(event.target.value)}
            error={errors.title}
            maxLength={100}
            required
          />
          <Field
            label="Descripción"
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            error={errors.description}
            maxLength={500}
          />
          <button type="submit" className="button" disabled={saving}>
            {saving ? 'Guardando…' : 'Guardar cambios'}
          </button>
        </form>

        <div className="danger-zone">
          {confirmDelete ? (
            <>
              <p>Se borrarán el álbum y todas sus fotos. No se puede deshacer.</p>
              <button type="button" className="button button--danger" onClick={handleDelete}>
                Sí, borrar el álbum
              </button>{' '}
              <button type="button" className="link-button" onClick={() => setConfirmDelete(false)}>
                Cancelar
              </button>
            </>
          ) : (
            <button type="button" className="link-button link-button--danger" onClick={() => setConfirmDelete(true)}>
              Borrar álbum
            </button>
          )}
        </div>
      </Module>
    </>
  )
}
