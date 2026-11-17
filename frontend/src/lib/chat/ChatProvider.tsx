import { useEffect, useMemo, useState, useSyncExternalStore, type ReactNode } from 'react'
import { getChatStatus, listMessages, sendMessage } from '../../api/chat'
import { ChatService, type ChatTransport } from './ChatService'
import { ChatContext } from './context'

const httpTransport: ChatTransport = { status: getChatStatus, messages: listMessages, send: sendMessage }

/** Arranca el chat mientras hay sesión iniciada y lo para al salir. */
export function ChatProvider({ children, transport = httpTransport }: { children: ReactNode; transport?: ChatTransport }) {
  const [service] = useState(() => new ChatService(transport))
  const state = useSyncExternalStore(service.subscribe, service.getState)

  useEffect(() => {
    service.start()
    return () => service.stop()
  }, [service])

  const value = useMemo(() => ({ service, state }), [service, state])

  return <ChatContext.Provider value={value}>{children}</ChatContext.Provider>
}
