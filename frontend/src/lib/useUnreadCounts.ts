import { useEffect, useState } from 'react'
import { useLocation } from 'react-router'
import { getUnreadCounts, onNotificationsChanged, type UnreadCounts } from '../api/notifications'
import { useAuth } from '../auth/context'

/** Avisos sin leer: se piden al cambiar de página y cuando otra parte de la app los cambia. */
export function useUnreadCounts(): UnreadCounts | null {
  const { user } = useAuth()
  const userId = user?.id
  const { pathname } = useLocation()
  const [unread, setUnread] = useState<UnreadCounts | null>(null)

  useEffect(() => {
    if (userId === undefined) return
    let active = true
    const refresh = () => {
      getUnreadCounts()
        .then(({ unread: counts }) => {
          if (active) setUnread(counts)
        })
        .catch(() => undefined)
    }
    refresh()
    const unsubscribe = onNotificationsChanged(refresh)
    return () => {
      active = false
      unsubscribe()
    }
  }, [userId, pathname])

  return userId === undefined ? null : unread
}
