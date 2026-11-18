import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../api/client'
import { getProfile, updateStatus } from '../api/profile'
import { useAuth } from '../auth/context'
import { timeAgo } from '../lib/dates'
import { Avatar } from './ui/Avatar'

/** Franja "¿Cómo va todo?" bajo la barra superior en Inicio (guía §4). */
export function StatusBar() {
  const { user } = useAuth()
  const [saved, setSaved] = useState('')
  const [status, setStatus] = useState('')
  const [updatedAt, setUpdatedAt] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!user) return
    getProfile(user.id)
      .then(({ profile }) => {
        setSaved(profile.status ?? '')
        setStatus(profile.status ?? '')
        setUpdatedAt(profile.status_updated_at)
      })
      .catch(() => undefined)
  }, [user])

  if (!user) return null

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setError('')
    try {
      const { profile } = await updateStatus(status)
      setSaved(profile.status ?? '')
      setStatus(profile.status ?? '')
      setUpdatedAt(profile.status_updated_at)
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="statusbar">
      <form className="statusbar__inner" onSubmit={handleSubmit}>
        <Avatar src={user.avatar_url} name={user.first_name} size={24} decorative />
        <input
          className="statusbar__input"
          aria-label="Tu estado"
          placeholder="¿Cómo va todo?"
          maxLength={160}
          value={status}
          onChange={(event) => setStatus(event.target.value)}
        />
        {status !== saved && (
          <button type="submit" className="button" disabled={saving}>
            {saving ? 'Guardando…' : 'Cambiar'}
          </button>
        )}
        {updatedAt && saved && (
          <time className="muted" dateTime={updatedAt}>
            {timeAgo(updatedAt)}
          </time>
        )}
        {error && (
          <span className="field__error" role="alert">
            {error}
          </span>
        )}
        <Link to="/subir-fotos" className="button statusbar__upload">
          <span aria-hidden="true">⬆</span> Subir fotos
        </Link>
      </form>
    </div>
  )
}
