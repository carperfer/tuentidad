import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { listEvents, type EventSummary } from '../../api/events'
import { formatEventDate } from '../../lib/dates'
import { Counter, Module } from '../ui/Module'
import { DateBadge } from './EventItem'
import { STATUS_LABEL } from './labels'

const SHOWN = 4

/** «Eventos» de Inicio: los próximos, primero las invitaciones sin responder (encima del calendario). */
export function EventsModule() {
  const [events, setEvents] = useState<EventSummary[] | null>(null)

  useEffect(() => {
    listEvents()
      .then(({ upcoming }) =>
        // Las invitaciones pendientes primero; dentro de cada grupo, por fecha
        setEvents([...upcoming].sort((a, b) => Number(b.my_status === 'pending') - Number(a.my_status === 'pending'))),
      )
      .catch(() => setEvents([]))
  }, [])

  const pending = events?.filter((event) => event.my_status === 'pending').length ?? 0

  return (
    <Module
      title={<>Eventos {events && events.length > 0 && <Counter value={events.length} />}</>}
      action={<Link to="/eventos">Ver todos</Link>}
    >
      {events === null ? (
        <p className="status">Cargando…</p>
      ) : events.length === 0 ? (
        <p className="status">No tienes eventos próximos.</p>
      ) : (
        <>
          {pending > 0 && (
            <p className="notifications__new">
              <Link to="/eventos">{pending === 1 ? '1 invitación sin responder' : `${pending} invitaciones sin responder`}</Link>
            </p>
          )}
          <ul className="events-module">
            {events.slice(0, SHOWN).map((event) => (
              <li key={event.id}>
                <DateBadge value={event.starts_at} />
                <div>
                  <Link to={`/eventos/${event.id}`}>{event.title}</Link>
                  <p className="muted">
                    <time dateTime={event.starts_at}>{formatEventDate(event.starts_at)}</time>
                  </p>
                  <p className={`event-status event-status--${event.my_status}`}>{STATUS_LABEL[event.my_status]}</p>
                </div>
              </li>
            ))}
          </ul>
        </>
      )}
    </Module>
  )
}
