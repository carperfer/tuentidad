import { Link } from 'react-router'
import type { Photo } from '../api/photos'
import { Counter, Module } from './ui/Module'

type TaggedPhotosModuleProps = {
  /** Nombre de la persona, o null si es el usuario actual ("Fotos en las que salgo"). */
  name: string | null
  photos: Photo[]
  total: number
  /** Enlace "Ver todas"; sin él, el módulo ya muestra la lista completa. */
  moreTo?: string
  as?: 'h1' | 'h2'
}

/** "Fotos en las que sale X": rejilla de 5 columnas de miniaturas (guía §5.2). */
export function TaggedPhotosModule({ name, photos, total, moreTo, as }: TaggedPhotosModuleProps) {
  const title = name === null ? 'Fotos en las que salgo' : `Fotos en las que sale ${name}`

  return (
    <Module
      title={
        <>
          {title} <Counter value={total} />
        </>
      }
      as={as}
      footer={
        moreTo &&
        total > photos.length && (
          <Link to={moreTo}>
            Ver todas <Counter value={total} />
          </Link>
        )
      }
    >
      {photos.length === 0 ? (
        <p className="status">
          {name === null ? 'Todavía no te han etiquetado en ninguna foto.' : 'No hay fotos en las que salga que puedas ver.'}
        </p>
      ) : (
        <ul className="photo-grid photo-grid--five">
          {photos.map((photo, index) => (
            <li key={photo.id}>
              <Link to={`/albumes/${photo.album_id}/fotos/${photo.id}`}>
                <img src={photo.thumb_url} alt={`${title}: foto ${index + 1}`} loading="lazy" />
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Module>
  )
}
