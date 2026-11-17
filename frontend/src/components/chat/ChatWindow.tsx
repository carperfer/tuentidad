import { useEffect, useRef, useState, type FormEvent } from 'react'
import type { ChatContact, ChatMessage } from '../../api/chat'
import { errorMessage } from '../../api/client'
import { useAuth } from '../../auth/context'
import { formatLong } from '../../lib/dates'

type ChatWindowProps = {
  contact: ChatContact
  messages: ChatMessage[] | undefined
  onSend: (body: string) => Promise<void>
  onMinimize: () => void
  onClose: () => void
}

/** Ventana flotante de una conversación: mensajes "Nombre: texto" y caja para escribir (guía §8). */
export function ChatWindow({ contact, messages, onSend, onMinimize, onClose }: ChatWindowProps) {
  const { user } = useAuth()
  const [body, setBody] = useState('')
  const [error, setError] = useState('')
  const [sending, setSending] = useState(false)
  const list = useRef<HTMLOListElement>(null)
  const input = useRef<HTMLInputElement>(null)
  const name = `${contact.first_name} ${contact.last_name}`

  useEffect(() => {
    input.current?.focus()
  }, [])

  // Al llegar mensajes, se baja hasta el último
  useEffect(() => {
    const element = list.current
    if (element) element.scrollTop = element.scrollHeight
  }, [messages?.length])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const text = body.trim()
    if (text === '') return
    setSending(true)
    setError('')
    try {
      await onSend(text)
      setBody('')
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setSending(false)
      input.current?.focus()
    }
  }

  return (
    <section className="chat-window" aria-label={`Chat con ${name}`}>
      <header className="chat-window__header">
        <span className={`presence${contact.online ? ' presence--online' : ''}`} aria-hidden="true" />
        <h2 className="chat-window__title">{name}</h2>
        <span className="visually-hidden">{contact.online ? 'conectado' : 'desconectado'}</span>
        <button type="button" className="chat-window__button" aria-label={`Minimizar el chat con ${name}`} onClick={onMinimize}>
          <span aria-hidden="true">_</span>
        </button>
        <button type="button" className="chat-window__button" aria-label={`Cerrar el chat con ${name}`} onClick={onClose}>
          <span aria-hidden="true">×</span>
        </button>
      </header>
      <ol className="chat-window__messages" ref={list} aria-live="polite">
        {messages === undefined ? (
          <li className="status">Cargando…</li>
        ) : messages.length === 0 ? (
          <li className="status">Escribe el primer mensaje a {contact.first_name}.</li>
        ) : (
          messages.map((message) => (
            <li key={message.id} title={formatLong(message.created_at)}>
              <strong>{message.mine ? user?.first_name : contact.first_name}:</strong> {message.body}
            </li>
          ))
        )}
      </ol>
      {error && <p className="chat-window__error">{error}</p>}
      <form className="chat-window__form" onSubmit={handleSubmit}>
        <input
          ref={input}
          type="text"
          maxLength={1000}
          aria-label={`Mensaje para ${contact.first_name}`}
          placeholder="Escribe aquí…"
          value={body}
          onChange={(event) => setBody(event.target.value)}
          disabled={sending}
        />
      </form>
    </section>
  )
}
