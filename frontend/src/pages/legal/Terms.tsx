import { Link } from 'react-router'
import { OWNER } from './owner'

export function Terms() {
  return (
    <article className="box legal">
      <h1>Condiciones de uso</h1>
      <p className="status">Última actualización: {OWNER.updated}</p>

      <h2>Qué es tuentidad</h2>
      <p>
        tuentidad.es es un proyecto formativo, gestionado por {OWNER.name}, que recrea una red social privada para
        estar en contacto con tus amigos de verdad. Se ofrece gratis y sin garantías de disponibilidad.
      </p>

      <h2>Acceso</h2>
      <ul>
        <li>Solo se entra por invitación de otro usuario o pidiendo amistad a un perfil de bienvenida.</li>
        <li>Tienes que tener al menos 14 años y dar datos reales.</li>
        <li>Tu cuenta es personal: no compartas tu contraseña.</li>
      </ul>

      <h2>Normas de convivencia</h2>
      <p>No está permitido publicar ni enviar contenido que:</p>
      <ul>
        <li>sea ilegal, violento, de odio o acoso;</li>
        <li>muestre o etiquete a otras personas sin su permiso;</li>
        <li>infrinja derechos de autor o de imagen;</li>
        <li>sea spam o publicidad no solicitada.</li>
      </ul>
      <p>
        Las invitaciones son para personas que conoces. Podemos suspender o borrar las cuentas que incumplan estas
        normas.
      </p>

      <h2>Perfiles de bienvenida</h2>
      <p>Los perfiles marcados como «Perfil de demostración» no son personas reales: existen para ayudarte a entrar.</p>

      <h2>Darte de baja</h2>
      <p>Puedes pedir el borrado de tu cuenta en cualquier momento escribiendo a {OWNER.email}.</p>

      <h2>Legislación</h2>
      <p>Estas condiciones se rigen por la legislación española.</p>

      <p>
        Consulta también la <Link to="/privacidad">política de privacidad</Link> y la{' '}
        <Link to="/cookies">política de cookies</Link>.
      </p>
    </article>
  )
}
