import { api, post } from './client'
import type { Comment } from './comments'
import type { Person } from './friends'

export type RsvpStatus = 'yes' | 'no' | 'maybe'

export type EventSummary = {
  id: number
  title: string
  place: string
  description: string
  /** UTC, "AAAA-MM-DD HH:MM:SS". */
  starts_at: string
  organizer: Person
  is_organizer: boolean
  my_status: 'organizer' | 'pending' | RsvpStatus
  /** "yes" incluye a quien organiza. */
  counts: Record<'pending' | RsvpStatus, number>
}

export type EventDetail = EventSummary & {
  invitees: { person: Person; status: 'pending' | RsvpStatus }[]
  comments: Comment[]
}

export type EventData = {
  title: string
  place: string
  description: string
  /** ISO en UTC (con Z). */
  starts_at: string
}

export type Birthday = { person: Person; date: string }

export function listEvents(): Promise<{ upcoming: EventSummary[]; past: EventSummary[] }> {
  return api('/api/events')
}

export function getCalendar(): Promise<{ events: EventSummary[]; birthdays: Birthday[] }> {
  return api('/api/calendar')
}

export function getEvent(id: number): Promise<{ event: EventDetail }> {
  return api(`/api/events/${id}`)
}

export function createEvent(data: EventData & { invite: number[] }): Promise<{ event: EventDetail }> {
  return post('/api/events', data)
}

export function updateEvent(id: number, data: EventData): Promise<{ event: EventDetail }> {
  return api(`/api/events/${id}`, { method: 'PUT', body: JSON.stringify(data) })
}

export function deleteEvent(id: number): Promise<{ message: string }> {
  return api(`/api/events/${id}`, { method: 'DELETE' })
}

/** Amigos que se pueden invitar (a un evento ya creado, los que aún no lo están). */
export function listInvitable(eventId?: number): Promise<{ people: Person[] }> {
  return api(`/api/events/invitable${eventId ? `?event=${eventId}` : ''}`)
}

export function inviteToEvent(id: number, userIds: number[]): Promise<{ event: EventDetail }> {
  return post(`/api/events/${id}/invitations`, { user_ids: userIds })
}

export function respondToEvent(id: number, status: RsvpStatus): Promise<{ event: EventDetail }> {
  return api(`/api/events/${id}/rsvp`, { method: 'PUT', body: JSON.stringify({ status }) })
}

export function commentEvent(id: number, body: string): Promise<{ comments: Comment[] }> {
  return post(`/api/events/${id}/comments`, { body })
}

export function deleteEventComment(commentId: number): Promise<{ message: string; comments: Comment[] }> {
  return api(`/api/event-comments/${commentId}`, { method: 'DELETE' })
}
