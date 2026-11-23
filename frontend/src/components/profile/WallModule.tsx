import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { errorMessage, fieldErrors } from '../../api/client'
import { deleteWallPost, listWall, writeOnWall, type WallPost, type WallReply } from '../../api/wall'
import { useAuth } from '../../auth/context'
import { formatLong } from '../../lib/dates'
import { linkify } from '../../lib/links'
import { Alert } from '../Alert'
import { Avatar, type AvatarSize } from '../ui/Avatar'
import { MediaObject } from '../ui/MediaObject'
import { Module } from '../ui/Module'
import { LinkPreviewCard } from '../wall/LinkPreviewCard'
import { WallComposer } from '../wall/WallComposer'

/** Respuestas que se ven de un hilo antes de pulsar «Ver todas». */
const VISIBLE_REPLIES = 3

/** «Tablón» del perfil: caja para escribir y mensajes de la persona y sus amigos con sus hilos de respuestas (guía §7.3 y §7.4). */
export function WallModule({ userId, own, firstName }: { userId: number; own: boolean; firstName: string }) {
  const { user } = useAuth()
  const [posts, setPosts] = useState<WallPost[] | null>(null)
  const [nextBefore, setNextBefore] = useState<number | null>(null)
  const [error, setError] = useState('')

  useEffect(() => {
    listWall(userId)
      .then((page) => {
        setPosts(page.posts)
        setNextBefore(page.next_before)
      })
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [userId])

  async function publish(body: string, linkPreview: boolean) {
    setError('')
    try {
      const { post } = await writeOnWall(userId, { body, linkPreview })
      setPosts((current) => [post, ...(current ?? [])])
    } catch (caught) {
      setError(fieldErrors(caught).body ?? errorMessage(caught))
      throw caught
    }
  }

  async function reply(parent: WallPost, body: string, linkPreview: boolean) {
    setError('')
    try {
      const { post } = await writeOnWall<WallReply>(userId, { body, parentId: parent.id, linkPreview })
      setPosts((current) => current?.map((item) => (item.id === parent.id ? { ...item, replies: [...item.replies, post] } : item)) ?? null)
    } catch (caught) {
      setError(fieldErrors(caught).body ?? errorMessage(caught))
      throw caught
    }
  }

  async function handleDelete(id: number) {
    try {
      await deleteWallPost(id)
      setPosts(
        (current) =>
          current
            ?.filter((post) => post.id !== id)
            .map((post) => ({ ...post, replies: post.replies.filter((item) => item.id !== id) })) ?? null,
      )
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
      <WallComposer
        id={`wall-${userId}`}
        label={own ? 'Escribe en tu tablón' : `Escribe en el tablón de ${firstName}`}
        leading={<Avatar src={user?.avatar_url} name={user?.first_name ?? ''} size={32} decorative />}
        onSubmit={publish}
      />
      {error && <Alert>{error}</Alert>}
      {posts === null ? (
        !error && <p className="status">Cargando tablón…</p>
      ) : posts.length === 0 ? (
        <p className="status">{own ? 'Tu tablón está vacío. Tus amigos pueden escribirte aquí.' : `Sé el primero en escribir a ${firstName}.`}</p>
      ) : (
        <ul className="media-list">
          {posts.map((post) => (
            <li key={post.id}>
              <WallThread post={post} userId={userId} onReply={reply} onDelete={handleDelete} />
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

function WallThread({
  post,
  userId,
  onReply,
  onDelete,
}: {
  post: WallPost
  userId: number
  onReply: (parent: WallPost, body: string, linkPreview: boolean) => Promise<void>
  onDelete: (id: number) => void
}) {
  const { user } = useAuth()
  const [replying, setReplying] = useState(false)
  const [expanded, setExpanded] = useState(false)
  const hidden = expanded ? 0 : Math.max(0, post.replies.length - VISIBLE_REPLIES)
  const replies = post.replies.slice(hidden)

  return (
    <>
      <WallEntry entry={post} avatarSize={40} onDelete={onDelete} onReply={() => setReplying(true)} />
      {(post.replies.length > 0 || replying) && (
        <div className="wall__thread">
          {hidden > 0 && (
            <button type="button" className="link-button wall__more" onClick={() => setExpanded(true)}>
              Ver las {post.replies.length} respuestas
            </button>
          )}
          {replies.length > 0 && (
            <ul className="wall__replies">
              {replies.map((item) => (
                <li key={item.id}>
                  <WallEntry entry={item} avatarSize={32} onDelete={onDelete} onReply={() => setReplying(true)} />
                </li>
              ))}
            </ul>
          )}
          {replying && (
            <WallComposer
              id={`wall-${userId}-reply-${post.id}`}
              label={`Responde a ${post.author.first_name}`}
              placeholder="Escribe una respuesta…"
              submitLabel="Responder"
              leading={<Avatar src={user?.avatar_url} name={user?.first_name ?? ''} size={24} decorative />}
              autoFocus
              compact
              onSubmit={(body, linkPreview) => onReply(post, body, linkPreview)}
            />
          )}
        </div>
      )}
    </>
  )
}

function WallEntry({
  entry,
  avatarSize,
  onReply,
  onDelete,
}: {
  entry: WallReply
  avatarSize: AvatarSize
  onReply: () => void
  onDelete: (id: number) => void
}) {
  return (
    <MediaObject
      media={
        <Link to={`/perfil/${entry.author.id}`} tabIndex={-1} aria-hidden="true">
          <Avatar src={entry.author.avatar_url} name={entry.author.first_name} size={avatarSize} decorative />
        </Link>
      }
      aside={
        <span className="wall__actions">
          <button
            type="button"
            className="link-button"
            aria-label={`Responder al mensaje de ${entry.author.first_name}`}
            onClick={onReply}
          >
            Responder
          </button>
          {entry.can_delete && (
            <button
              type="button"
              className="link-button"
              aria-label={`Borrar el mensaje de ${entry.author.first_name}`}
              onClick={() => onDelete(entry.id)}
            >
              Borrar
            </button>
          )}
        </span>
      }
    >
      <p className="feed__line">
        <Link to={`/perfil/${entry.author.id}`} className="person-name">
          {entry.author.first_name} {entry.author.last_name}
        </Link>{' '}
        <time className="muted" dateTime={entry.created_at}>
          {formatLong(entry.created_at)}
        </time>
      </p>
      <p className="comment__body">{linkify(entry.body)}</p>
      {entry.link_preview && <LinkPreviewCard preview={entry.link_preview} />}
    </MediaObject>
  )
}
