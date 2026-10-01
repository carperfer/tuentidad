import { useEffect, useState } from 'react'
import { getHealth, type Health } from '../api/health'

export function Landing() {
  const [health, setHealth] = useState<Health | null>(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    getHealth()
      .then(setHealth)
      .catch(() => setFailed(true))
  }, [])

  return (
    <div className="landing">
      <section className="box landing__intro">
        <h1>Bienvenido a tuentidad</h1>
        <p>
          La red social privada para estar en contacto con tus amigos de
          verdad. Solo se entra por invitación.
        </p>
      </section>

      <aside className="box">
        <h2 className="box__title">Estado del sistema</h2>
        {failed && <p className="status status--error">API no disponible</p>}
        {!failed && !health && <p className="status">Comprobando…</p>}
        {health && (
          <p className={`status status--${health.status === 'ok' ? 'ok' : 'error'}`}>
            API: {health.status} · Base de datos: {health.database}
          </p>
        )}
      </aside>
    </div>
  )
}
