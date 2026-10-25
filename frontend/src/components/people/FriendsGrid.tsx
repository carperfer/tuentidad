import type { ReactNode } from 'react'
import { Link } from 'react-router'
import type { Person } from '../../api/friends'
import { Avatar } from '../ui/Avatar'
import { Counter, Module } from '../ui/Module'

type FriendsGridProps = {
  title: string
  people: Person[]
  total: number
  /** Enlace "Ver todos (n)" cuando hay más personas de las que se muestran. */
  moreTo?: string
  empty?: ReactNode
  as?: 'h1' | 'h2'
}

/** Rejilla de 3 columnas con foto y nombre en dos líneas (guía §6.3, FriendsGrid). */
export function FriendsGrid({ title, people, total, moreTo, empty, as }: FriendsGridProps) {
  if (people.length === 0 && !empty) return null

  return (
    <Module
      title={title}
      as={as}
      footer={
        moreTo && total > people.length ? (
          <Link to={moreTo}>
            Ver todos <Counter value={total} />
          </Link>
        ) : undefined
      }
    >
      {people.length === 0 ? (
        <p className="status">{empty}</p>
      ) : (
        <ul className="friends-grid">
          {people.map((person) => (
            <li key={person.id}>
              <Link to={`/perfil/${person.id}`}>
                <Avatar src={person.avatar_url} name={person.first_name} size={50} decorative />
                <span>
                  {person.first_name}
                  <br />
                  {person.last_name}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Module>
  )
}
