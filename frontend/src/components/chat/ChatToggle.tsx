import { useChat } from '../../lib/chat/context'
import { Dropdown } from '../ui/Dropdown'
import { ChatList } from './ChatList'

/** "Chat (n)" en la topbar: abre la lista de amigos para chatear (guía §3 y §8). */
export function ChatToggle({ onOpen }: { onOpen?: () => void }) {
  const chat = useChat()
  if (!chat) return null

  const { service, state } = chat

  return (
    <div className="topbar__chat">
      <Dropdown
        label={
          <>
            Chat <span className="topbar__counter">({state.online})</span>{' '}
            <span className="visually-hidden">{state.online === 1 ? 'amigo conectado' : 'amigos conectados'}</span>
            {state.unread > 0 && (
              <>
                {' '}
                <span className="topbar__badge">
                  {state.unread}{' '}
                  <span className="visually-hidden">{state.unread === 1 ? 'mensaje sin leer' : 'mensajes sin leer'}</span>
                </span>
              </>
            )}
          </>
        }
      >
        <div className="chat-panel">
          <ChatList
            contacts={state.contacts}
            onOpen={(friendId) => {
              service.open(friendId)
              onOpen?.()
            }}
          />
        </div>
      </Dropdown>
    </div>
  )
}
