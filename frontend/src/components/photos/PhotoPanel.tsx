import { useEffect, useState, type FormEvent, type ReactNode } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../../api/client'
import type { Person } from '../../api/friends'
import { notifyNotificationsChanged } from '../../api/notifications'
import {
  commentPhoto,
  deleteComment,
  getPhoto,
  listTaggable,
  removeTag,
  tagPhoto,
  type Photo,
  type PhotoDetail,
  type TagArea,
} from '../../api/photos'
import { useAuth } from '../../auth/context'
import { Alert } from '../Alert'
import { Comments } from '../Comments'
import { Module } from '../ui/Module'
import { areaAround } from '../../lib/tagArea'
import { PhotoStage } from './PhotoStage'

type PhotoPanelProps = {
  photo: Photo
  alt: string
  /** Título y enlace del módulo del visor. */
  title: string
  action: ReactNode
  /** Contenido del módulo antes de la foto (paginador) y al final (herramientas del dueño). */
  before: ReactNode
  after: ReactNode
}

/**
 * Una foto del visor con sus etiquetas y comentarios. Se monta de nuevo
 * al cambiar de foto, así el etiquetado a medias no pasa a la siguiente.
 */
export function PhotoPanel({ photo, alt, title, action, before, after }: PhotoPanelProps) {
  const { user } = useAuth()
  const [detail, setDetail] = useState<PhotoDetail | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [highlightId, setHighlightId] = useState<number | null>(null)
  // Etiquetado en curso: zona marcada, personas que se pueden elegir y la elegida
  const [area, setArea] = useState<TagArea | null>(null)
  const [candidates, setCandidates] = useState<Person[] | null>(null)
  const [chosenId, setChosenId] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    getPhoto(photo.id)
      .then((result) => {
        setDetail(result)
        // Al verla se marcan como leídos sus avisos
        notifyNotificationsChanged()
      })
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [photo.id])

  async function startTagging() {
    setError('')
    setNotice('')
    setArea(areaAround(photo, 50, 50))
    setChosenId('')
    try {
      const { people } = await listTaggable(photo.id)
      setCandidates(people)
    } catch (caught) {
      setError(errorMessage(caught))
      setArea(null)
    }
  }

  function stopTagging() {
    setArea(null)
    setCandidates(null)
  }

  async function handleTag(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!area || chosenId === '') return
    setSaving(true)
    setError('')
    try {
      const { tags } = await tagPhoto(photo.id, Number(chosenId), area)
      setDetail((current) => current && { ...current, tags })
      stopTagging()
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setSaving(false)
    }
  }

  async function handleRemove(tagId: number) {
    setError('')
    setNotice('')
    try {
      const result = await removeTag(tagId)
      setDetail((current) => current && { ...current, tags: result.tags })
      setNotice(result.message)
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  const tags = detail?.tags ?? []

  return (
    <>
      <Module title={title} as="h1" className="photo-viewer" action={action}>
        {before}
        <PhotoStage photo={photo} alt={alt} tags={tags} highlightId={highlightId} area={area} onAreaChange={setArea} />

        {error && <Alert>{error}</Alert>}
        {notice && <Alert kind="ok">{notice}</Alert>}

        {detail && (
          <div className="photo-tags">
            {tags.length > 0 && (
              <p>
                <span className="muted">En esta foto: </span>
                {tags.map((tag, index) => {
                  const name = `${tag.user.first_name} ${tag.user.last_name}`
                  return (
                    <span key={tag.id} onMouseEnter={() => setHighlightId(tag.id)} onMouseLeave={() => setHighlightId(null)}>
                      {index > 0 && ', '}
                      <Link
                        to={`/perfil/${tag.user.id}`}
                        onFocus={() => setHighlightId(tag.id)}
                        onBlur={() => setHighlightId(null)}
                      >
                        {name}
                      </Link>
                      {tag.can_remove && (
                        <>
                          {' '}
                          <button
                            type="button"
                            className="link-button photo-tags__remove"
                            aria-label={tag.user.id === user?.id ? 'Quitar mi etiqueta' : `Quitar la etiqueta de ${name}`}
                            onClick={() => handleRemove(tag.id)}
                          >
                            (quitar)
                          </button>
                        </>
                      )}
                    </span>
                  )
                })}
              </p>
            )}

            {area === null ? (
              <button type="button" className="button" onClick={startTagging}>
                <span aria-hidden="true">🏷</span> Etiquetar a alguien
              </button>
            ) : (
              <form className="tag-form" onSubmit={handleTag}>
                <p className="muted">Arrastra sobre la foto para marcar dónde sale y elige quién es.</p>
                {candidates === null ? (
                  <p className="status">Cargando amigos…</p>
                ) : candidates.length === 0 ? (
                  <p className="status">No queda nadie a quien puedas etiquetar en esta foto.</p>
                ) : (
                  <>
                    <label htmlFor={`tag-person-${photo.id}`}>¿Quién sale?</label>{' '}
                    <select id={`tag-person-${photo.id}`} value={chosenId} onChange={(event) => setChosenId(event.target.value)}>
                      <option value="">Elige a una persona</option>
                      {candidates.map((person) => (
                        <option key={person.id} value={person.id}>
                          {person.id === user?.id ? 'Yo' : `${person.first_name} ${person.last_name}`}
                        </option>
                      ))}
                    </select>{' '}
                    <button type="submit" className="button button--primary" disabled={saving || chosenId === ''}>
                      Etiquetar
                    </button>
                  </>
                )}{' '}
                <button type="button" className="link-button" onClick={stopTagging}>
                  Cancelar
                </button>
              </form>
            )}
          </div>
        )}
        {after}
      </Module>

      {detail && (
        <Comments
          id={`photo-${photo.id}`}
          comments={detail.comments}
          onSubmit={async (body) => (await commentPhoto(photo.id, body)).comments}
          onDelete={async (commentId) => (await deleteComment(commentId)).comments}
          onChange={(comments) => setDetail((current) => current && { ...current, comments })}
        />
      )}
    </>
  )
}
