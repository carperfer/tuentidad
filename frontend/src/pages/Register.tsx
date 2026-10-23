import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { register } from '../api/auth'
import { errorMessage, fieldErrors } from '../api/client'
import { getInvitation, type PublicInvitation } from '../api/invitations'
import { useAuth } from '../auth/context'
import { Alert } from '../components/Alert'
import { Field } from '../components/Field'

export function Register() {
  const { token = '' } = useParams()
  const { setUser } = useAuth()
  const navigate = useNavigate()
  const [invitation, setInvitation] = useState<PublicInvitation | null>(null)
  const [invalid, setInvalid] = useState('')
  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    birthdate: '',
    password: '',
    password_confirm: '',
    accept_terms: false,
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState('')
  const [sending, setSending] = useState(false)

  useEffect(() => {
    getInvitation(token)
      .then(setInvitation)
      .catch((caught: unknown) => setInvalid(errorMessage(caught)))
  }, [token])

  function update(field: keyof typeof form, value: string | boolean) {
    setForm((current) => ({ ...current, [field]: value }))
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSending(true)
    setErrors({})
    setError('')
    try {
      setUser(await register({ token, ...form }))
      navigate('/inicio', { replace: true })
    } catch (caught) {
      const fields = fieldErrors(caught)
      setErrors(fields)
      if (Object.keys(fields).length === 0) setError(errorMessage(caught))
      setSending(false)
    }
  }

  if (invalid) {
    return (
      <section className="box narrow">
        <h1>Invitación no válida</h1>
        <Alert>{invalid}</Alert>
        <p>Pide a tu amigo que te vuelva a invitar.</p>
        <p>
          <Link to="/">Ir a la portada</Link>
        </p>
      </section>
    )
  }

  if (!invitation) return <p className="status">Comprobando la invitación…</p>

  return (
    <section className="box narrow">
      <h1>Crea tu cuenta</h1>
      <p>
        {invitation.inviter && invitation.welcome ? (
          <>
            <strong>{invitation.inviter}</strong> ha aceptado tu solicitud de amistad: en cuanto crees tu cuenta, seréis
            amigos.{' '}
          </>
        ) : invitation.inviter ? (
          <>
            <strong>{invitation.inviter}</strong> te ha invitado a tuentidad.{' '}
          </>
        ) : null}
        Tu cuenta usará el email <strong>{invitation.email}</strong>.
      </p>
      <form onSubmit={handleSubmit} noValidate>
        {error && <Alert>{error}</Alert>}
        <Field
          label="Nombre"
          autoComplete="given-name"
          value={form.first_name}
          onChange={(e) => update('first_name', e.target.value)}
          error={errors.first_name}
          required
        />
        <Field
          label="Apellidos"
          autoComplete="family-name"
          value={form.last_name}
          onChange={(e) => update('last_name', e.target.value)}
          error={errors.last_name}
          required
        />
        <Field
          label="Fecha de nacimiento"
          type="date"
          autoComplete="bday"
          value={form.birthdate}
          onChange={(e) => update('birthdate', e.target.value)}
          error={errors.birthdate}
          required
        />
        <Field
          label="Contraseña"
          type="password"
          autoComplete="new-password"
          value={form.password}
          onChange={(e) => update('password', e.target.value)}
          error={errors.password}
          required
        />
        <Field
          label="Repite la contraseña"
          type="password"
          autoComplete="new-password"
          value={form.password_confirm}
          onChange={(e) => update('password_confirm', e.target.value)}
          error={errors.password_confirm}
          required
        />
        <label className="checkbox">
          <input
            type="checkbox"
            checked={form.accept_terms}
            onChange={(e) => update('accept_terms', e.target.checked)}
          />
          <span>
            Acepto las <Link to="/condiciones">condiciones de uso</Link> y la{' '}
            <Link to="/privacidad">política de privacidad</Link>
          </span>
        </label>
        {errors.accept_terms && <p className="field__error">{errors.accept_terms}</p>}
        <button type="submit" className="button" disabled={sending}>
          {sending ? 'Creando cuenta…' : 'Crear cuenta'}
        </button>
      </form>
    </section>
  )
}
