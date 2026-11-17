import type { ChatContact } from '../../api/chat'

type ChatListProps = {
  contacts: ChatContact[]
  onOpen: (friendId: number) => void
}

/** Amigos con los que chatear, los conectados primero (guía §8, ChatList). */
export function ChatList({ contacts, onOpen }: ChatListProps) {
  if (contacts.length === 0) return <p className="status">Cuando tengas amigos, podrás chatear con ellos aquí.</p>

  return (
    <ul className="chat-list">
      {contacts.map((contact) => (
        <li key={contact.id}>
          <button type="button" className="chat-list__contact" onClick={() => onOpen(contact.id)}>
            <span className={`presence${contact.online ? ' presence--online' : ''}`} aria-hidden="true" />
            <span className="chat-list__name">
              {contact.first_name} {contact.last_name}
            </span>{' '}
            <span className="visually-hidden">{contact.online ? '(conectado)' : '(desconectado)'}</span>
            {contact.unread > 0 && (
              <span className="chat-list__unread">
                {contact.unread}{' '}
                <span className="visually-hidden">{contact.unread === 1 ? 'mensaje nuevo' : 'mensajes nuevos'}</span>
              </span>
            )}
          </button>
        </li>
      ))}
    </ul>
  )
}
