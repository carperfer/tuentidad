import { Link } from 'react-router'
import type { FriendActivity } from '../api/friends'
import { timeAgo } from '../lib/dates'
import { Avatar } from './ui/Avatar'
import { MediaObject } from './ui/MediaObject'
import { Module } from './ui/Module'

/** "Novedades": últimos estados de los amigos (guía §6.2, ActivityFeed). */
export function ActivityFeed({ activity }: { activity: FriendActivity[] | null }) {
  return (
    <Module title="Novedades de tus amigos" as="h1">
      {activity === null ? (
        <p className="status">Cargando novedades…</p>
      ) : activity.length === 0 ? (
        <p className="status">
          Todavía no hay novedades. <Link to="/gente">Busca a tus amigos</Link> para ver aquí lo que cuentan.
        </p>
      ) : (
        <ul className="media-list feed">
          {activity.map((item) => (
            <li key={item.id}>
              <MediaObject
                media={
                  <Link to={`/perfil/${item.id}`} tabIndex={-1} aria-hidden="true">
                    <Avatar src={item.avatar_url} name={item.first_name} size={40} decorative />
                  </Link>
                }
              >
                <p className="feed__line">
                  <Link to={`/perfil/${item.id}`} className="person-name">
                    {item.first_name} {item.last_name}
                  </Link>{' '}
                  {item.status}
                </p>
                {item.updated_at && (
                  <time className="muted" dateTime={item.updated_at}>
                    {timeAgo(item.updated_at)}
                  </time>
                )}
              </MediaObject>
            </li>
          ))}
        </ul>
      )}
    </Module>
  )
}
