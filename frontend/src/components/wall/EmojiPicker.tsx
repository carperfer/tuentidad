import { useEffect, useId, useRef, useState } from 'react'

const EMOJIS = [
  '😀', '😄', '😂', '🤣', '😊', '😍', '😘', '😜',
  '😎', '🤔', '😏', '😴', '😢', '😭', '😡', '😱',
  '🥳', '🤗', '🙄', '😇', '👍', '👎', '👏', '🙌',
  '💪', '🙏', '👋', '✌️', '❤️', '💔', '🔥', '⭐',
  '🎉', '🎂', '🍻', '☕', '⚽', '🎵', '📷', '✈️',
]

/** Botón «☺» con la paleta de emoticonos; sigue abierta para poner varios y se cierra con Escape o al pulsar fuera. */
export function EmojiPicker({ onPick }: { onPick: (emoji: string) => void }) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  const panelId = useId()

  useEffect(() => {
    if (!open) return

    function handlePointer(event: PointerEvent) {
      if (!ref.current?.contains(event.target as Node)) setOpen(false)
    }
    function handleKey(event: KeyboardEvent) {
      if (event.key === 'Escape') setOpen(false)
    }
    document.addEventListener('pointerdown', handlePointer)
    document.addEventListener('keydown', handleKey)
    return () => {
      document.removeEventListener('pointerdown', handlePointer)
      document.removeEventListener('keydown', handleKey)
    }
  }, [open])

  return (
    <div className="emoji-picker" ref={ref}>
      <button
        type="button"
        className="emoji-picker__trigger"
        aria-label="Emoticonos"
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((current) => !current)}
      >
        <span aria-hidden="true">☺</span>
      </button>
      <div id={panelId} className="emoji-picker__panel" role="group" aria-label="Emoticonos" hidden={!open}>
        {EMOJIS.map((emoji) => (
          <button key={emoji} type="button" className="emoji-picker__emoji" onClick={() => onPick(emoji)}>
            {emoji}
          </button>
        ))}
      </div>
    </div>
  )
}
