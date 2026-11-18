import { useEffect, useId, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError, errorMessage, fieldErrors } from '../api/client'
import { createEvent, getEvent, listInvitable, updateEvent, type EventData } from '../api/events'
import type { Person } from '../api/friends'
import { Alert } from '../components/Alert'
import { FriendPicker } from '../components/events/FriendPicker'
import { Field } from '../components/Field'
import { UserCard } from '../components/UserCard'
import { Columns } from '../components/ui/Columns'
import { Module } from '../components/ui/Module'
import { fromLocalInput, toLocalInput } from '../lib/dates'

type Form = { title: string; startsAt: string; place: string; description: string }

const EMPTY: Form = { title: '', startsAt: '', place: '', description: '' }

/** Crear un evento (e invitar a amigos) o editar uno propio. */
export function EventForm() {
  const { id } = useParams()
  const eventId = id === undefined ? null : Number(id)
  const navigate = useNavigate()
  const descriptionId = useId()
  const [form, setForm] = useState<Form | null>(eventId === null ? EMPTY : null)
  const [friends, setFriends] = useState<Person[] | null>(null)
  const [invite, setInvite] = useState<number[]>([])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (eventId === null) {
      listInvitable()
        .then(({ people }) => setFriends(people))
        .catch(() => setFriends([]))
      return
    }
    getEvent(eventId)
      .then(({ event }) => {
        if (!event.is_organizer) throw new Error('Solo quien organiza puede editar el evento.')
        setForm({
          title: event.title,
          startsAt: toLocalInput(event.starts_at),
          place: event.place,
          description: event.description,
        })
      })
      .catch((caught: unknown) =>
        setError(caught instanceof ApiError || !(caught instanceof Error) ? errorMessage(caught) : caught.message),
      )
  }, [eventId])

  const set = (key: keyof Form, value: string) => setForm((current) => current && { ...current, [key]: value })

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!form) return
    setSaving(true)
    setErrors({})
    setError('')
    const data: EventData = {
      title: form.title,
      place: form.place,
      description: form.description,
      starts_at: fromLocalInput(form.startsAt),
    }
    try {
      const result = eventId === null ? await createEvent({ ...data, invite }) : await updateEvent(eventId, data)
      navigate(`/eventos/${result.event.id}`, { replace: eventId !== null })
    } catch (caught) {
      setErrors(fieldErrors(caught))
      setError(errorMessage(caught))
      setSaving(false)
    }
  }

  const title = eventId === null ? 'Crear evento' : 'Editar evento'

  return (
    <Columns left={<UserCard />}>
      <Module title={title} as="h1" action={<Link to={eventId === null ? '/eventos' : `/eventos/${eventId}`}>Cancelar</Link>}>
        {error && <Alert>{error}</Alert>}
        {form === null ? (
          !error && <p className="status">Cargando evento…</p>
        ) : (
          <form onSubmit={handleSubmit} noValidate>
            <Field
              label="¿Qué celebráis?"
              placeholder="Cumpleaños, fiesta, quedada…"
              maxLength={100}
              value={form.title}
              onChange={(event) => set('title', event.target.value)}
              error={errors.title}
            />
            <Field
              label="Día y hora"
              type="datetime-local"
              value={form.startsAt}
              onChange={(event) => set('startsAt', event.target.value)}
              error={errors.starts_at}
            />
            <Field
              label="Lugar"
              maxLength={150}
              value={form.place}
              onChange={(event) => set('place', event.target.value)}
              error={errors.place}
            />
            <div className="field">
              <label htmlFor={descriptionId}>Descripción</label>
              <textarea
                id={descriptionId}
                rows={4}
                maxLength={2000}
                value={form.description}
                onChange={(event) => set('description', event.target.value)}
                aria-invalid={errors.description ? true : undefined}
              />
              {errors.description && <p className="field__error">{errors.description}</p>}
            </div>
            {eventId === null && (
              <fieldset className="fieldset">
                <legend>Invitar a amigos</legend>
                {friends === null ? (
                  <p className="status">Cargando amigos…</p>
                ) : (
                  <FriendPicker people={friends} selected={invite} onChange={setInvite} />
                )}
                {errors.invite && <p className="field__error">{errors.invite}</p>}
              </fieldset>
            )}
            <button type="submit" className="button button--primary" disabled={saving}>
              {saving ? 'Guardando…' : eventId === null ? 'Crear evento' : 'Guardar cambios'}
            </button>
          </form>
        )}
      </Module>
    </Columns>
  )
}
