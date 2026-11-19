import { api } from './client'

export type Visibility = 'public' | 'friends' | 'private'

export type Relationship = 'self' | 'friend' | 'incoming' | 'outgoing' | 'none'

export type Profile = {
  id: number
  first_name: string
  last_name: string
  birthdate: string | null
  bio: string | null
  status: string | null
  status_updated_at: string | null
  avatar_url: string | null
  is_owner: boolean
  relationship: Relationship
  /** Solicitud de amistad pendiente entre el visitante y el perfil. */
  request_id: number | null
  visibility?: {
    birthdate: Visibility
    bio: Visibility
    status: Visibility
    avatar: Visibility
    networks: Visibility
    links: Visibility
  }
  /** «Redes»; null si su privacidad no deja verlas. */
  networks?: Networks | null
  /** Enlaces a redes sociales; null si su privacidad no deja verlos. */
  links?: SocialLink[] | null
  /** Solo para el dueño: frecuencia de los emails de resumen. */
  digest?: Digest
}

export type Digest = 'none' | 'daily' | 'weekly'

export type NetworkKind = 'university' | 'work' | 'school'

export type NetworkItem = { name: string; detail: string; start_year: number | null; end_year: number | null }

export type Networks = Record<NetworkKind, NetworkItem[]>

export type SocialLink = { url: string; network: string }

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

export function updateSettings(settings: { digest: Digest }): Promise<{ profile: Profile }> {
  return api('/api/profile/settings', { method: 'PUT', body: JSON.stringify(settings) })
}

/** Sustituye los estudios y trabajos del perfil. */
export function updateNetworks(
  visibility: Visibility,
  items: (NetworkItem & { kind: NetworkKind })[],
): Promise<{ profile: Profile }> {
  return api('/api/profile/networks', { method: 'PUT', body: JSON.stringify({ visibility, items }) })
}

/** Sustituye los enlaces a redes sociales. */
export function updateLinks(visibility: Visibility, links: string[]): Promise<{ profile: Profile }> {
  return api('/api/profile/links', { method: 'PUT', body: JSON.stringify({ visibility, links }) })
}

export function updateStatus(status: string): Promise<{ profile: Profile }> {
  return api('/api/profile/status', { method: 'PUT', body: JSON.stringify({ status }) })
}

export function uploadAvatar(file: File): Promise<{ profile: Profile }> {
  const data = new FormData()
  data.append('avatar', file)
  return api('/api/profile/avatar', { method: 'POST', body: data })
}

export function getDashboardSummary(): Promise<DashboardSummary> {
  return api('/api/dashboard')
}
