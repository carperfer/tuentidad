import { useEffect, useRef, useState, type FormEvent, type KeyboardEvent, type ReactNode } from 'react'
import { previewLink, type LinkPreview } from '../../api/wall'
import { firstUrl } from '../../lib/links'
import { EmojiPicker } from './EmojiPicker'
import { LinkPreviewCard } from './LinkPreviewCard'

const PREVIEW_DELAY = 600

type WallComposerProps = {
  id: string
  label: string
  placeholder?: string
  submitLabel?: string
  autoFocus?: boolean
  compact?: boolean
  /** Lo que va delante de la caja (el avatar de quien escribe). */
  leading?: ReactNode
  /** Lanza un error si no se ha podido publicar (el texto se conserva). */
  onSubmit: (body: string, linkPreview: boolean) => Promise<void>
}

/**
 * Caja para escribir en el tablón con emoticonos y, si el texto lleva un
 * enlace, la vista previa que se publicará con él (la lee el servidor).
 */
export function WallComposer({ id, label, placeholder = 'Escribe aquí…', submitLabel = 'Publicar', autoFocus, compact, leading, onSubmit }: WallComposerProps) {
  const [body, setBody] = useState('')
  const [sending, setSending] = useState(false)
  const [preview, setPreview] = useState<{ url: string; card: LinkPreview | null } | null>(null)
  const [dismissed, setDismissed] = useState<string | null>(null)
  const textarea = useRef<HTMLTextAreaElement>(null)

  const url = firstUrl(body)
  const wanted = url !== null && url !== dismissed

  useEffect(() => {
    if (!wanted || preview?.url === url) return
    let active = true
    const timer = window.setTimeout(() => {
      previewLink(url)
        .then(({ preview: card }) => {
          if (active) setPreview({ url, card })
        })
        .catch(() => {
          if (active) setPreview({ url, card: null })
        })
    }, PREVIEW_DELAY)
    return () => {
      active = false
      window.clearTimeout(timer)
    }
  }, [url, wanted, preview?.url])

  const card = wanted && preview?.url === url ? preview.card : null

  function insertEmoji(emoji: string) {
    const field = textarea.current
    const start = field?.selectionStart ?? body.length
    const end = field?.selectionEnd ?? body.length
    setBody(body.slice(0, start) + emoji + body.slice(end))
    requestAnimationFrame(() => {
      field?.focus()
      field?.setSelectionRange(start + emoji.length, start + emoji.length)
    })
  }

  async function submit() {
    if (body.trim() === '' || sending) return
    setSending(true)
    try {
      // Sin enlace da igual; con enlace, false solo si se ha quitado su tarjeta
      await onSubmit(body, url === null || wanted)
      setBody('')
      setPreview(null)
      setDismissed(null)
    } catch {
      // Quien usa la caja muestra el error; el texto se queda para reintentar
    } finally {
      setSending(false)
    }
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    void submit()
  }

  function handleKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
      event.preventDefault()
      void submit()
    }
  }

  return (
    <form className={`composer wall-composer${compact ? ' wall-composer--compact' : ''}`} onSubmit={handleSubmit}>
      {leading}
      <div className="wall-composer__main">
        <label className="visually-hidden" htmlFor={id}>
          {label}
        </label>
        <textarea
          id={id}
          ref={textarea}
          rows={compact ? 1 : 2}
          maxLength={1000}
          placeholder={placeholder}
          value={body}
          autoFocus={autoFocus}
          onChange={(event) => setBody(event.target.value)}
          onKeyDown={handleKeyDown}
        />
        {card && <LinkPreviewCard preview={card} onRemove={() => setDismissed(url)} />}
      </div>
      <EmojiPicker onPick={insertEmoji} />
      <button type="submit" className="button" disabled={sending || body.trim() === ''}>
        {submitLabel}
      </button>
    </form>
  )
}
