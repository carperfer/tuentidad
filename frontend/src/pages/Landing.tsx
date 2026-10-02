import { useState, type FormEvent } from 'react'
import { Link, useLocation, useNavigate } from 'react-router'
import { errorMessage, fieldErrors } from '../api/client'
import { useAuth } from '../auth/context'
import { Alert } from '../components/Alert'
import { Field } from '../components/Field'
import { WelcomeProfiles } from '../components/WelcomeProfiles'

export function Landing() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState('')
  const [sending, setSending] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSending(true)
    setErrors({})
    setError('')
    try {
      await login(email, password, remember)
      const from = (location.state as { from?: string } | null)?.from
      navigate(from ?? '/inicio', { replace: true })
    } catch (caught) {
      setErrors(fieldErrors(caught))
      setError(errorMessage(caught))
      setSending(false)
    }
  }

  return (
    <div className="landing">
      <div className="landing__main">
        <section className="box landing__intro">
          <h1>Bienvenido a tuentidad</h1>
          <p>
            La red social privada para estar en contacto con tus amigos de verdad: comparte fotos, organiza quedadas y
            habla con tu gente.
          </p>
          <p>Solo se entra por invitación. Si un amigo ya está dentro, pídele que te invite.</p>
        </section>
        <WelcomeProfiles />
      </div>

      <aside className="box">
        <h2 className="box__title">Entra en tuentidad</h2>
        <form onSubmit={handleSubmit} noValidate>
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
          <Field
            label="Contraseña"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            error={errors.password}
            required
          />
          <label className="checkbox">
            <input type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)} />
            Recordarme en este equipo
          </label>
          <button type="submit" className="button" disabled={sending}>
            {sending ? 'Entrando…' : 'Entrar'}
          </button>
          <p className="form__footer">
            <Link to="/recuperar-contrasena">¿Has olvidado tu contraseña?</Link>
          </p>
        </form>
      </aside>
    </div>
  )
}
