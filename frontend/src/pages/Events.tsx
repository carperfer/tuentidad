import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../api/client'
import { listEvents, type EventSummary } from '../api/events'
import { Alert } from '../components/Alert'
import { EventItem } from '../components/events/EventItem'
import { UserCard } from '../components/UserCard'
import { Columns } from '../components/ui/Columns'
import { Counter, Module } from '../components/ui/Module'

/** Eventos propios y a los que te han invitado: próximos y pasados. */
export function Events() {
  const [events, setEvents] = useState<{ upcoming: EventSummary[]; past: EventSummary[]; past_more: boolean } | null>(null)
  const [error, setError] = useState('')
  const [loadingMore, setLoadingMore] = useState(false)

  useEffect(() => {
    listEvents()
      .then(setEvents)
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [])

  async function loadMorePast() {
    if (!events) return
    setLoadingMore(true)
    try {
      const more = await listEvents(events.past.length)
      setEvents({ ...events, past: [...events.past, ...more.past], past_more: more.past_more })
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setLoadingMore(false)
    }
  }

  return (
    <Columns left={<UserCard />}>
      {error && <Alert>{error}</Alert>}
      <Module
        title={<>Próximos eventos {events && <Counter value={events.upcoming.length} />}</>}
        as="h1"
        action={
          <Link to="/eventos/nuevo" className="button">
            Crear evento
          </Link>
        }
      >
        {events === null ? (
          !error && <p className="status">Cargando eventos…</p>
        ) : events.upcoming.length === 0 ? (
          <p className="status">
            No tienes eventos a la vista. <Link to="/eventos/nuevo">Organiza uno</Link> e invita a tus amigos.
          </p>
        ) : (
          <ul className="media-list">
            {events.upcoming.map((event) => (
              <li key={event.id}>
                <EventItem event={event} />
              </li>
            ))}
          </ul>
        )}
      </Module>
      {events && events.past.length > 0 && (
        <Module title="Eventos pasados">
          <ul className="media-list">
            {events.past.map((event) => (
              <li key={event.id}>
                <EventItem event={event} />
              </li>
            ))}
          </ul>
          {events.past_more && (
            <p className="load-more">
              <button type="button" className="button" disabled={loadingMore} onClick={loadMorePast}>
                {loadingMore ? 'Cargando…' : 'Ver más eventos pasados'}
              </button>
            </p>
          )}
        </Module>
      )}
    </Columns>
  )
}
