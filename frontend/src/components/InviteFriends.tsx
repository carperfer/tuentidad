import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { errorMessage, fieldErrors } from '../api/client'
import { listInvitations, sendInvitation, type InvitationList, type InvitationStatus } from '../api/invitations'
import { Alert } from './Alert'
import { Module } from './ui/Module'

const STATUS: Record<InvitationStatus, string> = {
  pending: 'Pendiente',
  accepted: 'Ya está dentro',
  expired: 'Caducada',
}

/** Invitaciones disponibles, email y botón en la misma línea (guía §6.1, InviteFriends). */
export function InviteFriends() {
  const [data, setData] = useState<InvitationList | null>(null)
  const [email, setEmail] = useState('')
  const [fieldError, setFieldError] = useState('')
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [sending, setSending] = useState(false)

  const load = useCallback(() => {
    listInvitations()
      .then(setData)
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [])

  useEffect(load, [load])

  async function handleInvite(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSending(true)
    setFieldError('')
    setError('')
    setNotice('')
    try {
      const result = await sendInvitation(email)
      setNotice(result.message)
      setEmail('')
      load()
    } catch (caught) {
      const fields = fieldErrors(caught)
      if (fields.email) setFieldError(fields.email)
      else setError(errorMessage(caught))
    } finally {
      setSending(false)
    }
  }

  return (
    <Module title="Invitar a tus amigos">
      {data && (
        <p>
          <strong>{data.remaining}</strong> invitaciones disponibles
        </p>
      )}
      {notice && <Alert kind="ok">{notice}</Alert>}
      {error && <Alert>{error}</Alert>}
      <form className="inline-form" onSubmit={handleInvite} noValidate>
        <input
          type="email"
          placeholder="email"
          aria-label="Email de tu amigo"
          aria-invalid={fieldError ? true : undefined}
          aria-describedby={fieldError ? 'invite-error' : undefined}
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          required
        />
        <button type="submit" className="button" disabled={sending || data?.remaining === 0}>
          {sending ? 'Enviando…' : 'Invitar'}
        </button>
      </form>
      {fieldError && (
        <p id="invite-error" className="field__error">
          {fieldError}
        </p>
      )}
      {data && data.invitations.length > 0 && (
        <ul className="invitations">
          {data.invitations.map((invitation) => (
            <li key={invitation.email}>
              <span className="invitations__email">{invitation.email}</span>
              <span className={`badge badge--${invitation.status}`}>{STATUS[invitation.status]}</span>
            </li>
          ))}
        </ul>
      )}
    </Module>
  )
}
