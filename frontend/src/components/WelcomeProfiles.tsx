import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { errorMessage, fieldErrors } from '../api/client'
import { listWelcomeProfiles, requestFriendship, type WelcomeProfile } from '../api/welcome'
import { Alert } from './Alert'
import { Field } from './Field'

/** Perfiles de demostración a los que cualquiera puede pedir amistad para entrar. */
export function WelcomeProfiles() {
  const [profiles, setProfiles] = useState<WelcomeProfile[]>([])

  useEffect(() => {
    listWelcomeProfiles()
      .then(setProfiles)
      .catch(() => setProfiles([]))
  }, [])

  if (profiles.length === 0) return null

  return (
    <section className="box">
      <h2 className="box__title">¿Aún no conoces a nadie dentro?</h2>
      <p>Pide amistad a uno de nuestros perfiles de bienvenida y te enviaremos un enlace para crear tu cuenta.</p>
      <div className="welcome-profiles">
        {profiles.map((profile) => (
          <WelcomeProfileCard key={profile.id} profile={profile} />
        ))}
      </div>
    </section>
  )
}

function WelcomeProfileCard({ profile }: { profile: WelcomeProfile }) {
  const [open, setOpen] = useState(false)
  const [email, setEmail] = useState('')
  const [acceptPrivacy, setAcceptPrivacy] = useState(false)
  const [website, setWebsite] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState('')
  const [done, setDone] = useState('')
  const [sending, setSending] = useState(false)
  const name = `${profile.first_name} ${profile.last_name}`

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSending(true)
    setErrors({})
    setError('')
    try {
      setDone(await requestFriendship(profile.id, { email, accept_privacy: acceptPrivacy, website }))
    } catch (caught) {
      const fields = fieldErrors(caught)
      setErrors(fields)
      if (Object.keys(fields).length === 0) setError(errorMessage(caught))
    } finally {
      setSending(false)
    }
  }

  return (
    <article className="welcome-profile" aria-label={name}>
      <img className="welcome-profile__avatar" src={profile.avatar} alt="" width={72} height={72} />
      <div className="welcome-profile__body">
        <h3>{name}</h3>
        <span className="badge">Perfil de demostración</span>
        <p>{profile.bio}</p>

        {done ? (
          <Alert kind="ok">{done}</Alert>
        ) : open ? (
          <form onSubmit={handleSubmit} noValidate>
            {error && <Alert>{error}</Alert>}
            <Field
              label="Tu email"
              type="email"
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              error={errors.email}
              required
            />
            {/* Campo trampa para bots: oculto a las personas y a los lectores de pantalla */}
            <div className="honeypot" aria-hidden="true">
              <label>
                Web
                <input
                  type="text"
                  tabIndex={-1}
                  autoComplete="off"
                  value={website}
                  onChange={(e) => setWebsite(e.target.value)}
                />
              </label>
            </div>
            <label className="checkbox">
              <input type="checkbox" checked={acceptPrivacy} onChange={(e) => setAcceptPrivacy(e.target.checked)} />
              <span>
                He leído y acepto la <Link to="/privacidad">política de privacidad</Link>
              </span>
            </label>
            {errors.accept_privacy && <p className="field__error">{errors.accept_privacy}</p>}
            <button type="submit" className="button" disabled={sending}>
              {sending ? 'Enviando…' : 'Enviar solicitud'}
            </button>
          </form>
        ) : (
          <button type="button" className="button" onClick={() => setOpen(true)}>
            Pedir amistad a {profile.first_name}
          </button>
        )}
      </div>
    </article>
  )
}
