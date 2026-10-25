import { useState } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../../api/client'
import {
  acceptFriendRequest,
  cancelFriendRequest,
  rejectFriendRequest,
  type FriendRequest,
} from '../../api/friends'
import { Alert } from '../Alert'
import { Avatar } from '../ui/Avatar'
import { MediaObject } from '../ui/MediaObject'
import { Counter, Module } from '../ui/Module'

type RequestsModuleProps = {
  kind: 'incoming' | 'outgoing'
  requests: FriendRequest[]
  /** Se llama tras aceptar, rechazar o cancelar, para recargar los datos. */
  onChange: (message: string) => void
  /** Si es false y no hay solicitudes, el módulo no se muestra. */
  showEmpty?: boolean
}

/** Solicitudes de amistad recibidas (aceptar o rechazar) o enviadas (cancelar). */
export function RequestsModule({ kind, requests, onChange, showEmpty = true }: RequestsModuleProps) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const incoming = kind === 'incoming'

  if (requests.length === 0 && !showEmpty) return null

  async function perform(action: () => Promise<{ message: string }>) {
    setBusy(true)
    setError('')
    try {
      onChange((await action()).message)
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Module
      title={
        <>
          {incoming ? 'Solicitudes recibidas' : 'Solicitudes enviadas'} <Counter value={requests.length} />
        </>
      }
    >
      {error && <Alert>{error}</Alert>}
      {requests.length === 0 ? (
        <p className="status">{incoming ? 'No tienes solicitudes pendientes.' : 'No has enviado solicitudes.'}</p>
      ) : (
        <ul className="media-list">
          {requests.map((request) => (
            <li key={request.id}>
              <MediaObject media={<Avatar src={request.avatar_url} name={request.first_name} size={24} decorative />}>
                <Link to={`/perfil/${request.user_id}`} className="person-name">
                  {request.first_name} {request.last_name}
                </Link>
                <div className="actions">
                  {incoming ? (
                    <>
                      <button
                        type="button"
                        className="link-button"
                        disabled={busy}
                        onClick={() => perform(() => acceptFriendRequest(request.id))}
                        aria-label={`Aceptar a ${request.first_name}`}
                      >
                        Aceptar
                      </button>
                      <button
                        type="button"
                        className="link-button link-button--muted"
                        disabled={busy}
                        onClick={() => perform(() => rejectFriendRequest(request.id))}
                        aria-label={`Rechazar a ${request.first_name}`}
                      >
                        Rechazar
                      </button>
                    </>
                  ) : (
                    <button
                      type="button"
                      className="link-button link-button--muted"
                      disabled={busy}
                      onClick={() => perform(() => cancelFriendRequest(request.id))}
                      aria-label={`Cancelar la solicitud a ${request.first_name}`}
                    >
                      Cancelar
                    </button>
                  )}
                </div>
              </MediaObject>
            </li>
          ))}
        </ul>
      )}
    </Module>
  )
}
