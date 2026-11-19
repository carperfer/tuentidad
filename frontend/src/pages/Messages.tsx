import { useEffect, useId, useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { listConversations, type Conversation } from '../api/chat'
import { errorMessage } from '../api/client'
import { Alert } from '../components/Alert'
import { UserCard } from '../components/UserCard'
import { Avatar } from '../components/ui/Avatar'
import { Columns } from '../components/ui/Columns'
import { MediaObject } from '../components/ui/MediaObject'
import { Counter, Module } from '../components/ui/Module'
import { useChat } from '../lib/chat/context'
import { timeAgo } from '../lib/dates'

/** Bandeja de Mensajes: las conversaciones del chat con su último mensaje. */
export function Messages() {
  const chat = useChat()
  const navigate = useNavigate()
  const selectId = useId()
  const [conversations, setConversations] = useState<Conversation[] | null>(null)
  const [error, setError] = useState('')
  const unread = chat?.state.unread ?? 0

  // Se recarga cuando cambian los no leídos (el chat los consulta cada poco)
  useEffect(() => {
    listConversations()
      .then(({ conversations: result }) => setConversations(result))
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [unread])

  const contacts = chat?.state.contacts ?? []

  return (
    <Columns left={<UserCard />}>
      <Module
        title={
          <>
            Mensajes {unread > 0 && <Counter value={unread} />}
            {unread > 0 && (
              <>
                {' '}
                <span className="visually-hidden">sin leer</span>
              </>
            )}
          </>
        }
        as="h1"
      >
        {contacts.length > 0 && (
          <div className="inline-form messages__new">
            <label htmlFor={selectId}>Escribir a</label>{' '}
            <select
              id={selectId}
              value=""
              onChange={(event) => event.target.value && navigate(`/mensajes/${event.target.value}`)}
            >
              <option value="">Elige a un amigo</option>
              {contacts.map((contact) => (
                <option key={contact.id} value={contact.id}>
                  {contact.first_name} {contact.last_name}
                </option>
              ))}
            </select>
          </div>
        )}
        {error && <Alert>{error}</Alert>}
        {conversations === null ? (
          !error && <p className="status">Cargando mensajes…</p>
        ) : conversations.length === 0 ? (
          <p className="status">Todavía no tienes mensajes. Escribe a un amigo o ábrele el chat desde la barra de abajo.</p>
        ) : (
          <ul className="media-list conversation-list">
            {conversations.map(({ person, last_message: last, unread: count }) => (
              <li key={person.id} className={count > 0 ? 'notification-list__unread' : undefined}>
                <MediaObject
                  media={<Avatar src={person.avatar_url} name={person.first_name} size={40} decorative />}
                  aside={count > 0 && <span className="chat-list__unread">{count}</span>}
                >
                  <p className="feed__line">
                    <Link to={`/mensajes/${person.id}`} className="person-name">
                      {person.first_name} {person.last_name}
                    </Link>
                    {count > 0 && (
                      <>
                        {' '}
                        <span className="visually-hidden">({count} sin leer)</span>
                      </>
                    )}
                  </p>
                  <p className="conversation-list__preview">
                    {last.mine && <span className="muted">Tú: </span>}
                    {last.body}
                  </p>
                  <time className="muted" dateTime={last.created_at}>
                    {timeAgo(last.created_at)}
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
