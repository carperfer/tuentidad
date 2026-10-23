import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { forgotPassword } from '../api/auth'
import { errorMessage, fieldErrors } from '../api/client'
import { Alert } from '../components/Alert'
import { Field } from '../components/Field'

export function ForgotPassword() {
  const [email, setEmail] = useState('')
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
      setNotice(await forgotPassword(email))
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
      <h1>¿Has olvidado tu contraseña?</h1>
      {notice ? (
        <Alert kind="ok">{notice}</Alert>
      ) : (
        <form onSubmit={handleSubmit} noValidate>
          <p>Escribe el email de tu cuenta y te enviaremos un enlace para elegir una contraseña nueva.</p>
          {error && <Alert>{error}</Alert>}
          <Field
            label="Email"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            error={errors.email}
            required
          />
          <button type="submit" className="button" disabled={sending}>
            {sending ? 'Enviando…' : 'Enviar enlace'}
          </button>
        </form>
      )}
      <p className="form__footer">
        <Link to="/">Volver a la portada</Link>
      </p>
    </section>
  )
}
