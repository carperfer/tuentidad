import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { errorMessage, fieldErrors } from '../api/client'
import { createAlbum, listAlbums, thumbUrl, type AlbumSummary } from '../api/photos'
import { getProfile, type Profile } from '../api/profile'
import { Alert } from '../components/Alert'
import { Field } from '../components/Field'
import { OwnerCard } from '../components/ProfileSidebar'
import { Columns } from '../components/ui/Columns'
import { Counter, Module } from '../components/ui/Module'

/** Álbumes de un usuario; el dueño puede crear álbumes nuevos. */
export function Albums() {
  const { id } = useParams()
  const userId = Number(id)
  const navigate = useNavigate()
  const [profile, setProfile] = useState<Profile | null>(null)
  const [albums, setAlbums] = useState<AlbumSummary[] | null>(null)
  const [error, setError] = useState('')
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState('')
  const [creating, setCreating] = useState(false)

  useEffect(() => {
    if (!Number.isInteger(userId) || userId < 1) return

    getProfile(userId)
      .then(({ profile: result }) => setProfile(result))
      .catch((caught: unknown) => setError(errorMessage(caught)))
    listAlbums(userId)
      .then(({ albums: result }) => setAlbums(result))
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [userId])

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setCreating(true)
    setErrors({})
    setFormError('')
    try {
      const { album } = await createAlbum({ title, description })
      navigate(`/albumes/${album.id}`)
    } catch (caught) {
      setErrors(fieldErrors(caught))
      setFormError(errorMessage(caught))
      setCreating(false)
    }
  }

  if (!Number.isInteger(userId) || userId < 1) return <Columns><Alert>No se ha encontrado el usuario.</Alert></Columns>

  const own = profile?.is_owner ?? false

  return (
    <Columns
      left={
        <>
          <OwnerCard userId={userId} profile={profile} />
          {own && (
            <Module title="Nuevo álbum">
              <p className="muted">Solo tus amigos podrán ver las fotos.</p>
              {formError && <Alert>{formError}</Alert>}
              <form onSubmit={handleCreate} noValidate>
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
                <button type="submit" className="button button--primary" disabled={creating}>
                  {creating ? 'Creando…' : 'Crear álbum'}
                </button>
              </form>
            </Module>
          )}
        </>
      }
    >
      {error ? (
        <Alert>{error}</Alert>
      ) : profile === null || albums === null ? (
        <p className="status">Cargando fotos…</p>
      ) : (
        <Module
          as="h1"
          title={
            <>
              {own ? 'Mis álbumes de fotos' : `Álbumes de ${profile.first_name}`} <Counter value={albums.length} />
            </>
          }
        >
          {albums.length === 0 ? (
            <p className="status">{own ? 'Todavía no has creado ningún álbum.' : 'Todavía no tiene álbumes.'}</p>
          ) : (
            <ul className="album-grid">
              {albums.map((album) => (
                <li key={album.id}>
                  <Link to={`/albumes/${album.id}`} className="album-card">
                    {album.cover_photo_id ? (
                      <img src={thumbUrl(album.cover_photo_id)} alt="" className="album-card__cover" />
                    ) : (
                      <span className="album-card__cover album-card__cover--empty" />
                    )}
                    <strong>{album.title}</strong>
                    <span className="muted">{album.photo_count === 1 ? '1 foto' : `${album.photo_count} fotos`}</span>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Module>
      )}
    </Columns>
  )
}
