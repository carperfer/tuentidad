import { useState } from 'react'
import type { Video } from '../../api/videos'

/**
 * Vídeo de YouTube que no carga nada de YouTube hasta que se pulsa: así nadie
 * envía datos a terceros solo por abrir una página (se usa youtube-nocookie.com).
 */
export function VideoPlayer({ video }: { video: Pick<Video, 'youtube_id' | 'title'> }) {
  const [playing, setPlaying] = useState(false)

  if (playing) {
    return (
      <div className="video-player">
        <iframe
          src={`https://www.youtube-nocookie.com/embed/${video.youtube_id}?autoplay=1`}
          title={video.title}
          allow="autoplay; encrypted-media; picture-in-picture"
          allowFullScreen
        />
      </div>
    )
  }

  return (
    <button type="button" className="video-player video-player--idle" onClick={() => setPlaying(true)}>
      <span className="video-player__play" aria-hidden="true">
        ▶
      </span>
      <span className="video-player__title">{video.title}</span>
      <span className="visually-hidden">Reproducir «{video.title}»</span>
      <span className="video-player__note">Se reproduce desde YouTube</span>
    </button>
  )
}
