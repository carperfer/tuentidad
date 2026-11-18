import { api, post } from './client'
import type { Person } from './friends'

export type NotificationType = 'photo_tag' | 'photo_comment' | 'friend_request' | 'event_invite' | 'event_comment' | 'event_reminder'

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

export function listNotifications(): Promise<{ notifications: Notification[]; unread: UnreadCounts }> {
  return api('/api/notifications')
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
