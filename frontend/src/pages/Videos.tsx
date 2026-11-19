import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { errorMessage, fieldErrors } from '../api/client'
import { deleteVideo, listVideoFeed, shareVideo, type Video } from '../api/videos'
import { Alert } from '../components/Alert'
import { Field } from '../components/Field'
import { UserCard } from '../components/UserCard'
import { Columns } from '../components/ui/Columns'
import { Module } from '../components/ui/Module'
import { VideoPlayer } from '../components/videos/VideoPlayer'
import { timeAgo } from '../lib/dates'

/** Vídeos de YouTube compartidos por ti y tus amigos, y formulario para compartir uno. */
export function Videos() {
  const [videos, setVideos] = useState<Video[] | null>(null)
  const [nextBefore, setNextBefore] = useState<number | null>(null)
  const [url, setUrl] = useState('')
  const [title, setTitle] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState('')
  const [sharing, setSharing] = useState(false)

  useEffect(() => {
    listVideoFeed()
      .then((page) => {
        setVideos(page.videos)
        setNextBefore(page.next_before)
      })
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [])

  async function handleShare(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSharing(true)
    setErrors({})
    setError('')
    try {
      const { video } = await shareVideo(url, title)
      setVideos((current) => [video, ...(current ?? [])])
      setUrl('')
      setTitle('')
    } catch (caught) {
      setErrors(fieldErrors(caught))
      if (Object.keys(fieldErrors(caught)).length === 0) setError(errorMessage(caught))
    } finally {
      setSharing(false)
    }
  }

  async function handleDelete(id: number) {
    try {
      await deleteVideo(id)
      setVideos((current) => current?.filter((video) => video.id !== id) ?? null)
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  async function loadMore() {
    if (nextBefore === null) return
    try {
      const page = await listVideoFeed(nextBefore)
      setVideos((current) => [...(current ?? []), ...page.videos])
      setNextBefore(page.next_before)
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  return (
    <Columns
      left={
        <>
          <UserCard />
          <Module title="Comparte un vídeo">
            <p className="muted">Pega un enlace de YouTube. Solo lo verán tus amigos.</p>
            <form onSubmit={handleShare} noValidate>
              <Field
                label="Enlace de YouTube"
                type="url"
                placeholder="https://www.youtube.com/watch?v=…"
                value={url}
                onChange={(event) => setUrl(event.target.value)}
                error={errors.url}
              />
              <Field
                label="Título"
                maxLength={150}
                value={title}
                onChange={(event) => setTitle(event.target.value)}
                error={errors.title}
              />
              <button type="submit" className="button" disabled={sharing}>
                Compartir
              </button>
            </form>
          </Module>
        </>
      }
    >
      <Module title="Vídeos" as="h1">
        {error && <Alert>{error}</Alert>}
        {videos === null ? (
          !error && <p className="status">Cargando vídeos…</p>
        ) : videos.length === 0 ? (
          <p className="status">Todavía no hay vídeos. Comparte el primero.</p>
        ) : (
          <>
            <ul className="media-list video-list">
              {videos.map((video) => (
                <li key={video.id}>
                  <p className="feed__line">
                    <Link to={`/perfil/${video.owner.id}`} className="person-name">
                      {video.owner.first_name} {video.owner.last_name}
                    </Link>{' '}
                    <time className="muted" dateTime={video.created_at}>
                      {timeAgo(video.created_at)}
                    </time>
                    {video.is_owner && (
                      <>
                        {' '}
                        <button
                          type="button"
                          className="link-button"
                          aria-label={`Borrar el vídeo «${video.title}»`}
                          onClick={() => handleDelete(video.id)}
                        >
                          Borrar
                        </button>
                      </>
                    )}
                  </p>
                  <VideoPlayer video={video} />
                </li>
              ))}
            </ul>
            {nextBefore !== null && (
              <p className="load-more">
                <button type="button" className="button" onClick={loadMore}>
                  Ver más
                </button>
              </p>
            )}
          </>
        )}
      </Module>
    </Columns>
  )
}
