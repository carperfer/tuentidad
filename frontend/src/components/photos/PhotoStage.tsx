import { useRef, useState, type PointerEvent } from 'react'
import type { Photo, PhotoTag, TagArea } from '../../api/photos'
import { areaAround, clamp, round } from '../../lib/tagArea'

type PhotoStageProps = {
  photo: Photo
  alt: string
  tags: PhotoTag[]
  /** Etiqueta resaltada al pasar por su nombre en la lista. */
  highlightId: number | null
  /** Zona que se está marcando para una etiqueta nueva; con ella, arrastrar dibuja otra. */
  area: TagArea | null
  onAreaChange: (area: TagArea) => void
}

function style(area: TagArea) {
  return { left: `${area.x}%`, top: `${area.y}%`, width: `${area.width}%`, height: `${area.height}%` }
}

/**
 * Foto con las zonas de sus etiquetas encima. Mientras se etiqueta, se arrastra
 * para marcar dónde sale la persona (o se hace clic para una zona cuadrada).
 */
export function PhotoStage({ photo, alt, tags, highlightId, area, onAreaChange }: PhotoStageProps) {
  const stage = useRef<HTMLDivElement>(null)
  const [start, setStart] = useState<{ x: number; y: number } | null>(null)
  const drawing = area !== null

  function point(event: PointerEvent<HTMLDivElement>) {
    const rect = event.currentTarget.getBoundingClientRect()
    return {
      x: clamp(((event.clientX - rect.left) / rect.width) * 100, 0, 100),
      y: clamp(((event.clientY - rect.top) / rect.height) * 100, 0, 100),
    }
  }

  function handlePointerDown(event: PointerEvent<HTMLDivElement>) {
    if (!drawing || event.button !== 0) return
    event.preventDefault()
    stage.current?.setPointerCapture?.(event.pointerId)
    const origin = point(event)
    setStart(origin)
    onAreaChange(areaAround(photo, origin.x, origin.y))
  }

  function handlePointerMove(event: PointerEvent<HTMLDivElement>) {
    if (!start) return
    const current = point(event)
    const width = Math.abs(current.x - start.x)
    const height = Math.abs(current.y - start.y)
    // Un arrastre muy corto cuenta como clic y deja la zona cuadrada
    if (width < 2 || height < 2) return
    onAreaChange({
      x: round(Math.min(start.x, current.x)),
      y: round(Math.min(start.y, current.y)),
      width: round(width),
      height: round(height),
    })
  }

  return (
    <div className="photo-viewer__frame">
      <div
        ref={stage}
        className={`photo-stage${drawing ? ' photo-stage--drawing' : ''}`}
        // Ancho de la foto tal como se muestra (como mucho, 75 % del alto de la ventana),
        // para que las zonas en % coincidan con la imagen
        style={{ width: `min(100%, ${photo.width}px, calc(75vh * ${photo.width / photo.height}))` }}
        onPointerDown={handlePointerDown}
        onPointerMove={handlePointerMove}
        onPointerUp={() => setStart(null)}
        onPointerCancel={() => setStart(null)}
      >
        <img
          className="photo-viewer__image"
          src={photo.url}
          width={photo.width}
          height={photo.height}
          alt={alt}
          draggable={false}
        />
        {!drawing &&
          tags.map((tag) => (
            <span
              key={tag.id}
              className={`photo-tag${tag.id === highlightId ? ' photo-tag--active' : ''}`}
              style={style(tag)}
              aria-hidden="true"
            >
              <span className="photo-tag__label">
                {tag.user.first_name} {tag.user.last_name}
              </span>
            </span>
          ))}
        {area && <span className="photo-tag photo-tag--new" style={style(area)} aria-hidden="true" />}
      </div>
    </div>
  )
}
