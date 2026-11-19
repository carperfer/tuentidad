import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { listUserVideos, type Video } from '../../api/videos'
import { formatLong } from '../../lib/dates'
import { Module } from '../ui/Module'
import { Pager } from '../ui/Pager'
import { VideoPlayer } from '../videos/VideoPlayer'

/**
 * «Espacio personal»: los vídeos de la persona, uno a uno con paginador «1 de N»
 * (guía §6.2). Solo para quien puede verlos (la persona y sus amigos).
 */
export function PersonalSpace({ userId, own, firstName }: { userId: number; own: boolean; firstName: string }) {
  const [videos, setVideos] = useState<Video[]>([])
  const [total, setTotal] = useState<number | null>(null)
  const [nextBefore, setNextBefore] = useState<number | null>(null)
  const [index, setIndex] = useState(0)

  useEffect(() => {
    listUserVideos(userId)
      .then((page) => {
        setVideos(page.videos)
        setTotal(page.total ?? page.videos.length)
        setNextBefore(page.next_before)
      })
      .catch(() => setTotal(0))
  }, [userId])

  // Se piden más vídeos cuando el paginador llega al final de los cargados
  async function go(target: number) {
    if (target >= videos.length && nextBefore !== null) {
      const page = await listUserVideos(userId, nextBefore)
      setVideos((current) => [...current, ...page.videos])
      setNextBefore(page.next_before)
    }
    setIndex(target)
  }

  if (total === null || (total === 0 && !own)) return null

  const video = videos[index]
  const title = own ? 'Mi espacio personal' : `Espacio personal de ${firstName}`

  return (
    <Module title={title} action={own && <Link to="/videos">Nueva entrada</Link>}>
      {total === 0 || !video ? (
        <p className="status">
          <Link to="/videos">Comparte tu primer vídeo</Link> y aparecerá aquí.
        </p>
      ) : (
        <>
          <p className="feed__line">
            <strong>{video.title}</strong>{' '}
            <time className="muted" dateTime={video.created_at}>
              {formatLong(video.created_at)}
            </time>
          </p>
          <VideoPlayer key={video.id} video={video} />
          <Pager index={index} total={total} onChange={go} />
        </>
      )}
    </Module>
  )
}
