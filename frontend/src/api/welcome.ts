import { api, post } from './client'

export type WelcomeProfile = {
  id: number
  first_name: string
  last_name: string
  bio: string
  avatar: string
}

export async function listWelcomeProfiles(): Promise<WelcomeProfile[]> {
  const { profiles } = await api<{ profiles: WelcomeProfile[] }>('/api/welcome-profiles')
  return profiles
}

export async function requestFriendship(
  profileId: number,
  data: { email: string; accept_privacy: boolean; website: string },
): Promise<string> {
  const { message } = await post<{ message: string }>(`/api/welcome-profiles/${profileId}/friend-requests`, data)
  return message
}
