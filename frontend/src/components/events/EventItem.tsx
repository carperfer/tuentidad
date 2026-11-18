import { Link } from 'react-router'
import type { EventSummary } from '../../api/events'
import { formatEventDate, parseServerDate } from '../../lib/dates'
import { MediaObject } from '../ui/MediaObject'
import { STATUS_LABEL } from './labels'

/** Hoja de calendario con el día y el mes. */
export function DateBadge({ value }: { value: string }) {
  const date = parseServerDate(value)
  return (
    <span className="date-badge" aria-hidden="true">
      <span className="date-badge__month">{new Intl.DateTimeFormat('es', { month: 'short' }).format(date)}</span>
      <span className="date-badge__day">{date.getDate()}</span>
    </span>
  )
}

/** Un evento en una lista: fecha, título, lugar y la respuesta propia. */
export function EventItem({ event }: { event: EventSummary }) {
  return (
    <MediaObject media={<DateBadge value={event.starts_at} />}>
      <p className="feed__line">
        <Link to={`/eventos/${event.id}`} className="person-name">
          {event.title}
        </Link>
      </p>
      <p className="muted">
        <time dateTime={event.starts_at}>{formatEventDate(event.starts_at)}</time>
        {event.place && ` · ${event.place}`}
      </p>
      <p className={`event-status event-status--${event.my_status}`}>
        {STATUS_LABEL[event.my_status]} · {event.counts.yes} {event.counts.yes === 1 ? 'va' : 'van'}
      </p>
    </MediaObject>
  )
}
