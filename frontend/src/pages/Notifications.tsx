import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../api/client'
import {
  listNotifications,
  markNotificationsRead,
  notifyNotificationsChanged,
  type Notification,
  type NotificationType,
} from '../api/notifications'
import { Alert } from '../components/Alert'
import { UserCard } from '../components/UserCard'
import { Avatar } from '../components/ui/Avatar'
import { Columns } from '../components/ui/Columns'
import { MediaObject } from '../components/ui/MediaObject'
import { Module } from '../components/ui/Module'
import { timeAgo } from '../lib/dates'

const TEXT: Record<NotificationType, string> = {
  photo_tag: 'te ha etiquetado en una foto',
  photo_comment: 'ha comentado una foto',
  friend_request: 'te ha enviado una solicitud de amistad',
}

function target(notification: Notification): string {
  if (notification.photo) return `/albumes/${notification.photo.album_id}/fotos/${notification.photo.id}`
  return '/gente'
}

/** Últimos avisos; al abrir la página se marcan todos como leídos. */
export function Notifications() {
  const [notifications, setNotifications] = useState<Notification[] | null>(null)
  const [error, setError] = useState('')

  useEffect(() => {
    listNotifications()
      .then(async ({ notifications: result, unread }) => {
        setNotifications(result)
        if (unread.total > 0) {
          await markNotificationsRead()
          notifyNotificationsChanged()
        }
      })
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [])

  return (
    <Columns left={<UserCard />}>
      <Module title="Avisos" as="h1">
        {error && <Alert>{error}</Alert>}
        {notifications === null ? (
          !error && <p className="status">Cargando avisos…</p>
        ) : notifications.length === 0 ? (
          <p className="status">No tienes avisos. Aquí verás cuando te etiqueten, comenten tus fotos o te pidan amistad.</p>
        ) : (
          <ul className="media-list notification-list">
            {notifications.map((notification) => (
              <li key={notification.id} className={notification.read ? undefined : 'notification-list__unread'}>
                <MediaObject
                  media={<Avatar src={notification.actor.avatar_url} name={notification.actor.first_name} size={40} decorative />}
                  aside={
                    notification.photo && (
                      <Link to={target(notification)} tabIndex={-1} aria-hidden="true">
                        <img className="notification-list__thumb" src={notification.photo.thumb_url} alt="" loading="lazy" />
                      </Link>
                    )
                  }
                >
                  <p className="feed__line">
                    <Link to={`/perfil/${notification.actor.id}`} className="person-name">
                      {notification.actor.first_name} {notification.actor.last_name}
                    </Link>{' '}
                    <Link to={target(notification)}>{TEXT[notification.type]}</Link>
                    {!notification.read && <span className="visually-hidden"> (nuevo)</span>}
                  </p>
                  <time className="muted" dateTime={notification.created_at}>
                    {timeAgo(notification.created_at)}
                  </time>
                </MediaObject>
              </li>
            ))}
          </ul>
        )}
      </Module>
    </Columns>
  )
}
