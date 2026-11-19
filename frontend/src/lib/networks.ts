import type { NetworkItem } from '../api/profile'

/** "2006-actualmente", "Hasta 2001" o "1990-1992". */
export function yearsLabel(item: Pick<NetworkItem, 'start_year' | 'end_year'>): string {
  if (item.start_year === null) return item.end_year === null ? '' : `Hasta ${item.end_year}`
  return item.end_year === null ? `${item.start_year}-actualmente` : `${item.start_year}-${item.end_year}`
}
