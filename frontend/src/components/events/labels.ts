import type { EventSummary } from '../../api/events'

/** Respuesta propia a un evento, para mostrarla. */
export const STATUS_LABEL: Record<EventSummary['my_status'], string> = {
  organizer: 'Lo organizas tú',
  pending: 'Sin responder',
  yes: 'Vas',
  maybe: 'Quizás vas',
  no: 'No vas',
}
