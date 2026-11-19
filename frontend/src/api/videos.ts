import { api, post } from './client'
import type { Person } from './friends'

export type Video = {
  id: number
  youtube_id: string
  title: string
  created_at: string
  owner: Person
  is_owner: boolean
}

export type VideoPage = { videos: Video[]; next_before: number | null; total: number | null }

/** Vídeos tuyos y de tus amigos, de 10 en 10. */
export function listVideoFeed(before?: number): Promise<VideoPage> {
  return api(`/api/videos${before ? `?before=${before}` : ''}`)
}

export function listUserVideos(userId: number, before?: number): Promise<VideoPage> {
  return api(`/api/users/${userId}/videos${before ? `?before=${before}` : ''}`)
}

export function shareVideo(url: string, title: string): Promise<{ video: Video }> {
  return post('/api/videos', { url, title })
}

export function deleteVideo(id: number): Promise<{ message: string }> {
  return api(`/api/videos/${id}`, { method: 'DELETE' })
}
