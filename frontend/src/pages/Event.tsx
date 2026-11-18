import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { errorMessage } from '../api/client'
import {
  commentEvent,
  deleteEvent,
  deleteEventComment,
  getEvent,
  inviteToEvent,
  listInvitable,
  respondToEvent,
  type EventDetail,
  type RsvpStatus,
} from '../api/events'
import type { Person } from '../api/friends'
import { notifyNotificationsChanged } from '../api/notifications'
import { Alert } from '../components/Alert'
import { Comments } from '../components/Comments'
import { DateBadge } from '../components/events/EventItem'
import { STATUS_LABEL } from '../components/events/labels'
import { FriendPicker } from '../components/events/FriendPicker'
import { Avatar } from '../components/ui/Avatar'
import { Columns } from '../components/ui/Columns'
import { MediaObject } from '../components/ui/MediaObject'
import { Counter, Module } from '../components/ui/Module'
import { formatEventDate } from '../lib/dates'

const ANSWERS: { status: RsvpStatus; label: string }[] = [
  { status: 'yes', label: 'Voy' },
  { status: 'maybe', label: 'Quizás' },
  { status: 'no', label: 'No voy' },
]

const GROUPS: { status: 'yes' | 'maybe' | 'pending' | 'no'; title: string }[] = [
  { status: 'yes', title: 'Van' },
  { status: 'maybe', title: 'Quizás' },
  { status: 'pending', title: 'Sin responder' },
  { status: 'no', title: 'No van' },
]

/** Página de un evento: datos, respuesta, invitados y comentarios. */
export function Event() {
  const { id } = useParams()
  const eventId = Number(id)
  const navigate = useNavigate()
  const [event, setEvent] = useState<EventDetail | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const [invitable, setInvitable] = useState<Person[] | null>(null)
  const [invite, setInvite] = useState<number[]>([])

  useEffect(() => {
    if (!Number.isInteger(eventId) || eventId < 1) return
    getEvent(eventId)
      .then(({ event: result }) => {
        setEvent(result)
        // Al verlo se marcan como leídos sus avisos
        notifyNotificationsChanged()
        if (result.is_organizer) return listInvitable(eventId).then(({ people }) => setInvitable(people))
      })
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [eventId])

  async function run(action: () => Promise<{ event: EventDetail }>, message = '') {
    setBusy(true)
    setError('')
    setNotice('')
    try {
      setEvent((await action()).event)
      setNotice(message)
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setBusy(false)
    }
  }

  async function handleInvite() {
    await run(() => inviteToEvent(eventId, invite), invite.length === 1 ? 'Invitación enviada.' : 'Invitaciones enviadas.')
    setInvite([])
    setInvitable((await listInvitable(eventId)).people)
  }

  async function handleDelete() {
    try {
      await deleteEvent(eventId)
      navigate('/eventos', { replace: true })
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  if (event === null) {
    return <Columns>{error ? <Alert>{error}</Alert> : <p className="status">Cargando evento…</p>}</Columns>
  }

  const organizerName = `${event.organizer.first_name} ${event.organizer.last_name}`
  const groups = GROUPS.map((group) => ({
    ...group,
    people:
      group.status === 'yes'
        ? [event.organizer, ...event.invitees.filter((item) => item.status === 'yes').map((item) => item.person)]
        : event.invitees.filter((item) => item.status === group.status).map((item) => item.person),
  })).filter((group) => group.people.length > 0)

  return (
    <Columns
      left={
        <Module title="Organiza">
          <MediaObject media={<Avatar src={event.organizer.avatar_url} name={event.organizer.first_name} size={50} decorative />}>
            <Link to={`/perfil/${event.organizer.id}`} className="person-name">
              {organizerName}
            </Link>
            {event.is_organizer && <p className="muted">Tú</p>}
          </MediaObject>
        </Module>
      }
      right={
        <>
          <Module title="Asistentes">
            {groups.map((group) => (
              <div key={group.status} className="attendees">
                <h3 className="calendar__title">
                  {group.title} <Counter value={group.people.length} />
                </h3>
                <ul className="attendees__list">
                  {group.people.map((person) => (
                    <li key={person.id}>
                      <Link to={`/perfil/${person.id}`}>
                        {person.first_name} {person.last_name}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </Module>
          {event.is_organizer && invitable && (
            <Module title="Invitar a más amigos">
              <FriendPicker people={invitable} selected={invite} onChange={setInvite} />
              {invitable.length > 0 && (
                <button type="button" className="button" disabled={busy || invite.length === 0} onClick={handleInvite}>
                  Invitar
                </button>
              )}
            </Module>
          )}
        </>
      }
    >
      {notice && <Alert kind="ok">{notice}</Alert>}
      {error && <Alert>{error}</Alert>}
      <Module title={event.title} as="h1" action={event.is_organizer && <Link to={`/eventos/${event.id}/editar`}>Editar</Link>}>
        <div className="event-header">
          <DateBadge value={event.starts_at} />
          <dl className="info-list">
            <dt>Cuándo</dt>
            <dd>
              <time dateTime={event.starts_at}>{formatEventDate(event.starts_at)}</time>
            </dd>
            {event.place && (
              <>
                <dt>Dónde</dt>
                <dd>{event.place}</dd>
              </>
            )}
          </dl>
        </div>
        {event.description && <p className="event-description">{event.description}</p>}

        {event.is_organizer ? (
          <p className="event-status event-status--organizer">{STATUS_LABEL.organizer}</p>
        ) : (
          <div className="rsvp" role="group" aria-label="¿Vas a ir?">
            <span>¿Vas a ir?</span>
            {ANSWERS.map((answer) => (
              <button
                key={answer.status}
                type="button"
                className={`button${event.my_status === answer.status ? ' button--primary' : ''}`}
                aria-pressed={event.my_status === answer.status}
                disabled={busy}
                onClick={() => run(() => respondToEvent(event.id, answer.status))}
              >
                {answer.label}
              </button>
            ))}
          </div>
        )}

        {event.is_organizer && (
          <div className="danger-zone">
            {confirmingDelete ? (
              <>
                <span>¿Borrar el evento? Desaparecerá también para los invitados. </span>
                <button type="button" className="button button--danger" onClick={handleDelete}>
                  Sí, borrarlo
                </button>{' '}
                <button type="button" className="link-button" onClick={() => setConfirmingDelete(false)}>
                  Cancelar
                </button>
              </>
            ) : (
              <button type="button" className="link-button" onClick={() => setConfirmingDelete(true)}>
                Borrar evento
              </button>
            )}
          </div>
        )}
      </Module>
      <Comments
        id={`event-${event.id}`}
        comments={event.comments}
        onSubmit={async (body) => (await commentEvent(event.id, body)).comments}
        onDelete={async (commentId) => (await deleteEventComment(commentId)).comments}
        onChange={(comments) => setEvent((current) => current && { ...current, comments })}
      />
    </Columns>
  )
}
