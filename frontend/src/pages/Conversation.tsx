import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router'
import { listMessages, sendMessage, type ChatMessage } from '../api/chat'
import { errorMessage } from '../api/client'
import { useAuth } from '../auth/context'
import { Alert } from '../components/Alert'
import { UserCard } from '../components/UserCard'
import { Avatar } from '../components/ui/Avatar'
import { Columns } from '../components/ui/Columns'
import { Module } from '../components/ui/Module'
import { useChat } from '../lib/chat/context'
import { formatLong } from '../lib/dates'

const PAGE = 50
/** Con la conversación a la vista, se buscan mensajes nuevos cada 5 s. */
const POLL = 5_000

/** Una conversación completa en Mensajes, con su historial y caja para escribir. */
export function Conversation() {
  const { id } = useParams()
  const friendId = Number(id)
  const { user } = useAuth()
  const chat = useChat()
  const [messages, setMessages] = useState<ChatMessage[] | null>(null)
  const [hasOlder, setHasOlder] = useState(false)
  const [body, setBody] = useState('')
  const [error, setError] = useState('')
  const [sending, setSending] = useState(false)
  const list = useRef<HTMLOListElement>(null)
  const service = chat?.service
  const friend = chat?.state.contacts.find((contact) => contact.id === friendId)

  const append = useCallback((items: ChatMessage[]) => {
    setMessages((current) => {
      const known = new Set((current ?? []).map((message) => message.id))
      return [...(current ?? []), ...items.filter((message) => !known.has(message.id))]
    })
  }, [])

  useEffect(() => {
    if (!Number.isInteger(friendId) || friendId < 1) return
    listMessages(friendId)
      .then(({ messages: result }) => {
        setMessages(result)
        setHasOlder(result.length === PAGE)
        service?.markRead(friendId)
      })
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [friendId, service])

  // Mensajes nuevos mientras la conversación está abierta y la pestaña a la vista
  const lastId = messages?.at(-1)?.id
  useEffect(() => {
    if (messages === null) return
    const timer = setInterval(() => {
      if (document.visibilityState !== 'visible') return
      listMessages(friendId, lastId)
        .then(({ messages: newer }) => {
          if (newer.length === 0) return
          append(newer)
          service?.markRead(friendId)
        })
        .catch(() => undefined)
    }, POLL)
    return () => clearInterval(timer)
  }, [friendId, lastId, messages, append, service])

  useEffect(() => {
    const element = list.current
    if (element) element.scrollTop = element.scrollHeight
  }, [lastId])

  async function loadOlder() {
    const first = messages?.[0]
    if (!first) return
    try {
      const { messages: older } = await listMessages(friendId, undefined, first.id)
      setMessages((current) => [...older, ...(current ?? [])])
      setHasOlder(older.length === PAGE)
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const text = body.trim()
    if (text === '') return
    setSending(true)
    setError('')
    try {
      append([(await sendMessage(friendId, text)).message])
      setBody('')
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setSending(false)
    }
  }

  const name = friend ? `${friend.first_name} ${friend.last_name}` : 'tu amigo'

  return (
    <Columns left={<UserCard />}>
      <Module title={`Mensajes con ${name}`} as="h1" action={<Link to="/mensajes">Volver a Mensajes</Link>}>
        {error && <Alert>{error}</Alert>}
        {messages === null ? (
          !error && <p className="status">Cargando conversación…</p>
        ) : (
          <>
            {hasOlder && (
              <p className="load-more">
                <button type="button" className="link-button" onClick={loadOlder}>
                  Ver mensajes anteriores
                </button>
              </p>
            )}
            <ol className="conversation" ref={list} aria-live="polite">
              {messages.length === 0 && <li className="status">Todavía no os habéis escrito. ¡Empieza tú!</li>}
              {messages.map((message) => (
                <li
                  key={message.id}
                  className={message.mine ? 'conversation__message conversation__message--mine' : 'conversation__message'}
                >
                  <strong>{message.mine ? user?.first_name : (friend?.first_name ?? '')}:</strong> {message.body}{' '}
                  <time className="muted" dateTime={message.created_at}>
                    {formatLong(message.created_at)}
                  </time>
                </li>
              ))}
            </ol>
            <form className="composer" onSubmit={handleSubmit}>
              <Avatar src={user?.avatar_url} name={user?.first_name ?? ''} size={32} decorative />
              <label className="visually-hidden" htmlFor="conversation-body">
                Mensaje para {name}
              </label>
              <textarea
                id="conversation-body"
                rows={2}
                maxLength={1000}
                placeholder="Escribe aquí…"
                value={body}
                onChange={(event) => setBody(event.target.value)}
              />
              <button type="submit" className="button" disabled={sending || body.trim() === ''}>
                Enviar
              </button>
            </form>
          </>
        )}
      </Module>
    </Columns>
  )
}
