import { useId, useState, type FormEvent } from 'react'
import { errorMessage } from '../api/client'
import { updateSettings, type Digest } from '../api/profile'
import { Alert } from './Alert'
import { Module } from './ui/Module'

const OPTIONS: { value: Digest; label: string }[] = [
  { value: 'none', label: 'Nunca' },
  { value: 'daily', label: 'Cada día' },
  { value: 'weekly', label: 'Cada semana' },
]

/** Frecuencia de los emails de resumen de avisos y mensajes sin leer (desactivados por defecto). */
export function DigestSettings({ initial }: { initial: Digest }) {
  const selectId = useId()
  const [digest, setDigest] = useState<Digest>(initial)
  const [saved, setSaved] = useState<Digest>(initial)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setNotice('')
    setError('')
    try {
      const { profile } = await updateSettings({ digest })
      setSaved(profile.digest ?? digest)
      setNotice(digest === 'none' ? 'Ya no recibirás emails de resumen.' : 'Guardado. El resumen solo llega si hay novedades.')
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Module title="Emails de resumen">
      <p>Te enviamos por email los avisos y mensajes que no hayas leído, solo si hay novedades.</p>
      {notice && <Alert kind="ok">{notice}</Alert>}
      {error && <Alert>{error}</Alert>}
      <form className="inline-form" onSubmit={handleSubmit}>
        <label htmlFor={selectId}>Recibir un resumen</label>{' '}
        <select id={selectId} value={digest} onChange={(event) => setDigest(event.target.value as Digest)}>
          {OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>{' '}
        <button type="submit" className="button" disabled={saving || digest === saved}>
          Guardar
        </button>
      </form>
    </Module>
  )
}
