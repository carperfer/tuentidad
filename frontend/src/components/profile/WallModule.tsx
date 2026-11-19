import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { errorMessage, fieldErrors } from '../../api/client'
import { deleteWallPost, listWall, writeOnWall, type WallPost } from '../../api/wall'
import { useAuth } from '../../auth/context'
import { formatLong } from '../../lib/dates'
import { Alert } from '../Alert'
import { Avatar } from '../ui/Avatar'
import { MediaObject } from '../ui/MediaObject'
import { Module } from '../ui/Module'

/** «Tablón» del perfil: caja para escribir y mensajes de la persona y sus amigos (guía §7.3 y §7.4). */
export function WallModule({ userId, own, firstName }: { userId: number; own: boolean; firstName: string }) {
  const { user } = useAuth()
  const [posts, setPosts] = useState<WallPost[] | null>(null)
  const [nextBefore, setNextBefore] = useState<number | null>(null)
  const [body, setBody] = useState('')
  const [error, setError] = useState('')
  const [sending, setSending] = useState(false)

  useEffect(() => {
    listWall(userId)
      .then((page) => {
        setPosts(page.posts)
        setNextBefore(page.next_before)
      })
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [userId])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (body.trim() === '') return
    setSending(true)
    setError('')
    try {
      const { post } = await writeOnWall(userId, body)
      setPosts((current) => [post, ...(current ?? [])])
      setBody('')
    } catch (caught) {
      setError(fieldErrors(caught).body ?? errorMessage(caught))
    } finally {
      setSending(false)
    }
  }

  async function handleDelete(id: number) {
    try {
      await deleteWallPost(id)
      setPosts((current) => current?.filter((post) => post.id !== id) ?? null)
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  async function loadMore() {
    if (nextBefore === null) return
    try {
      const page = await listWall(userId, nextBefore)
      setPosts((current) => [...(current ?? []), ...page.posts])
      setNextBefore(page.next_before)
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  return (
    <Module title="Tablón" className="wall">
      <form className="composer" onSubmit={handleSubmit}>
        <Avatar src={user?.avatar_url} name={user?.first_name ?? ''} size={32} decorative />
        <label className="visually-hidden" htmlFor={`wall-${userId}`}>
          {own ? 'Escribe en tu tablón' : `Escribe en el tablón de ${firstName}`}
        </label>
        <textarea
          id={`wall-${userId}`}
          rows={2}
          maxLength={1000}
          placeholder="Escribe aquí…"
          value={body}
          onChange={(event) => setBody(event.target.value)}
        />
        <button type="submit" className="button" disabled={sending || body.trim() === ''}>
          Publicar
        </button>
      </form>
      {error && <Alert>{error}</Alert>}
      {posts === null ? (
        !error && <p className="status">Cargando tablón…</p>
      ) : posts.length === 0 ? (
        <p className="status">{own ? 'Tu tablón está vacío. Tus amigos pueden escribirte aquí.' : `Sé el primero en escribir a ${firstName}.`}</p>
      ) : (
        <ul className="media-list">
          {posts.map((post) => (
            <li key={post.id}>
              <MediaObject
                media={
                  <Link to={`/perfil/${post.author.id}`} tabIndex={-1} aria-hidden="true">
                    <Avatar src={post.author.avatar_url} name={post.author.first_name} size={40} decorative />
                  </Link>
                }
                aside={
                  <span className="wall__actions">
                    {own && post.author.id !== user?.id && <Link to={`/perfil/${post.author.id}`}>Responder</Link>}
                    {post.can_delete && (
                      <button
                        type="button"
                        className="link-button"
                        aria-label={`Borrar el mensaje de ${post.author.first_name}`}
                        onClick={() => handleDelete(post.id)}
                      >
                        Borrar
                      </button>
                    )}
                  </span>
                }
              >
                <p className="feed__line">
                  <Link to={`/perfil/${post.author.id}`} className="person-name">
                    {post.author.first_name} {post.author.last_name}
                  </Link>{' '}
                  <time className="muted" dateTime={post.created_at}>
                    {formatLong(post.created_at)}
                  </time>
                </p>
                <p className="comment__body">{post.body}</p>
              </MediaObject>
            </li>
          ))}
        </ul>
      )}
      {nextBefore !== null && (
        <p className="load-more">
          <button type="button" className="button" onClick={loadMore}>
            Ver más
          </button>
        </p>
      )}
    </Module>
  )
}
