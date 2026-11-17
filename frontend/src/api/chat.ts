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

/** Sin after, los últimos mensajes; con after, los posteriores a ese id. Marca como leídos los recibidos. */
export function listMessages(friendId: number, after?: number): Promise<{ messages: ChatMessage[] }> {
  return api(`/api/chat/${friendId}/messages${after ? `?after=${after}` : ''}`)
}

export function sendMessage(friendId: number, body: string): Promise<{ message: ChatMessage }> {
  return post(`/api/chat/${friendId}/messages`, { body })
}
