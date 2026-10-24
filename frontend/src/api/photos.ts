import { api, post } from './client'

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
