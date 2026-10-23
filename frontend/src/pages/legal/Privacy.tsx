import { Link } from 'react-router'
import { OWNER } from './owner'

export function Privacy() {
  return (
    <article className="box legal">
      <h1>Política de privacidad</h1>
      <p className="status">Última actualización: {OWNER.updated}</p>

      <h2>Quién trata tus datos</h2>
      <p>
        {OWNER.name} (NIF {OWNER.taxId}), {OWNER.address}. Contacto: {OWNER.email}.
      </p>
      <p>
        tuentidad.es es un proyecto formativo sin ánimo de lucro que recrea la experiencia de una red social privada
        entre amigos.
      </p>

      <h2>Qué datos tratamos y para qué</h2>
      <ul>
        <li>
          <strong>Tu cuenta:</strong> email, nombre, apellidos, fecha de nacimiento y contraseña (guardada cifrada de
          forma irreversible). Sirven para darte acceso y comprobar que tienes la edad mínima. Base legal: la ejecución
          del servicio que solicitas al registrarte.
        </li>
        <li>
          <strong>Invitaciones:</strong> el email de las personas a las que invitas, para enviarles la invitación.
          Base legal: interés legítimo en permitir que invites a tus amigos. Si la invitación no se acepta, se borra
          como máximo 30 días después de caducar.
        </li>
        <li>
          <strong>Solicitudes a los perfiles de bienvenida:</strong> el email que escribes en la portada, para enviarte
          el enlace de registro. Base legal: tu consentimiento, que guardamos con su fecha. Si no te registras, se borra
          como máximo 30 días después de que caduque el enlace.
        </li>
        <li>
          <strong>Seguridad:</strong> dirección IP y registros técnicos de acceso, para limitar intentos abusivos y
          proteger las cuentas. Base legal: interés legítimo en la seguridad del servicio.
        </li>
      </ul>

      <h2>Edad mínima</h2>
      <p>Para registrarte tienes que tener al menos 14 años.</p>

      <h2>Con quién se comparten</h2>
      <p>
        No cedemos tus datos a terceros. El alojamiento web, la base de datos y el envío de emails los presta OVHcloud
        (en la Unión Europea) como encargado del tratamiento.
      </p>

      <h2>Cuánto tiempo se guardan</h2>
      <p>
        Los datos de tu cuenta se conservan mientras la mantengas. Las invitaciones y solicitudes no aceptadas se
        borran como se indica arriba.
      </p>

      <h2>Tus derechos</h2>
      <p>
        Puedes pedir el acceso, la rectificación, la supresión, la limitación, la portabilidad de tus datos u oponerte
        a su tratamiento escribiendo a {OWNER.email}. Si das tu consentimiento, puedes retirarlo en cualquier momento.
        Si no estás conforme con la respuesta, puedes reclamar ante la Agencia Española de Protección de Datos
        (aepd.es).
      </p>

      <p>
        Consulta también las <Link to="/condiciones">condiciones de uso</Link> y la{' '}
        <Link to="/cookies">política de cookies</Link>.
      </p>
    </article>
  )
}
