import { useState, type FormEvent } from 'react'
import { errorMessage } from '../../api/client'
import { updateStatus, type Profile } from '../../api/profile'
import { timeAgo } from '../../lib/dates'

/** Caja de estado del perfil propio: se cambia aquí mismo, como en la barra de Inicio. */
export function StatusEditor({ profile, onChange }: { profile: Profile; onChange: (profile: Profile) => void }) {
  const [status, setStatus] = useState(profile.status ?? '')
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setError('')
    try {
      onChange((await updateStatus(status.trim())).profile)
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setSaving(false)
    }
  }

  return (
    <form className="status-box status-editor" onSubmit={handleSubmit}>
      <label className="visually-hidden" htmlFor="profile-status">
        Tu estado
      </label>
      <input
        id="profile-status"
        type="text"
        maxLength={160}
        placeholder="¿Cómo va todo?"
        value={status}
        onChange={(event) => setStatus(event.target.value)}
      />
      <button type="submit" className="button" disabled={saving || status.trim() === (profile.status ?? '')}>
        Cambiar
      </button>
      {profile.status && profile.status_updated_at && (
        <time className="muted" dateTime={profile.status_updated_at}>
          {timeAgo(profile.status_updated_at)}
        </time>
      )}
      {error && <span className="field__error">{error}</span>}
    </form>
  )
}
