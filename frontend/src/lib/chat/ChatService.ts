import type { ChatContact, ChatMessage, ChatStatus } from '../../api/chat'

/**
 * Cómo llegan y salen los mensajes. Ahora es HTTP con sondeo; se puede
 * sustituir (p. ej. por WebSockets) sin tocar el resto del chat.
 */
export type ChatTransport = {
  status: () => Promise<ChatStatus>
  messages: (friendId: number, after?: number) => Promise<{ messages: ChatMessage[] }>
  send: (friendId: number, body: string) => Promise<{ message: ChatMessage }>
}

export type ChatWindowState = { friendId: number; minimized: boolean }

export type ChatState = {
  contacts: ChatContact[]
  online: number
  unread: number
  windows: ChatWindowState[]
  /** Mensajes de cada conversación abierta, por id del amigo. */
  conversations: Record<number, ChatMessage[]>
}

/** Intervalos de sondeo en milisegundos: más frecuentes con una conversación abierta y a la vista. */
export const INTERVALS = {
  status: 30_000,
  conversation: 4_000,
  hidden: 60_000,
}

const MAX_WINDOWS = 3
const STORAGE_KEY = 'tuentidad:chat-windows'

const EMPTY: ChatState = { contacts: [], online: 0, unread: 0, windows: [], conversations: {} }

function loadWindows(): ChatWindowState[] {
  try {
    const saved: unknown = JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? '[]')
    return Array.isArray(saved)
      ? saved.filter((item): item is ChatWindowState => typeof item?.friendId === 'number').slice(-MAX_WINDOWS)
      : []
  } catch {
    return []
  }
}

function saveWindows(windows: ChatWindowState[]): void {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(windows))
  } catch {
    // Sin almacenamiento, las ventanas no se recuerdan al recargar
  }
}

/**
 * Estado del chat y sondeo adaptativo: el estado (amigos conectados y sin leer)
 * cada 30 s y las conversaciones abiertas cada 4 s; con la pestaña en segundo
 * plano, todo cada 60 s.
 */
export class ChatService {
  private state: ChatState = { ...EMPTY, windows: loadWindows() }
  private listeners = new Set<() => void>()
  private timers: ReturnType<typeof setTimeout>[] = []
  private running = false
  private readonly transport: ChatTransport

  constructor(transport: ChatTransport) {
    this.transport = transport
  }

  getState = (): ChatState => this.state

  subscribe = (listener: () => void): (() => void) => {
    this.listeners.add(listener)
    return () => this.listeners.delete(listener)
  }

  start(): void {
    if (this.running) return
    this.running = true
    document.addEventListener('visibilitychange', this.handleVisibility)
    void this.pollStatus()
    for (const window of this.state.windows) void this.loadConversation(window.friendId)
    this.scheduleConversations()
  }

  stop(): void {
    this.running = false
    document.removeEventListener('visibilitychange', this.handleVisibility)
    this.clearTimers()
  }

  /** Abre (o despliega) la conversación con un amigo. */
  open(friendId: number): void {
    const others = this.state.windows.filter((window) => window.friendId !== friendId)
    this.setWindows([...others, { friendId, minimized: false }].slice(-MAX_WINDOWS))
    void this.loadConversation(friendId)
  }

  close(friendId: number): void {
    const conversations = { ...this.state.conversations }
    delete conversations[friendId]
    this.update({ conversations })
    this.setWindows(this.state.windows.filter((window) => window.friendId !== friendId))
  }

  toggleMinimized(friendId: number): void {
    this.setWindows(
      this.state.windows.map((window) => (window.friendId === friendId ? { ...window, minimized: !window.minimized } : window)),
    )
    if (this.state.windows.some((window) => window.friendId === friendId && !window.minimized)) {
      void this.fetchNew(friendId)
    }
  }

  async send(friendId: number, body: string): Promise<void> {
    const { message } = await this.transport.send(friendId, body)
    this.append(friendId, [message])
  }

  private handleVisibility = (): void => {
    // Al volver a la pestaña, se actualiza todo enseguida
    if (document.visibilityState === 'visible') {
      this.clearTimers()
      void this.pollStatus()
      void this.pollConversations()
    }
  }

  private hidden(): boolean {
    return document.visibilityState === 'hidden'
  }

  private async pollStatus(): Promise<void> {
    try {
      const status = await this.transport.status()
      this.update({ contacts: status.contacts, online: status.online, unread: status.unread })
    } catch {
      // Sin conexión o con la sesión caducada: se reintenta en el siguiente ciclo
    }
    this.schedule(() => this.pollStatus(), this.hidden() ? INTERVALS.hidden : INTERVALS.status)
  }

  private async pollConversations(): Promise<void> {
    const open = this.state.windows.filter((window) => !window.minimized)
    await Promise.all(open.map((window) => this.fetchNew(window.friendId)))
    this.scheduleConversations()
  }

  private scheduleConversations(): void {
    this.schedule(() => this.pollConversations(), this.hidden() ? INTERVALS.hidden : INTERVALS.conversation)
  }

  private async loadConversation(friendId: number): Promise<void> {
    try {
      const { messages } = await this.transport.messages(friendId)
      this.update({ conversations: { ...this.state.conversations, [friendId]: messages } })
      this.markRead(friendId)
    } catch {
      // Se vuelve a intentar al sondear las conversaciones abiertas
    }
  }

  private async fetchNew(friendId: number): Promise<void> {
    const current = this.state.conversations[friendId]
    if (current === undefined) return this.loadConversation(friendId)
    try {
      const { messages } = await this.transport.messages(friendId, current.at(-1)?.id)
      if (messages.length > 0) {
        this.append(friendId, messages)
        this.markRead(friendId)
      }
    } catch {
      // Se reintenta en el siguiente ciclo
    }
  }

  private append(friendId: number, messages: ChatMessage[]): void {
    const current = this.state.conversations[friendId] ?? []
    const known = new Set(current.map((message) => message.id))
    const added = messages.filter((message) => !known.has(message.id))
    if (added.length === 0) return
    this.update({ conversations: { ...this.state.conversations, [friendId]: [...current, ...added] } })
  }

  /** Al leer una conversación (aquí o en Mensajes), sus mensajes dejan de contar como no leídos. */
  markRead(friendId: number): void {
    const contact = this.state.contacts.find((item) => item.id === friendId)
    if (!contact || contact.unread === 0) return
    this.update({
      contacts: this.state.contacts.map((item) => (item.id === friendId ? { ...item, unread: 0 } : item)),
      unread: Math.max(0, this.state.unread - contact.unread),
    })
  }

  private setWindows(windows: ChatWindowState[]): void {
    saveWindows(windows)
    this.update({ windows })
  }

  private update(changes: Partial<ChatState>): void {
    this.state = { ...this.state, ...changes }
    for (const listener of this.listeners) listener()
  }

  private schedule(task: () => Promise<void>, delay: number): void {
    if (!this.running) return
    const timer = setTimeout(() => {
      this.timers = this.timers.filter((item) => item !== timer)
      void task()
    }, delay)
    this.timers.push(timer)
  }

  private clearTimers(): void {
    for (const timer of this.timers) clearTimeout(timer)
    this.timers = []
  }
}
