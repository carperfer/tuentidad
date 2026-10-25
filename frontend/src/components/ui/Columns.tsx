import type { ReactNode } from 'react'

type ColumnsProps = {
  left?: ReactNode
  right?: ReactNode
  /** Una sola columna estrecha y centrada (formularios de acceso). */
  narrow?: boolean
  /** Contenido de la columna central. */
  children: ReactNode
}

/**
 * Retícula de tres columnas (240 px / flexible / 190 px) de la guía §2.1.
 * Las columnas laterales son opcionales; sin ellas la central ocupa su espacio.
 */
export function Columns({ left, right, narrow = false, children }: ColumnsProps) {
  const variant = left && right ? 'three' : left ? 'left' : right ? 'right' : narrow ? 'narrow' : 'single'

  return (
    <div className={`layout layout--${variant}`}>
      {left && (
        <aside className="layout__left" aria-label="Columna izquierda">
          {left}
        </aside>
      )}
      <main className="layout__main" id="contenido">
        {children}
      </main>
      {right && (
        <aside className="layout__right" aria-label="Columna derecha">
          {right}
        </aside>
      )}
    </div>
  )
}
