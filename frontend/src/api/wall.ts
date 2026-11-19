import { api, post } from './client'
import type { Person } from './friends'

export type WallPost = { id: number; author: Person; body: string; created_at: string; can_delete: boolean }

/** Mensajes del tablón, de 10 en 10 (before para los anteriores). */
export function listWall(userId: number, before?: number): Promise<{ posts: WallPost[]; next_before: number | null }> {
  return api(`/api/users/${userId}/wall${before ? `?before=${before}` : ''}`)
}

export function writeOnWall(userId: number, body: string): Promise<{ post: WallPost }> {
  return post(`/api/users/${userId}/wall`, { body })
}

export function deleteWallPost(id: number): Promise<{ message: string }> {
  return api(`/api/wall-posts/${id}`, { method: 'DELETE' })
}
