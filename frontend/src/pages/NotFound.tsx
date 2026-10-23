import { Link } from 'react-router'

export function NotFound() {
  return (
    <section className="box">
      <h1>Página no encontrada</h1>
      <p>
        <Link to="/">Volver al inicio</Link>
      </p>
    </section>
  )
}
