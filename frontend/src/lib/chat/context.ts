import { createContext, useContext } from 'react'
import type { ChatService, ChatState } from './ChatService'

export type ChatContextValue = { service: ChatService; state: ChatState }

export const ChatContext = createContext<ChatContextValue | null>(null)

/** Estado y acciones del chat; null fuera de una sesión iniciada. */
export function useChat(): ChatContextValue | null {
  return useContext(ChatContext)
}
