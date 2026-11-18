import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { getCalendar, type Birthday, type EventSummary } from '../../api/events'
import { daysFromToday, formatTime, parseServerDate } from '../../lib/dates'
import { Module } from '../ui/Module'

type Entry = { key: string; date: Date; kind: 'birthday' | 'event'; birthday?: Birthday; event?: EventSummary }

const GROUPS = [
  { title: 'Hoy', from: 0, to: 0 },
  { title: 'Mañana', from: 1, to: 1 },
  { title: 'Esta semana', from: 2, to: 7 },
]

/** "Calendario" de Inicio: eventos y cumpleaños de hoy, mañana y esta semana (guía §6.1). */
export function CalendarModule() {
  const [entries, setEntries] = useState<Entry[] | null>(null)

  useEffect(() => {
    getCalendar()
      .then(({ events, birthdays }) => {
        setEntries(
          [
            ...birthdays.map((birthday): Entry => {
              const [year, month, day] = birthday.date.split('-').map(Number)
              return { key: `b${birthday.person.id}`, date: new Date(year, month - 1, day), kind: 'birthday', birthday }
            }),
            ...events.map((event): Entry => ({
              key: `e${event.id}`,
              date: parseServerDate(event.starts_at),
              kind: 'event',
              event,
            })),
          ].sort((a, b) => a.date.getTime() - b.date.getTime()),
        )
      })
      .catch(() => setEntries([]))
  }, [])

  const groups = GROUPS.map((group) => ({
    ...group,
    entries: (entries ?? []).filter((entry) => {
      const days = daysFromToday(entry.date)
      // Un evento empezado hace un rato sigue en «Hoy»
      return (group.from === 0 ? days <= 0 : days >= group.from) && days <= group.to
    }),
  })).filter((group) => group.entries.length > 0)

  return (
    <Module
      title="Calendario"
      action={<Link to="/eventos/nuevo">Crear evento</Link>}
      footer={<Link to="/eventos">Ver todos mis eventos</Link>}
    >
      {entries === null ? (
        <p className="status">Cargando…</p>
      ) : groups.length === 0 ? (
        <p className="status">No tienes ningún evento esta semana.</p>
      ) : (
        groups.map((group) => (
          <div key={group.title} className="calendar__group">
            <h3 className="calendar__title">{group.title}</h3>
            <ul className="calendar__list">
              {group.entries.map((entry) => (
                <li key={entry.key}>
                  {entry.birthday && (
                    <>
                      <span aria-hidden="true">🎂</span> Cumpleaños de{' '}
                      <Link to={`/perfil/${entry.birthday.person.id}`}>
                        {entry.birthday.person.first_name} {entry.birthday.person.last_name}
                      </Link>
                    </>
                  )}
                  {entry.event && (
                    <>
                      <span aria-hidden="true">📅</span> <Link to={`/eventos/${entry.event.id}`}>{entry.event.title}</Link>{' '}
                      <time className="muted" dateTime={entry.event.starts_at}>
                        {group.from === 2 ? new Intl.DateTimeFormat('es', { weekday: 'long' }).format(entry.date) + ', ' : ''}
                        {formatTime(entry.event.starts_at)}
                      </time>
                    </>
                  )}
                </li>
              ))}
            </ul>
          </div>
        ))
      )}
    </Module>
  )
}
