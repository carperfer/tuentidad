import { Link } from 'react-router'
import { OWNER } from './owner'

const COOKIES = [
  { name: 'tuentidad_session', purpose: 'Mantener tu sesión iniciada y proteger los formularios', duration: '2 horas' },
  { name: 'tuentidad_remember', purpose: 'Recordar tu sesión si marcas «Recordarme»', duration: '30 días' },
]

export function Cookies() {
  return (
    <article className="box legal">
      <h1>Política de cookies</h1>
      <p className="status">Última actualización: {OWNER.updated}</p>

      <p>
        tuentidad.es solo usa <strong>cookies técnicas</strong>, imprescindibles para que funcione el inicio de sesión.
        No usamos cookies de análisis, publicidad ni de terceros, por eso no te pedimos consentimiento para ellas.
      </p>

      <div className="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Cookie</th>
              <th>Finalidad</th>
              <th>Duración</th>
            </tr>
          </thead>
          <tbody>
            {COOKIES.map((cookie) => (
              <tr key={cookie.name}>
                <td>
                  <code>{cookie.name}</code>
                </td>
                <td>{cookie.purpose}</td>
                <td>{cookie.duration}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <p>
        Puedes borrarlas o bloquearlas desde la configuración de tu navegador, pero entonces no podrás iniciar sesión.
      </p>

      <p>
        Más información en la <Link to="/privacidad">política de privacidad</Link>.
      </p>
    </article>
  )
}
