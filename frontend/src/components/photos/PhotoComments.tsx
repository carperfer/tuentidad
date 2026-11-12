import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { errorMessage, fieldErrors } from '../../api/client'
import { commentPhoto, deleteComment, type PhotoComment } from '../../api/photos'
import { useAuth } from '../../auth/context'
import { timeAgo } from '../../lib/dates'
import { Alert } from '../Alert'
import { Avatar } from '../ui/Avatar'
import { MediaObject } from '../ui/MediaObject'
import { Counter, Module } from '../ui/Module'

type PhotoCommentsProps = {
  photoId: number
  comments: PhotoComment[]
  onChange: (comments: PhotoComment[]) => void
}

/** Comentarios de una foto y caja para escribir uno (guía §7.3 y §7.4). */
export function PhotoComments({ photoId, comments, onChange }: PhotoCommentsProps) {
  const { user } = useAuth()
  const [body, setBody] = useState('')
  const [error, setError] = useState('')
  const [sending, setSending] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (body.trim() === '') return
    setSending(true)
    setError('')
    try {
      const result = await commentPhoto(photoId, body)
      onChange(result.comments)
      setBody('')
    } catch (caught) {
      setError(fieldErrors(caught).body ?? errorMessage(caught))
    } finally {
      setSending(false)
    }
  }

  async function handleDelete(commentId: number) {
    setError('')
    try {
      onChange((await deleteComment(commentId)).comments)
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  return (
    <Module
      title={
        <>
          Comentarios <Counter value={comments.length} />
        </>
      }
      className="photo-comments"
    >
      {error && <Alert>{error}</Alert>}
      {comments.length === 0 ? (
        <p className="status">Todavía no hay comentarios. ¡Sé el primero!</p>
      ) : (
        <ul className="media-list">
          {comments.map((comment) => (
            <li key={comment.id}>
              <MediaObject
                media={
                  <Link to={`/perfil/${comment.author.id}`} tabIndex={-1} aria-hidden="true">
                    <Avatar src={comment.author.avatar_url} name={comment.author.first_name} size={40} decorative />
                  </Link>
                }
                aside={
                  comment.can_delete && (
                    <button
                      type="button"
                      className="link-button"
                      aria-label={`Borrar el comentario de ${comment.author.first_name}`}
                      onClick={() => handleDelete(comment.id)}
                    >
                      Borrar
                    </button>
                  )
                }
              >
                <p className="feed__line">
                  <Link to={`/perfil/${comment.author.id}`} className="person-name">
                    {comment.author.first_name} {comment.author.last_name}
                  </Link>{' '}
                  <time className="muted" dateTime={comment.created_at}>
                    {timeAgo(comment.created_at)}
                  </time>
                </p>
                <p className="comment__body">{comment.body}</p>
              </MediaObject>
            </li>
          ))}
        </ul>
      )}
      <form className="composer" onSubmit={handleSubmit}>
        <Avatar src={user?.avatar_url} name={user?.first_name ?? ''} size={32} decorative />
        <label className="visually-hidden" htmlFor={`comment-${photoId}`}>
          Escribe un comentario
        </label>
        <textarea
          id={`comment-${photoId}`}
          rows={2}
          maxLength={500}
          placeholder="Escribe un comentario…"
          value={body}
          onChange={(event) => setBody(event.target.value)}
        />
        <button type="submit" className="button" disabled={sending || body.trim() === ''}>
          Comentar
        </button>
      </form>
    </Module>
  )
}
