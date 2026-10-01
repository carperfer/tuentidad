import { api, post, resetCsrf } from './client'

export type User = {
  id: number
  email: string
  first_name: string
  last_name: string
}

type UserResponse = { user: User }
type MessageResponse = { message: string }

export type RegisterData = {
  token: string
  first_name: string
  last_name: string
  birthdate: string
  password: string
  password_confirm: string
  accept_terms: boolean
}

export async function getCurrentUser(): Promise<User | null> {
  const { user } = await api<{ user: User | null }>('/api/auth/me')
  return user
}

export async function login(email: string, password: string, remember: boolean): Promise<User> {
  const { user } = await post<UserResponse>('/api/auth/login', { email, password, remember })
  return user
}

export async function logout(): Promise<void> {
  await post('/api/auth/logout')
  // La sesión cambia al salir, y con ella el token CSRF
  resetCsrf()
}

export async function register(data: RegisterData): Promise<User> {
  const { user } = await post<UserResponse>('/api/auth/register', data)
  return user
}

export async function forgotPassword(email: string): Promise<string> {
  const { message } = await post<MessageResponse>('/api/auth/forgot-password', { email })
  return message
}

export async function resetPassword(token: string, password: string, passwordConfirm: string): Promise<string> {
  const { message } = await post<MessageResponse>('/api/auth/reset-password', {
    token,
    password,
    password_confirm: passwordConfirm,
  })
  return message
}
