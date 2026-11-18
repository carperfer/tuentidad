import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../api/client'
import {
  listNotifications,
  markNotificationRead,
  markNotificationsRead,
  notifyNotificationsChanged,
  type Notification,
  type NotificationGroup,
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
  friend_accepted: 'ha aceptado tu solicitud de amistad',
  invitation_accepted: 'ha aceptado tu invitación y ya sois amigos',
  event_invite: 'te ha invitado a',
  event_comment: 'ha comentado en',
  event_reminder: 'te recuerda que mañana es',
}

const FILTERS: { group: NotificationGroup | null; label: string }[] = [
  { group: null, label: 'Todos' },
  { group: 'photos', label: 'Fotos' },
  { group: 'events', label: 'Eventos' },
  { group: 'friends', label: 'Amistad' },
]

function target(notification: Notification): string {
  if (notification.photo) return `/albumes/${notification.photo.album_id}/fotos/${notification.photo.id}`
  if (notification.event) return `/eventos/${notification.event.id}`
  if (notification.type === 'friend_request') return '/gente'
  return `/perfil/${notification.actor.id}`
}

/** Centro de avisos: todos los tipos, con filtros, paginación y marcado como leídos. */
export function Notifications() {
  const [group, setGroup] = useState<NotificationGroup | null>(null)
  const [notifications, setNotifications] = useState<Notification[] | null>(null)
  const [nextBefore, setNextBefore] = useState<number | null>(null)
  const [unreadTotal, setUnreadTotal] = useState(0)
  const [error, setError] = useState('')
  const [loadingMore, setLoadingMore] = useState(false)

  useEffect(() => {
    let active = true
    listNotifications({ group: group ?? undefined })
      .then((page) => {
        if (!active) return
        setNotifications(page.notifications)
        setNextBefore(page.next_before)
        setUnreadTotal(page.unread.total)
      })
      .catch((caught: unknown) => setError(errorMessage(caught)))
    return () => {
      active = false
    }
  }, [group])

  async function loadMore() {
    if (nextBefore === null) return
    setLoadingMore(true)
    try {
      const page = await listNotifications({ group: group ?? undefined, before: nextBefore })
      setNotifications((current) => [...(current ?? []), ...page.notifications])
      setNextBefore(page.next_before)
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setLoadingMore(false)
    }
  }

  async function markAll() {
    try {
      await markNotificationsRead()
      setNotifications((current) => current?.map((item) => ({ ...item, read: true })) ?? null)
      setUnreadTotal(0)
      notifyNotificationsChanged()
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  /** Al abrir un aviso se marca como leído; la página de destino no espera a la respuesta. */
  function open(notification: Notification) {
    if (notification.read) return
    markNotificationRead(notification.id)
      .then(() => notifyNotificationsChanged())
      .catch(() => undefined)
  }

  return (
    <Columns left={<UserCard />}>
      <Module
        title="Avisos"
        as="h1"
        action={
          unreadTotal > 0 && (
            <button type="button" className="link-button" onClick={markAll}>
              Marcar todos como leídos
            </button>
          )
        }
      >
        <div className="filters" role="group" aria-label="Filtrar avisos">
          {FILTERS.map((filter) => (
            <button
              key={filter.label}
              type="button"
              className={`filters__option${filter.group === group ? ' filters__option--active' : ''}`}
              aria-pressed={filter.group === group}
              onClick={() => {
                if (filter.group === group) return
                setNotifications(null)
                setGroup(filter.group)
              }}
            >
              {filter.label}
            </button>
          ))}
        </div>
        {error && <Alert>{error}</Alert>}
        {notifications === null ? (
          !error && <p className="status">Cargando avisos…</p>
        ) : notifications.length === 0 ? (
          <p className="status">
            {group === null
              ? 'No tienes avisos. Aquí verás cuando te etiqueten, comenten tus fotos, te pidan amistad o te inviten a un evento.'
              : 'No tienes avisos de este tipo.'}
          </p>
        ) : (
          <>
            <ul className="media-list notification-list">
              {notifications.map((notification) => (
                <li key={notification.id} className={notification.read ? undefined : 'notification-list__unread'}>
                  <MediaObject
                    media={
                      <Avatar src={notification.actor.avatar_url} name={notification.actor.first_name} size={40} decorative />
                    }
                    aside={
                      notification.photo && (
                        <Link to={target(notification)} tabIndex={-1} aria-hidden="true" onClick={() => open(notification)}>
                          <img className="notification-list__thumb" src={notification.photo.thumb_url} alt="" loading="lazy" />
                        </Link>
                      )
                    }
                  >
                    <p className="feed__line">
                      <Link to={`/perfil/${notification.actor.id}`} className="person-name">
                        {notification.actor.first_name} {notification.actor.last_name}
                      </Link>{' '}
                      <Link to={target(notification)} onClick={() => open(notification)}>
                        {TEXT[notification.type]}
                        {notification.event && ` «${notification.event.title}»`}
                      </Link>
                      {!notification.read && <span className="visually-hidden"> (nuevo)</span>}
                    </p>
                    <time className="muted" dateTime={notification.created_at}>
                      {timeAgo(notification.created_at)}
                    </time>
                  </MediaObject>
                </li>
              ))}
            </ul>
            {nextBefore !== null && (
              <p className="load-more">
                <button type="button" className="button" disabled={loadingMore} onClick={loadMore}>
                  {loadingMore ? 'Cargando…' : 'Ver más'}
                </button>
              </p>
            )}
          </>
        )}
      </Module>
    </Columns>
  )
}
