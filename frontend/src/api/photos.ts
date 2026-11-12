import { api, post } from './client'
import type { Person } from './friends'

export type AlbumSummary = {
  id: number
  title: string
  description: string
  updated_at: string | null
  photo_count: number
  cover_photo_id: number | null
}

export type Photo = {
  id: number
  album_id: number
  width: number
  height: number
  url: string
  thumb_url: string
  created_at: string | null
}

export type Album = {
  id: number
  title: string
  description: string
  owner: { id: number; first_name: string; last_name: string }
  is_owner: boolean
  created_at: string | null
  updated_at: string | null
  photos: Photo[]
}

export type AlbumData = { title: string; description: string }

/** Tipos y tamaño que acepta el backend; se comprueban antes de subir. */
export const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp']
export const MAX_PHOTO_SIZE = 10 * 1024 * 1024

export function thumbUrl(photoId: number): string {
  return `/api/photos/${photoId}/thumb`
}

export function listAlbums(userId: number): Promise<{ albums: AlbumSummary[] }> {
  return api(`/api/users/${userId}/albums`)
}

export function listRecentPhotos(userId: number, limit: number): Promise<{ photos: Photo[]; total: number }> {
  return api(`/api/users/${userId}/photos?limit=${limit}`)
}

export function getAlbum(id: number): Promise<{ album: Album }> {
  return api(`/api/albums/${id}`)
}

export function createAlbum(data: AlbumData): Promise<{ album: Album }> {
  return post('/api/albums', data)
}

export function updateAlbum(id: number, data: AlbumData): Promise<{ album: Album }> {
  return api(`/api/albums/${id}`, { method: 'PUT', body: JSON.stringify(data) })
}

export function deleteAlbum(id: number): Promise<{ message: string }> {
  return api(`/api/albums/${id}`, { method: 'DELETE' })
}

export function uploadPhoto(albumId: number, file: File): Promise<{ photo: Photo }> {
  const data = new FormData()
  data.append('photo', file)
  return api(`/api/albums/${albumId}/photos`, { method: 'POST', body: data })
}

export function deletePhoto(id: number): Promise<{ message: string }> {
  return api(`/api/photos/${id}`, { method: 'DELETE' })
}

export type PhotoTag = {
  id: number
  user: Person
  /** Zona en porcentajes de la imagen. */
  x: number
  y: number
  width: number
  height: number
  can_remove: boolean
}

export type TagArea = Pick<PhotoTag, 'x' | 'y' | 'width' | 'height'>

export type PhotoComment = {
  id: number
  author: Person
  body: string
  created_at: string
  can_delete: boolean
}

export type PhotoDetail = {
  photo: Photo & { album_title: string; owner_id: number }
  tags: PhotoTag[]
  comments: PhotoComment[]
}

/** Foto con sus etiquetas y comentarios; marca como leídos sus avisos. */
export function getPhoto(id: number): Promise<PhotoDetail> {
  return api(`/api/photos/${id}`)
}

export function listTaggable(photoId: number): Promise<{ people: Person[] }> {
  return api(`/api/photos/${photoId}/taggable`)
}

export function tagPhoto(photoId: number, userId: number, area: TagArea): Promise<{ tags: PhotoTag[] }> {
  return post(`/api/photos/${photoId}/tags`, { user_id: userId, ...area })
}

export function removeTag(tagId: number): Promise<{ message: string; tags: PhotoTag[] }> {
  return api(`/api/photo-tags/${tagId}`, { method: 'DELETE' })
}

export function commentPhoto(photoId: number, body: string): Promise<{ comments: PhotoComment[] }> {
  return post(`/api/photos/${photoId}/comments`, { body })
}

export function deleteComment(commentId: number): Promise<{ message: string; comments: PhotoComment[] }> {
  return api(`/api/photo-comments/${commentId}`, { method: 'DELETE' })
}

/** Fotos en las que sale un usuario (solo las que puede ver quien consulta). */
export function listTaggedPhotos(userId: number, limit?: number): Promise<{ photos: Photo[]; total: number }> {
  return api(`/api/users/${userId}/tagged-photos${limit ? `?limit=${limit}` : ''}`)
}
