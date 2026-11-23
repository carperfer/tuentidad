import type { LinkPreview } from '../../api/wall'

/** Cuadro con la foto, el título y el resumen de un enlace; con onRemove, una ✕ para quitarlo antes de publicar. */
export function LinkPreviewCard({ preview, onRemove }: { preview: LinkPreview; onRemove?: () => void }) {
  return (
    <div className="link-card">
      <a className="link-card__link" href={preview.url} target="_blank" rel="noopener noreferrer nofollow ugc">
        {preview.image_url && <img className="link-card__image" src={preview.image_url} alt="" loading="lazy" />}
        <span className="link-card__text">
          <span className="link-card__title">{preview.title}</span>
          {preview.description && <span className="link-card__description">{preview.description}</span>}
          <span className="link-card__site">{preview.site_name}</span>
        </span>
      </a>
      {onRemove && (
        <button type="button" className="link-card__remove" aria-label="Quitar la vista previa del enlace" onClick={onRemove}>
          ✕
        </button>
      )}
    </div>
  )
}
