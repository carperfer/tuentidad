import { api, post } from './client'

export type InvitationStatus = 'pending' | 'accepted' | 'expired'

export type Invitation = {
  email: string
  status: InvitationStatus
  created_at: string
  expires_at: string
}

export type InvitationList = {
  remaining: number
  invitations: Invitation[]
}

export type PublicInvitation = {
  email: string
  inviter: string | null
  /** La invitación viene de un perfil de bienvenida: al registrarse, serán amigos. */
  welcome: boolean
}

export function listInvitations(): Promise<InvitationList> {
  return api<InvitationList>('/api/invitations')
}

export function sendInvitation(email: string): Promise<{ message: string; remaining: number }> {
  return post('/api/invitations', { email })
}

export function getInvitation(token: string): Promise<PublicInvitation> {
  return api<PublicInvitation>(`/api/invitations/${encodeURIComponent(token)}`)
}
