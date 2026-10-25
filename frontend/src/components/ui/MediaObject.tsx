import type { ReactNode } from 'react'

/** Avatar a la izquierda y contenido a la derecha: mensajes, novedades y listas de personas (guía §7.4). */
export function MediaObject({ media, children, aside }: { media: ReactNode; children: ReactNode; aside?: ReactNode }) {
  return (
    <div className="media">
      <div className="media__avatar">{media}</div>
      <div className="media__body">{children}</div>
      {aside && <div className="media__aside">{aside}</div>}
    </div>
  )
}
