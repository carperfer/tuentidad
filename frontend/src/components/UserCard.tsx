import { Link } from 'react-router'
import { useAuth } from '../auth/context'
import { useUnreadCounts } from '../lib/useUnreadCounts'
import { Avatar } from './ui/Avatar'
import { Counter } from './ui/Module'

type UserCardProps = {
  friendsCount?: number
  pendingRequests?: number
}

/** Tarjeta del usuario actual con sus avisos (columna izquierda de Inicio, guía §6.1). */
export function UserCard({ friendsCount, pendingRequests = 0 }: UserCardProps) {
  const { user } = useAuth()
  const unread = useUnreadCounts()
  if (!user) return null

  const tags = unread?.photo_tag ?? 0
  const comments = unread?.photo_comment ?? 0
  const events = (unread?.event_invite ?? 0) + (unread?.event_comment ?? 0) + (unread?.event_reminder ?? 0)
  const friends = (unread?.friend_accepted ?? 0) + (unread?.invitation_accepted ?? 0)
  const wall = unread?.wall_post ?? 0

  return (
    <section className="module user-card" aria-label="Tu cuenta">
      <div className="module__body">
        <div className="media">
          <div className="media__avatar">
            <Avatar src={user.avatar_url} name={user.first_name} size={50} decorative />
          </div>
          <div className="media__body">
            <Link to={`/perfil/${user.id}`} className="user-card__name">
              {user.first_name} {user.last_name}
            </Link>
            {friendsCount !== undefined && (
              <p className="muted">{friendsCount === 1 ? '1 amigo' : `${friendsCount} amigos`}</p>
            )}
          </div>
        </div>
        <ul className="notifications">
          <li className={unread && unread.total > 0 ? 'notifications__new' : undefined}>
            <Link to="/avisos">
              Avisos <Counter value={unread?.total ?? 0} />
              {unread && unread.total > 0 && (
                <>
                  {' '}
                  <span className="visually-hidden">sin leer</span>
                </>
              )}
            </Link>
          </li>
          {tags > 0 && (
            <li className="notifications__new">
              <Link to="/avisos">{tags === 1 ? '1 etiqueta nueva' : `${tags} etiquetas nuevas`}</Link>
            </li>
          )}
          {comments > 0 && (
            <li className="notifications__new">
              <Link to="/avisos">{comments === 1 ? '1 comentario nuevo' : `${comments} comentarios nuevos`}</Link>
            </li>
          )}
          {pendingRequests > 0 && (
            <li>
              <Link to="/gente">
                {pendingRequests === 1 ? '1 solicitud de amistad' : `${pendingRequests} solicitudes de amistad`}
              </Link>
            </li>
          )}
          {friends > 0 && (
            <li className="notifications__new">
              <Link to="/avisos">{friends === 1 ? '1 amigo nuevo' : `${friends} amigos nuevos`}</Link>
            </li>
          )}
          {wall > 0 && (
            <li className="notifications__new">
              <Link to={`/perfil/${user.id}`}>{wall === 1 ? '1 mensaje en tu tablón' : `${wall} mensajes en tu tablón`}</Link>
            </li>
          )}
          {events > 0 && (
            <li className="notifications__new">
              <Link to="/avisos">{events === 1 ? '1 novedad en tus eventos' : `${events} novedades en tus eventos`}</Link>
            </li>
          )}
          <li>
            <Link to={`/perfil/${user.id}/fotos`}>Mis fotos</Link>
          </li>
          <li>
            <Link to="/eventos">Mis eventos</Link>
          </li>
          <li>
            <Link to={`/perfil/${user.id}/etiquetas`}>Fotos en las que salgo</Link>
          </li>
          <li>
            <Link to="/perfil/editar">Editar mi perfil</Link>
          </li>
        </ul>
      </div>
    </section>
  )
}
