import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import { errorMessage } from '../api/client'
import {
  listFriendRequests,
  listFriends,
  removeFriend,
  searchPeople,
  sendFriendRequest,
  type FriendRequests,
  type Person,
} from '../api/friends'
import { Alert } from '../components/Alert'
import { RequestsModule } from '../components/people/RequestsModule'
import { UserCard } from '../components/UserCard'
import { Avatar } from '../components/ui/Avatar'
import { Columns } from '../components/ui/Columns'
import { MediaObject } from '../components/ui/MediaObject'
import { Counter, Module } from '../components/ui/Module'

const RELATIONSHIP_LABEL: Record<NonNullable<Person['relationship']>, string> = {
  none: 'Añadir amigo',
  incoming: 'Te ha enviado una solicitud',
  outgoing: 'Solicitud enviada',
  friend: 'Ya sois amigos',
}

function fetchLists() {
  return Promise.all([listFriends(), listFriendRequests()])
}

async function fetchResults(term: string): Promise<Person[] | null> {
  return term.length >= 2 ? (await searchPeople(term)).users : null
}

/** Gente: búsqueda de personas, amigos y solicitudes. */
export function People() {
  const [params, setParams] = useSearchParams()
  const term = params.get('q')?.trim() ?? ''
  const [query, setQuery] = useState(term)
  const [results, setResults] = useState<Person[] | null>(null)
  const [friends, setFriends] = useState<Person[]>([])
  const [requests, setRequests] = useState<FriendRequests>({ incoming: [], outgoing: [] })
  const [confirmRemove, setConfirmRemove] = useState<number | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  const applyLists = useCallback(([friendData, requestData]: Awaited<ReturnType<typeof fetchLists>>) => {
    setFriends(friendData.friends)
    setRequests(requestData)
  }, [])

  useEffect(() => {
    fetchLists()
      .then(applyLists)
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [applyLists])

  useEffect(() => {
    fetchResults(term)
      .then(setResults)
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [term])

  function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setParams(query.trim() ? { q: query.trim() } : {})
  }

  async function refresh(message: string) {
    setNotice(message)
    setConfirmRemove(null)
    try {
      const [lists, found] = await Promise.all([fetchLists(), fetchResults(term)])
      applyLists(lists)
      setResults(found)
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  async function perform(action: () => Promise<{ message: string }>) {
    setError('')
    setNotice('')
    setBusy(true)
    try {
      await refresh((await action()).message)
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Columns
      left={<UserCard friendsCount={friends.length} pendingRequests={requests.incoming.length} />}
      right={
        <>
          <RequestsModule kind="incoming" requests={requests.incoming} onChange={refresh} />
          <RequestsModule kind="outgoing" requests={requests.outgoing} onChange={refresh} />
        </>
      }
    >
      {notice && <Alert kind="ok">{notice}</Alert>}
      {error && <Alert>{error}</Alert>}

      <Module title="Buscar gente" as="h1">
        <form className="inline-form" role="search" onSubmit={handleSearch}>
          <input
            type="search"
            aria-label="Buscar personas"
            placeholder="Nombre o apellido"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            autoComplete="off"
          />
          <button type="submit" className="button">
            Buscar
          </button>
        </form>
        {term !== '' && term.length < 2 && <p className="status">Escribe al menos dos caracteres.</p>}
        {results !== null &&
          (results.length === 0 ? (
            <p className="status">No se han encontrado personas.</p>
          ) : (
            <ul className="media-list" aria-label="Resultados de búsqueda">
              {results.map((person) => (
                <li key={person.id}>
                  <MediaObject
                    media={<Avatar src={person.avatar_url} name={person.first_name} size={40} decorative />}
                    aside={
                      person.relationship === 'none' ? (
                        <button
                          type="button"
                          className="button"
                          disabled={busy}
                          onClick={() => perform(() => sendFriendRequest(person.id))}
                        >
                          {RELATIONSHIP_LABEL.none}
                        </button>
                      ) : (
                        <span className="muted">{RELATIONSHIP_LABEL[person.relationship ?? 'none']}</span>
                      )
                    }
                  >
                    <Link to={`/perfil/${person.id}`} className="person-name">
                      {person.first_name} {person.last_name}
                    </Link>
                  </MediaObject>
                </li>
              ))}
            </ul>
          ))}
      </Module>

      <Module
        title={
          <>
            Mis amigos <Counter value={friends.length} />
          </>
        }
      >
        {friends.length === 0 ? (
          <p className="status">Todavía no tienes amigos en tuentidad. Búscalos por su nombre.</p>
        ) : (
          <ul className="media-list">
            {friends.map((friend) => (
              <li key={friend.id}>
                <MediaObject
                  media={<Avatar src={friend.avatar_url} name={friend.first_name} size={40} decorative />}
                  aside={
                    confirmRemove === friend.id ? (
                      <span className="actions">
                        <button
                          type="button"
                          className="link-button link-button--danger"
                          disabled={busy}
                          onClick={() => perform(() => removeFriend(friend.id))}
                        >
                          Sí, eliminar
                        </button>
                        <button type="button" className="link-button link-button--muted" onClick={() => setConfirmRemove(null)}>
                          Cancelar
                        </button>
                      </span>
                    ) : (
                      <button
                        type="button"
                        className="link-button link-button--muted"
                        onClick={() => setConfirmRemove(friend.id)}
                        aria-label={`Eliminar a ${friend.first_name} de tus amigos`}
                      >
                        Eliminar
                      </button>
                    )
                  }
                >
                  <Link to={`/perfil/${friend.id}`} className="person-name">
                    {friend.first_name} {friend.last_name}
                  </Link>
                </MediaObject>
              </li>
            ))}
          </ul>
        )}
      </Module>
    </Columns>
  )
}
