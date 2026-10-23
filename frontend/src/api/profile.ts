import { api } from './client'

export type Visibility = 'public' | 'friends' | 'private'

export type Profile = {
  id: number
  first_name: string
  last_name: string
  birthdate: string | null
  bio: string | null
  status: string | null
  avatar_url: string | null
  is_owner: boolean
  visibility?: {
    birthdate: Visibility
    bio: Visibility
    status: Visibility
    avatar: Visibility
  }
}

export type ProfileData = {
  first_name: string
  last_name: string
  birthdate: string
  bio: string
  status: string
  birthdate_visibility: Visibility
  bio_visibility: Visibility
  status_visibility: Visibility
  avatar_visibility: Visibility
}

export type DashboardSummary = {
  invitations_sent: number
  invitations_accepted: number
  friends_count: number
}

export function getProfile(id: number): Promise<{ profile: Profile }> {
  return api(`/api/profiles/${id}`)
}

export function updateProfile(data: ProfileData): Promise<{ profile: Profile }> {
  return api('/api/profile', { method: 'PUT', body: JSON.stringify(data) })
}

export function uploadAvatar(file: File): Promise<{ profile: Profile }> {
  const data = new FormData()
  data.append('avatar', file)
  return api('/api/profile/avatar', { method: 'POST', body: data })
}

export function getDashboardSummary(): Promise<DashboardSummary> {
  return api('/api/dashboard')
}
