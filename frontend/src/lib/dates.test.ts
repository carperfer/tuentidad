import { describe, expect, it } from 'vitest'
import { formatDay, parseServerDate, timeAgo } from './dates'

describe('dates', () => {
  it('reads server dates as UTC', () => {
    expect(parseServerDate('2026-10-25 10:00:00').toISOString()).toBe('2026-10-25T10:00:00.000Z')
  })

  it('formats relative times in Spanish', () => {
    const now = new Date('2026-10-25T12:00:00Z')
    expect(timeAgo('2026-10-25 10:00:00', now)).toBe('hace 2 horas')
    expect(timeAgo('2026-10-25 11:59:30', now)).toBe('hace un momento')
    expect(timeAgo('2026-10-24 12:00:00', now)).toBe('ayer')
  })

  it('formats birthdays without shifting the day', () => {
    expect(formatDay('1990-01-01')).toBe('1 de enero de 1990')
  })
})
