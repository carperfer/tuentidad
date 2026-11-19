import { api, post } from './client'
import type { Person } from './friends'

export type NotificationType =
  | 'photo_tag'
  | 'photo_comment'
  | 'friend_request'
  | 'friend_accepted'
  | 'invitation_accepted'
  | 'event_invite'
  | 'event_comment'
  | 'event_reminder'
  | 'wall_post'

export type NotificationGroup = 'photos' | 'friends' | 'events'

export type Notification = {
  id: number
  type: NotificationType
  actor: Person
  photo: { id: number; album_id: number; thumb_url: string } | null
  event: { id: number; title: string } | null
  created_at: string
  read: boolean
}

export type UnreadCounts = Record<NotificationType | 'total', number>

export type NotificationPage = { notifications: Notification[]; next_before: number | null; unread: UnreadCounts }

/** Avisos de 20 en 20, del más reciente al más antiguo; before trae la página siguiente. */
export function listNotifications(options: { group?: NotificationGroup; before?: number } = {}): Promise<NotificationPage> {
  const params = new URLSearchParams()
  if (options.group) params.set('group', options.group)
  if (options.before) params.set('before', String(options.before))
  const query = params.toString()
  return api(`/api/notifications${query ? `?${query}` : ''}`)
}

export function markNotificationRead(id: number): Promise<{ unread: UnreadCounts }> {
  return post(`/api/notifications/${id}/read`)
}

export function getUnreadCounts(): Promise<{ unread: UnreadCounts }> {
  return api('/api/notifications/unread')
}

export function markNotificationsRead(): Promise<{ unread: UnreadCounts }> {
  return post('/api/notifications/read')
}

const CHANGED = 'tuentidad:notifications-changed'

/** Avisa a la topbar de que han cambiado los avisos sin leer (p. ej. al ver una foto). */
export function notifyNotificationsChanged(): void {
  window.dispatchEvent(new Event(CHANGED))
}

export function onNotificationsChanged(listener: () => void): () => void {
  window.addEventListener(CHANGED, listener)
  return () => window.removeEventListener(CHANGED, listener)
}
