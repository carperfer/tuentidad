import { api, post } from './client'
import type { Person } from './friends'

/** Tarjeta de un enlace: título, resumen y foto leídos de la propia página. */
export type LinkPreview = {
  id: number
  url: string
  title: string
  description: string
  site_name: string
  image_url: string | null
}

export type WallReply = {
  id: number
  parent_id: number | null
  author: Person
  body: string
  link_preview: LinkPreview | null
  created_at: string
  can_delete: boolean
}

export type WallPost = WallReply & { replies: WallReply[] }

/** Mensajes del tablón con sus respuestas, de 10 en 10 (before para los anteriores). */
export function listWall(userId: number, before?: number): Promise<{ posts: WallPost[]; next_before: number | null }> {
  return api(`/api/users/${userId}/wall${before ? `?before=${before}` : ''}`)
}

export type WallMessage = { body: string; parentId?: number; linkPreview?: boolean }

export function writeOnWall<T extends WallReply = WallPost>(userId: number, message: WallMessage): Promise<{ post: T }> {
  return post(`/api/users/${userId}/wall`, {
    body: message.body,
    parent_id: message.parentId ?? null,
    link_preview: message.linkPreview ?? true,
  })
}

export function deleteWallPost(id: number): Promise<{ message: string }> {
  return api(`/api/wall-posts/${id}`, { method: 'DELETE' })
}

/** Tarjeta del primer enlace del texto, o null si no hay o la página no se puede leer. */
export function previewLink(text: string): Promise<{ preview: LinkPreview | null }> {
  return post('/api/link-previews', { url: text })
}
