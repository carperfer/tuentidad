import { useEffect, useId, useRef, useState, type ReactNode } from 'react'

/** Disparador con triángulo y panel flotante; se cierra con Escape o al pulsar fuera (guía §7.9). */
export function Dropdown({ label, children, align = 'right' }: { label: ReactNode; children: ReactNode; align?: 'left' | 'right' }) {
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
    <div className="dropdown" ref={ref}>
      <button
        type="button"
        className="dropdown__trigger"
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((current) => !current)}
      >
        {label} <span aria-hidden="true">▾</span>
      </button>
      <div id={panelId} className={`dropdown__panel dropdown__panel--${align}`} hidden={!open} onClick={() => setOpen(false)}>
        {children}
      </div>
    </div>
  )
}
