import type { Person } from './friends'

/** Comentario de una foto o de un evento. */
export type Comment = {
  id: number
  author: Person
  body: string
  created_at: string
  can_delete: boolean
}
