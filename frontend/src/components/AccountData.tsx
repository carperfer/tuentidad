import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router'
import { ACCOUNT_EXPORT_URL, deleteAccount } from '../api/auth'
import { errorMessage, fieldErrors } from '../api/client'
import { useAuth } from '../auth/context'
import { Alert } from './Alert'
import { Field } from './Field'
import { Module } from './ui/Module'

/** Derechos sobre los datos (RGPD): descargarlos y borrar la cuenta. */
export function AccountData() {
  const { setUser } = useAuth()
  const navigate = useNavigate()
  const [confirming, setConfirming] = useState(false)
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState('')
  const [deleting, setDeleting] = useState(false)

  async function handleDelete(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setDeleting(true)
    setErrors({})
    setError('')
    try {
      const { message } = await deleteAccount(password)
      setUser(null)
      navigate('/', { replace: true, state: { notice: message } })
    } catch (caught) {
      const fields = fieldErrors(caught)
      setErrors(fields)
      if (!fields.password) setError(errorMessage(caught))
      setDeleting(false)
    }
  }

  return (
    <Module title="Tus datos">
      <p>
        Descarga una copia de todo lo que tienes en tuentidad: perfil, amigos, álbumes, comentarios, etiquetas e invitaciones.
      </p>
      <p>
        <a className="button" href={ACCOUNT_EXPORT_URL} download>
          <span aria-hidden="true">⬇</span> Descargar mis datos
        </a>
      </p>

      <div className="danger-zone">
        {!confirming ? (
          <button type="button" className="link-button" onClick={() => setConfirming(true)}>
            Borrar mi cuenta
          </button>
        ) : (
          <form onSubmit={handleDelete} noValidate>
            <p>
              <strong>
                Se borrarán para siempre tu perfil, tus amigos, tus fotos y álbumes, tus comentarios y tus etiquetas.
              </strong>{' '}
              No se puede deshacer. Si quieres conservar algo, descarga antes tus datos.
            </p>
            {error && <Alert>{error}</Alert>}
            <Field
              label="Escribe tu contraseña para confirmar"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              error={errors.password}
            />
            <button type="submit" className="button button--danger" disabled={deleting || password === ''}>
              {deleting ? 'Borrando…' : 'Borrar mi cuenta para siempre'}
            </button>{' '}
            <button
              type="button"
              className="link-button"
              onClick={() => {
                setConfirming(false)
                setPassword('')
                setErrors({})
              }}
            >
              Cancelar
            </button>
          </form>
        )}
      </div>
    </Module>
  )
}
