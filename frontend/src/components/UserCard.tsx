import { Link } from 'react-router'
import { useAuth } from '../auth/context'
import { Avatar } from './ui/Avatar'

type UserCardProps = {
  friendsCount?: number
  pendingRequests?: number
}

/** Tarjeta del usuario actual con sus avisos (columna izquierda de Inicio, guía §6.1). */
export function UserCard({ friendsCount, pendingRequests = 0 }: UserCardProps) {
  const { user } = useAuth()
  if (!user) return null

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
          {pendingRequests > 0 && (
            <li>
              <Link to="/gente">
                {pendingRequests === 1 ? '1 solicitud de amistad' : `${pendingRequests} solicitudes de amistad`}
              </Link>
            </li>
          )}
          <li>
            <Link to={`/perfil/${user.id}/fotos`}>Mis fotos</Link>
          </li>
          <li>
            <Link to="/perfil/editar">Editar mi perfil</Link>
          </li>
        </ul>
      </div>
    </section>
  )
}
