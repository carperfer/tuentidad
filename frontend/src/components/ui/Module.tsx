import { useId, type ReactNode } from 'react'

type ModuleProps = {
  title: ReactNode
  /** Enlace o control alineado a la derecha de la cabecera. */
  action?: ReactNode
  footer?: ReactNode
  className?: string
  /** Nivel del título: h1 para el módulo principal de una página. */
  as?: 'h1' | 'h2'
  children: ReactNode
}

/** Caja con cabecera, cuerpo y pie: la pieza con la que se construyen todas las columnas (guía §6). */
export function Module({ title, action, footer, className = '', as: Heading = 'h2', children }: ModuleProps) {
  const id = useId()

  return (
    <section className={`module ${className}`.trim()} aria-labelledby={id}>
      <header className="module__header">
        <Heading className="module__title" id={id}>
          {title}
        </Heading>
        {action && <div className="module__action">{action}</div>}
      </header>
      <div className="module__body">{children}</div>
      {footer && <footer className="module__footer">{footer}</footer>}
    </section>
  )
}

/** Contador entre paréntesis en gris: "Álbumes (3)". */
export function Counter({ value }: { value: number }) {
  return <span className="counter">({value})</span>
}
