import { useId, useState } from 'react'
import type { Person } from '../../api/friends'
import { Avatar } from '../ui/Avatar'

type FriendPickerProps = {
  people: Person[]
  selected: number[]
  onChange: (selected: number[]) => void
}

/** Lista de amigos con casillas y un filtro por nombre, para invitar a un evento. */
export function FriendPicker({ people, selected, onChange }: FriendPickerProps) {
  const [filter, setFilter] = useState('')
  const filterId = useId()

  if (people.length === 0) return <p className="status">No te quedan amigos por invitar.</p>

  const term = filter.trim().toLocaleLowerCase('es')
  const visible = people.filter((person) => `${person.first_name} ${person.last_name}`.toLocaleLowerCase('es').includes(term))
  const toggle = (id: number) => onChange(selected.includes(id) ? selected.filter((item) => item !== id) : [...selected, id])

  return (
    <div className="friend-picker">
      {people.length > 6 && (
        <div className="field">
          <label htmlFor={filterId}>Buscar entre tus amigos</label>
          <input id={filterId} type="search" value={filter} onChange={(event) => setFilter(event.target.value)} />
        </div>
      )}
      <ul className="friend-picker__list">
        {visible.map((person) => (
          <li key={person.id}>
            <label className="friend-picker__item">
              <input type="checkbox" checked={selected.includes(person.id)} onChange={() => toggle(person.id)} />
              <Avatar src={person.avatar_url} name={person.first_name} size={24} decorative />
              <span>
                {person.first_name} {person.last_name}
              </span>
            </label>
          </li>
        ))}
      </ul>
      <p className="muted">{selected.length === 1 ? '1 amigo elegido' : `${selected.length} amigos elegidos`}</p>
    </div>
  )
}
