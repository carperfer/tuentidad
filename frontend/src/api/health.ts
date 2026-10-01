import { api } from './client'

export type Health = {
  status: 'ok' | 'degraded'
  database: 'ok' | 'error'
  time: string
}

export function getHealth(): Promise<Health> {
  return api<Health>('/api/health')
}
