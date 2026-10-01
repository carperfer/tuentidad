import { useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router'
import { resetPassword } from '../api/auth'
import { errorMessage, fieldErrors } from '../api/client'
import { Alert } from '../components/Alert'
import { Field } from '../components/Field'

export function ResetPassword() {
  const { token = '' } = useParams()
  const [password, setPassword] = useState('')
  const [passwordConfirm, setPasswordConfirm] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [sending, setSending] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSending(true)
    setErrors({})
    setError('')
    try {
      setNotice(await resetPassword(token, password, passwordConfirm))
    } catch (caught) {
      const fields = fieldErrors(caught)
      setErrors(fields)
      if (Object.keys(fields).length === 0) setError(errorMessage(caught))
    } finally {
      setSending(false)
    }
  }

  return (
    <section className="box narrow">
      <h1>Elige una contraseña nueva</h1>
      {notice ? (
        <>
          <Alert kind="ok">{notice}</Alert>
          <p>
            <Link to="/">Iniciar sesión</Link>
          </p>
        </>
      ) : (
        <form onSubmit={handleSubmit} noValidate>
          {error && <Alert>{error}</Alert>}
          <Field
            label="Contraseña nueva"
            type="password"
            autoComplete="new-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            error={errors.password}
            required
          />
          <Field
            label="Repite la contraseña"
            type="password"
            autoComplete="new-password"
            value={passwordConfirm}
            onChange={(e) => setPasswordConfirm(e.target.value)}
            error={errors.password_confirm}
            required
          />
          <button type="submit" className="button" disabled={sending}>
            {sending ? 'Guardando…' : 'Cambiar contraseña'}
          </button>
          {error && (
            <p className="form__footer">
              <Link to="/recuperar-contrasena">Pedir un enlace nuevo</Link>
            </p>
          )}
        </form>
      )}
    </section>
  )
}
