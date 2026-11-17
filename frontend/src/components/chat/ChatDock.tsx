import { useChat } from '../../lib/chat/context'
import { Avatar } from '../ui/Avatar'
import { ChatWindow } from './ChatWindow'

/**
 * Barra fija al pie con las conversaciones abiertas y sus ventanas flotantes
 * encima (guía §8). En móvil, la ventana ocupa toda la pantalla.
 */
export function ChatDock() {
  const chat = useChat()
  if (!chat) return null

  const { service, state } = chat
  // Solo se muestran las conversaciones con amigos actuales
  const windows = state.windows.flatMap((window) => {
    const contact = state.contacts.find((item) => item.id === window.friendId)
    return contact ? [{ ...window, contact }] : []
  })
  if (windows.length === 0) return null

  return (
    <div className="chat-dock" role="region" aria-label="Conversaciones abiertas">
      <div className="chat-dock__windows">
        {windows
          .filter((window) => !window.minimized)
          .map(({ friendId, contact }) => (
            <ChatWindow
              key={friendId}
              contact={contact}
              messages={state.conversations[friendId]}
              onSend={(body) => service.send(friendId, body)}
              onMinimize={() => service.toggleMinimized(friendId)}
              onClose={() => service.close(friendId)}
            />
          ))}
      </div>
      <ul className="chat-dock__bar">
        {windows.map(({ friendId, minimized, contact }) => (
          <li key={friendId}>
            <button
              type="button"
              className={`chat-dock__tab${minimized ? '' : ' chat-dock__tab--open'}`}
              aria-expanded={!minimized}
              onClick={() => service.toggleMinimized(friendId)}
            >
              <Avatar src={contact.avatar_url} name={contact.first_name} size={24} decorative />
              <span className={`presence${contact.online ? ' presence--online' : ''}`} aria-hidden="true" />
              <span>{contact.first_name}</span>
              {minimized && contact.unread > 0 && (
                <span className="chat-list__unread">
                  {contact.unread} <span className="visually-hidden">sin leer</span>
                </span>
              )}
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}
