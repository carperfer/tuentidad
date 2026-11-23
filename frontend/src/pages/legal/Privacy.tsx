import { Link } from 'react-router'
import { OWNER } from './owner'
import { Columns } from '../../components/ui/Columns'

export function Privacy() {
  return (
    <Columns>
      <article className="module legal">
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
          <strong>Tu perfil:</strong> biografía, estado, foto de perfil, «Redes» (estudios, trayectoria profesional y
          colegios con sus años) y enlaces a tus perfiles en otras redes sociales, si decides añadirlos. Tú eliges quién
          ve cada dato (todos los usuarios, solo tus amigos o solo tú). Base legal: la ejecución del servicio.
        </li>
        <li>
          <strong>Amigos, fotos, etiquetas y comentarios:</strong> tus amistades y solicitudes, los álbumes y fotos que
          subes (solo los ven tus amigos; al subirlas se eliminan sus metadatos, como la ubicación), las etiquetas en
          las que sales (puedes quitarlas tú), los comentarios que escribes y los mensajes del tablón y sus respuestas
          (los ven la persona y sus amigos; los borran su autor y el dueño del tablón). Base legal: la ejecución del
          servicio.
        </li>
        <li>
          <strong>Enlaces en el tablón:</strong> si un mensaje lleva un enlace, nuestro servidor visita esa página para
          mostrar su título, su resumen y su foto, y guarda una copia reducida de la foto. Así quien lee el tablón no
          carga nada de la otra web; esa web solo ve una visita de nuestro servidor, nunca tus datos. Puedes quitar la
          vista previa antes de publicar, y se borra cuando ya no la usa ningún mensaje. Base legal: la ejecución del
          servicio.
        </li>
        <li>
          <strong>Chat:</strong> los mensajes que envías y recibes, que solo ven las dos personas de la conversación, y
          la hora de tu última actividad, para mostrar a tus amigos si estás conectado (en los últimos 2 minutos). Base
          legal: la ejecución del servicio.
        </li>
        <li>
          <strong>Vídeos:</strong> los enlaces de YouTube que compartes y su título, que solo ven tus amigos. La página no
          carga nada de YouTube hasta que pulsas para reproducir un vídeo; entonces se reproduce desde
          youtube-nocookie.com y Google recibe los datos técnicos de esa reproducción. Base legal: la ejecución del
          servicio.
        </li>
        <li>
          <strong>Eventos:</strong> los eventos que organizas, a quién invitas, tus respuestas y tus comentarios. Solo los
          ven quien organiza y sus invitados. Si dejas ver tu fecha de nacimiento a tus amigos, tu cumpleaños aparece en
          su calendario. Si dices que vas o que quizás vas, te enviamos un recordatorio por email el día antes. Base
          legal: la ejecución del servicio.
        </li>
        <li>
          <strong>Avisos y emails de resumen:</strong> los avisos de la web (etiquetas, comentarios, amistades y
          eventos). El email de resumen de avisos y mensajes sin leer solo se envía si lo activas en Mi cuenta, y
          puedes desactivarlo cuando quieras. Base legal: la ejecución del servicio para los avisos y tu consentimiento
          para el resumen.
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
        borran como se indica arriba. Si borras tu cuenta, sus datos y tus fotos se eliminan en ese momento; pueden
        seguir hasta 14 días en las copias de seguridad diarias de la base de datos, que se borran solas pasado ese
        plazo.
      </p>

      <h2>Tus derechos</h2>
      <p>
        Desde <Link to="/perfil/editar">Mi cuenta</Link> puedes corregir tus datos, descargar una copia de todos ellos
        y borrar tu cuenta cuando quieras.
      </p>
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
    </Columns>
  )
}
