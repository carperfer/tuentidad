import { useChat } from '../../lib/chat/context'
import { Avatar } from '../ui/Avatar'
import { Dropdown } from '../ui/Dropdown'
import { ChatList } from './ChatList'
import { ChatWindow } from './ChatWindow'

/**
 * Barra fija al pie, siempre visible con la sesión iniciada (guía §8): «Chat (n)»
 * abre la lista de amigos y cada conversación abierta queda como el icono de la
 * persona; encima, sus ventanas flotantes. En móvil, la ventana ocupa la pantalla.
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

  return (
    <div className="chat-dock" role="region" aria-label="Chat">
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
      <div className="chat-dock__bar">
        <div className="chat-dock__toggle">
          <Dropdown
            align="left"
            up
            label={
              <>
                Chat <span className="topbar__counter">({state.online})</span>{' '}
                <span className="visually-hidden">{state.online === 1 ? 'amigo conectado' : 'amigos conectados'}</span>
              </>
            }
          >
            <div className="chat-panel">
              <ChatList contacts={state.contacts} onOpen={(friendId) => service.open(friendId)} />
            </div>
          </Dropdown>
        </div>
        <ul className="chat-dock__tabs" aria-label="Conversaciones abiertas">
          {windows.map(({ friendId, minimized, contact }) => (
            <li key={friendId}>
              <button
                type="button"
                className={`chat-dock__tab${minimized ? '' : ' chat-dock__tab--open'}`}
                aria-expanded={!minimized}
                title={`${contact.first_name} ${contact.last_name}`}
                onClick={() => service.toggleMinimized(friendId)}
              >
                <Avatar src={contact.avatar_url} name={contact.first_name} size={24} decorative />
                <span className={`presence chat-dock__presence${contact.online ? ' presence--online' : ''}`} aria-hidden="true" />
                <span className="visually-hidden">
                  Chat con {contact.first_name} {contact.last_name}
                  {contact.online ? ' (conectado)' : ''}
                </span>
                {minimized && contact.unread > 0 && (
                  <span className="chat-list__unread chat-dock__unread">
                    {contact.unread} <span className="visually-hidden">sin leer</span>
                  </span>
                )}
              </button>
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}
