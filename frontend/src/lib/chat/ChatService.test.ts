import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ChatContact, ChatMessage } from '../../api/chat'
import { ChatService, INTERVALS, type ChatTransport } from './ChatService'

const contact = (id: number, unread = 0): ChatContact => ({
  id,
  first_name: `Amigo ${id}`,
  last_name: 'López',
  avatar_url: null,
  online: true,
  unread,
})

const message = (id: number, mine = false): ChatMessage => ({
  id,
  sender_id: mine ? 1 : 2,
  mine,
  body: `Mensaje ${id}`,
  created_at: '2026-11-17 10:00:00',
})

function fakeTransport() {
  return {
    status: vi.fn(async () => ({ contacts: [contact(2, 3), contact(3)], online: 2, unread: 3 })),
    messages: vi.fn(async (_friendId: number, after?: number) => ({ messages: after ? [] : [message(10), message(11)] })),
    send: vi.fn(async () => ({ message: message(12, true) })),
  } satisfies ChatTransport
}

function setVisibility(state: 'visible' | 'hidden') {
  Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => state })
}

describe('ChatService', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    sessionStorage.clear()
    setVisibility('visible')
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('polls the status every 30 seconds while visible and every 60 when hidden', async () => {
    const transport = fakeTransport()
    const service = new ChatService(transport)
    service.start()
    await vi.advanceTimersByTimeAsync(0)
    expect(transport.status).toHaveBeenCalledTimes(1)
    expect(service.getState().unread).toBe(3)

    await vi.advanceTimersByTimeAsync(INTERVALS.status)
    expect(transport.status).toHaveBeenCalledTimes(2)

    setVisibility('hidden')
    await vi.advanceTimersByTimeAsync(INTERVALS.status)
    expect(transport.status).toHaveBeenCalledTimes(3)
    await vi.advanceTimersByTimeAsync(INTERVALS.status)
    expect(transport.status).toHaveBeenCalledTimes(3)
    await vi.advanceTimersByTimeAsync(INTERVALS.hidden - INTERVALS.status)
    expect(transport.status).toHaveBeenCalledTimes(4)
    service.stop()
  })

  it('loads a conversation when opened, marks it as read and polls only for newer messages', async () => {
    const transport = fakeTransport()
    const service = new ChatService(transport)
    service.start()
    await vi.advanceTimersByTimeAsync(0)

    service.open(2)
    await vi.advanceTimersByTimeAsync(0)
    expect(service.getState().conversations[2].map((item) => item.id)).toEqual([10, 11])
    expect(service.getState().unread).toBe(0)

    await vi.advanceTimersByTimeAsync(INTERVALS.conversation)
    expect(transport.messages).toHaveBeenLastCalledWith(2, 11)

    await service.send(2, 'Hola')
    expect(service.getState().conversations[2].at(-1)?.mine).toBe(true)
    service.stop()
  })

  it('does not poll minimized conversations and keeps at most three windows', async () => {
    const transport = fakeTransport()
    const service = new ChatService(transport)
    service.start()
    for (const id of [2, 3, 4, 5]) service.open(id)
    await vi.advanceTimersByTimeAsync(0)
    expect(service.getState().windows.map((window) => window.friendId)).toEqual([3, 4, 5])

    service.toggleMinimized(3)
    service.toggleMinimized(4)
    service.toggleMinimized(5)
    transport.messages.mockClear()
    await vi.advanceTimersByTimeAsync(INTERVALS.conversation)
    expect(transport.messages).not.toHaveBeenCalled()

    service.close(5)
    expect(service.getState().windows.map((window) => window.friendId)).toEqual([3, 4])
    expect(JSON.parse(sessionStorage.getItem('tuentidad:chat-windows') ?? '[]')).toHaveLength(2)
    service.stop()
  })

  it('restores the open windows of the session and stops polling when stopped', async () => {
    sessionStorage.setItem('tuentidad:chat-windows', JSON.stringify([{ friendId: 2, minimized: false }]))
    const transport = fakeTransport()
    const service = new ChatService(transport)
    service.start()
    await vi.advanceTimersByTimeAsync(0)
    expect(transport.messages).toHaveBeenCalledWith(2)

    service.stop()
    transport.status.mockClear()
    await vi.advanceTimersByTimeAsync(INTERVALS.hidden * 2)
    expect(transport.status).not.toHaveBeenCalled()
  })
})
