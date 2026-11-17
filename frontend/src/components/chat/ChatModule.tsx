import { useChat } from '../../lib/chat/context'
import { Counter, Module } from '../ui/Module'
import { ChatList } from './ChatList'

/** "Chat (n)": amigos para chatear en la columna derecha de Inicio (guía §5.1). */
export function ChatModule() {
  const chat = useChat()
  if (!chat) return null

  return (
    <Module
      title={
        <>
          Chat <Counter value={chat.state.online} />
        </>
      }
    >
      <ChatList contacts={chat.state.contacts} onOpen={(friendId) => chat.service.open(friendId)} />
    </Module>
  )
}
