import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { describe, expect, it } from 'vitest'
import { AuthProvider } from '../auth/AuthContext'
import { CalendarModule } from '../components/events/CalendarModule'
import { ana, mockApi, sentBody } from '../test/utils'
import { Event } from './Event'
import { EventForm } from './EventForm'

const person = (id: number, firstName: string) => ({ id, first_name: firstName, last_name: 'López', avatar_url: null })

/** Fecha del servidor (UTC) a tantos días de hoy, a las 18:30 locales. */
function serverDate(days: number): string {
  const date = new Date()
  date.setDate(date.getDate() + days)
  date.setHours(18, 30, 0, 0)
  return date.toISOString().slice(0, 19).replace('T', ' ')
}

const event = (overrides = {}) => ({
  id: 7,
  title: 'Cumpleaños de Bea',
  place: 'Bar La Esquina',
  description: 'Trae ganas de bailar',
  starts_at: serverDate(3),
  organizer: person(2, 'Bea'),
  is_organizer: false,
  my_status: 'pending',
  counts: { yes: 1, maybe: 0, no: 0, pending: 1 },
  invitees: [{ person: person(1, 'Ana'), status: 'pending' }],
  comments: [],
  ...overrides,
})

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>
        <Routes>
          <Route path="/eventos/nuevo" element={<EventForm />} />
          <Route path="/eventos/:id" element={<Event />} />
          <Route path="/inicio" element={<CalendarModule />} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

describe('Events', () => {
  it('creates an event with the local time in UTC and the chosen friends', async () => {
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/events/invitable': () => ({ body: { people: [person(2, 'Bea'), person(3, 'Carla')] } }),
      'POST /api/events': () => ({
        status: 201,
        body: { event: event({ is_organizer: true, my_status: 'organizer', organizer: person(1, 'Ana') }) },
      }),
      'GET /api/events/7': () => ({
        body: { event: event({ is_organizer: true, my_status: 'organizer', organizer: person(1, 'Ana') }) },
      }),
      'GET /api/events/invitable?event=7': () => ({ body: { people: [person(3, 'Carla')] } }),
    })
    const user = userEvent.setup()
    renderAt('/eventos/nuevo')

    await user.type(await screen.findByLabelText('¿Qué celebráis?'), 'Cumpleaños de Bea')
    await user.type(screen.getByLabelText('Día y hora'), '2026-12-05T18:30')
    await user.type(screen.getByLabelText('Lugar'), 'Bar La Esquina')
    await user.click(await screen.findByRole('checkbox', { name: 'Bea López' }))
    expect(screen.getByText('1 amigo elegido')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Crear evento' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Cumpleaños de Bea' })).toBeInTheDocument()
    const call = fetchMock.mock.calls.find(([path, init]) => path === '/api/events' && init?.method === 'POST')
    expect(call && sentBody(call)).toEqual({
      title: 'Cumpleaños de Bea',
      place: 'Bar La Esquina',
      description: '',
      starts_at: new Date('2026-12-05T18:30').toISOString(),
      invite: [2],
    })
  })

  it('lets an invitee answer and shows who is going', async () => {
    const fetchMock = mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/events/7': () => ({ body: { event: event() } }),
      'PUT /api/events/7/rsvp': () => ({
        body: {
          event: event({
            my_status: 'yes',
            invitees: [{ person: person(1, 'Ana'), status: 'yes' }],
            counts: { yes: 2, maybe: 0, no: 0, pending: 0 },
          }),
        },
      }),
    })
    const user = userEvent.setup()
    renderAt('/eventos/7')

    expect(await screen.findByRole('heading', { level: 1, name: 'Cumpleaños de Bea' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Editar' })).not.toBeInTheDocument()
    const attendees = screen.getByRole('region', { name: 'Asistentes' })
    expect(within(attendees).getByText(/Sin responder/)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Voy' }))

    expect(await screen.findByRole('button', { name: 'Voy', pressed: true })).toBeInTheDocument()
    expect(within(attendees).getByRole('heading', { name: /Van/ })).toHaveTextContent('(2)')
    const call = fetchMock.mock.calls.find(([path]) => path === '/api/events/7/rsvp')
    expect(call && sentBody(call)).toEqual({ status: 'yes' })
  })

  it('groups the calendar into today, tomorrow and this week', async () => {
    const birthday = new Date()
    birthday.setDate(birthday.getDate() + 1)
    const pad = (n: number) => String(n).padStart(2, '0')
    mockApi({
      'GET /api/auth/me': () => ({ body: { user: ana } }),
      'GET /api/calendar': () => ({
        body: {
          events: [
            event({ id: 7, title: 'Cena', starts_at: serverDate(0) }),
            event({ id: 8, title: 'Quedada', starts_at: serverDate(4) }),
          ],
          birthdays: [
            {
              person: person(3, 'Carla'),
              date: `${birthday.getFullYear()}-${pad(birthday.getMonth() + 1)}-${pad(birthday.getDate())}`,
            },
          ],
        },
      }),
    })
    renderAt('/inicio')

    const today = (await screen.findByRole('heading', { name: 'Hoy' })).parentElement as HTMLElement
    expect(within(today).getByRole('link', { name: 'Cena' })).toHaveAttribute('href', '/eventos/7')
    const tomorrow = screen.getByRole('heading', { name: 'Mañana' }).parentElement as HTMLElement
    expect(tomorrow).toHaveTextContent('Cumpleaños de Carla López')
    const week = screen.getByRole('heading', { name: 'Esta semana' }).parentElement as HTMLElement
    expect(within(week).getByRole('link', { name: 'Quedada' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Crear evento' })).toHaveAttribute('href', '/eventos/nuevo')
  })
})
