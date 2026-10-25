type PagerProps = {
  /** Posición actual, empezando en 0. */
  index: number
  total: number
  onChange: (index: number) => void
}

/** "1 de 17" con botones de primero, anterior, siguiente y último (guía §7.2). */
export function Pager({ index, total, onChange }: PagerProps) {
  const first = index <= 0
  const last = index >= total - 1

  return (
    <nav className="pager" aria-label="Paginación">
      <span className="pager__position">
        {index + 1} de {total}
      </span>
      <button type="button" className="icon-button" aria-label="Primera" disabled={first} onClick={() => onChange(0)}>
        «
      </button>
      <button type="button" className="icon-button" aria-label="Anterior" disabled={first} onClick={() => onChange(index - 1)}>
        ‹
      </button>
      <button type="button" className="icon-button" aria-label="Siguiente" disabled={last} onClick={() => onChange(index + 1)}>
        ›
      </button>
      <button type="button" className="icon-button" aria-label="Última" disabled={last} onClick={() => onChange(total - 1)}>
        »
      </button>
    </nav>
  )
}
