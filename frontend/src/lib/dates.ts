/** El backend guarda las fechas en UTC con formato "AAAA-MM-DD HH:MM:SS". */
export function parseServerDate(value: string): Date {
  return new Date(/[zZ]|[+-]\d\d:?\d\d$/.test(value) ? value : `${value.replace(' ', 'T')}Z`)
}

const relative = new Intl.RelativeTimeFormat('es', { numeric: 'auto' })

const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
  ['year', 365 * 24 * 3600],
  ['month', 30 * 24 * 3600],
  ['week', 7 * 24 * 3600],
  ['day', 24 * 3600],
  ['hour', 3600],
  ['minute', 60],
]

/** "hace 43 minutos", "ayer"… */
export function timeAgo(value: string, now: Date = new Date()): string {
  const seconds = Math.round((parseServerDate(value).getTime() - now.getTime()) / 1000)
  for (const [unit, size] of UNITS) {
    if (Math.abs(seconds) >= size) return relative.format(Math.trunc(seconds / size), unit)
  }
  return 'hace un momento'
}

/** "13 de diciembre de 2009, a las 02:12" */
export function formatLong(value: string): string {
  const date = parseServerDate(value)
  const day = new Intl.DateTimeFormat('es', { day: 'numeric', month: 'long', year: 'numeric' }).format(date)
  const time = new Intl.DateTimeFormat('es', { hour: '2-digit', minute: '2-digit' }).format(date)
  return `${day}, a las ${time}`
}

/** "1 de enero de 1990" a partir de una fecha "AAAA-MM-DD" (sin hora ni zona). */
export function formatDay(value: string): string {
  const [year, month, day] = value.split('-').map(Number)
  return new Intl.DateTimeFormat('es', { day: 'numeric', month: 'long', year: 'numeric' }).format(
    new Date(year, month - 1, day),
  )
}

/** "sábado, 21 de noviembre, 18:30" en la hora local. */
export function formatEventDate(value: string): string {
  const date = parseServerDate(value)
  const day = new Intl.DateTimeFormat('es', { weekday: 'long', day: 'numeric', month: 'long' }).format(date)
  const time = new Intl.DateTimeFormat('es', { hour: '2-digit', minute: '2-digit' }).format(date)
  return `${day}, ${time}`
}

/** "18:30" en la hora local. */
export function formatTime(value: string): string {
  return new Intl.DateTimeFormat('es', { hour: '2-digit', minute: '2-digit' }).format(parseServerDate(value))
}

/** Valor de un <input type="datetime-local"> (hora local) a partir de una fecha del servidor. */
export function toLocalInput(value: string): string {
  const date = parseServerDate(value)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** De un <input type="datetime-local"> (hora local) a ISO en UTC; '' si está vacío o no es válido. */
export function fromLocalInput(value: string): string {
  const date = new Date(value)
  return value === '' || Number.isNaN(date.getTime()) ? '' : date.toISOString()
}

/** Días desde hoy (en la hora local) hasta una fecha: 0 hoy, 1 mañana… */
export function daysFromToday(date: Date, now: Date = new Date()): number {
  const start = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const target = new Date(date.getFullYear(), date.getMonth(), date.getDate())
  return Math.round((target.getTime() - start.getTime()) / 86_400_000)
}
