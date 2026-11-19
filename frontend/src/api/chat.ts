import { api, post } from './client'
import type { Person } from './friends'

export type ChatContact = Person & { online: boolean; unread: number }

export type ChatStatus = { contacts: ChatContact[]; online: number; unread: number }

export type ChatMessage = {
  id: number
  sender_id: number
  mine: boolean
  body: string
  created_at: string
}

export function getChatStatus(): Promise<ChatStatus> {
  return api('/api/chat')
}

/**
 * Sin parámetros, los últimos 50 mensajes; con after, los posteriores a ese id; con
 * before, los 50 anteriores. Marca como leídos los recibidos.
 */
export function listMessages(friendId: number, after?: number, before?: number): Promise<{ messages: ChatMessage[] }> {
  const query = after ? `?after=${after}` : before ? `?before=${before}` : ''
  return api(`/api/chat/${friendId}/messages${query}`)
}

export type Conversation = { person: Person; last_message: ChatMessage; unread: number }

/** Bandeja de Mensajes: conversaciones con amigos, de la más reciente a la más antigua. */
export function listConversations(): Promise<{ conversations: Conversation[] }> {
  return api('/api/chat/conversations')
}

export function sendMessage(friendId: number, body: string): Promise<{ message: ChatMessage }> {
  return post(`/api/chat/${friendId}/messages`, { body })
}
