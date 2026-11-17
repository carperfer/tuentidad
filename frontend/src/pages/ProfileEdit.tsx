import { useEffect, useState, type ChangeEvent, type FormEvent } from 'react'
import { Link } from 'react-router'
import { errorMessage, fieldErrors } from '../api/client'
import { getProfile, updateProfile, uploadAvatar, type Profile, type ProfileData, type Visibility } from '../api/profile'
import { useAuth } from '../auth/context'
import { AccountData } from '../components/AccountData'
import { Alert } from '../components/Alert'
import { Field } from '../components/Field'
import { ProfilePhoto } from '../components/ProfileSidebar'
import { Columns } from '../components/ui/Columns'
import { Module } from '../components/ui/Module'

const VISIBILITY_LABELS: Record<Visibility, string> = {
  public: 'Todos los usuarios',
  friends: 'Solo mis amigos',
  private: 'Solo yo',
}

const FIELD_LABELS = {
  birthdate: 'Fecha de nacimiento',
  bio: 'Sobre mí',
  status: 'Estado',
  avatar: 'Foto de perfil',
} as const

/** Edición del perfil propio («Mi cuenta»). */
export function ProfileEdit() {
  const { user, setUser } = useAuth()
  const [profile, setProfile] = useState<Profile | null>(null)
  const [form, setForm] = useState<ProfileData | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [saving, setSaving] = useState(false)
  const [uploading, setUploading] = useState(false)

  useEffect(() => {
    if (!user) return
    getProfile(user.id)
      .then(({ profile: result }) => {
        setProfile(result)
        setForm({
          first_name: result.first_name,
          last_name: result.last_name,
          birthdate: result.birthdate ?? '',
          bio: result.bio ?? '',
          status: result.status ?? '',
          birthdate_visibility: result.visibility?.birthdate ?? 'friends',
          bio_visibility: result.visibility?.bio ?? 'public',
          status_visibility: result.visibility?.status ?? 'public',
          avatar_visibility: result.visibility?.avatar ?? 'public',
        })
      })
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [user])

  function setValue<K extends keyof ProfileData>(key: K, value: ProfileData[K]) {
    setForm((current) => current && { ...current, [key]: value })
  }

  async function handleSave(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!form || !user) return
    setSaving(true)
    setErrors({})
    setError('')
    setNotice('')
    try {
      const result = await updateProfile(form)
      setProfile(result.profile)
      setUser({ ...user, first_name: result.profile.first_name, last_name: result.profile.last_name })
      setNotice('Tu perfil se ha actualizado.')
    } catch (caught) {
      setErrors(fieldErrors(caught))
      setError(errorMessage(caught))
    } finally {
      setSaving(false)
    }
  }

  async function handleAvatar(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    if (!file || !user) return
    setUploading(true)
    setErrors({})
    setError('')
    setNotice('')
    try {
      const result = await uploadAvatar(file)
      // La URL de la foto no cambia: se añade una marca para que el navegador la vuelva a pedir
      const avatarUrl = result.profile.avatar_url && `${result.profile.avatar_url}?v=${Date.now()}`
      setProfile({ ...result.profile, avatar_url: avatarUrl })
      setUser({ ...user, avatar_url: avatarUrl })
      setNotice('Tu foto de perfil se ha actualizado.')
    } catch (caught) {
      setErrors(fieldErrors(caught))
      setError(errorMessage(caught))
    } finally {
      setUploading(false)
      event.target.value = ''
    }
  }

  if (!profile || !form) {
    return <Columns>{error ? <Alert>{error}</Alert> : <p className="status">Cargando perfil…</p>}</Columns>
  }

  return (
    <Columns
      left={
        <>
          <ProfilePhoto profile={profile} />
          <Module title="Foto de perfil">
            <label htmlFor="avatar-upload">JPG, PNG o WebP; máximo 2 MB</label>
            <input
              id="avatar-upload"
              className="file-input"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              onChange={handleAvatar}
              disabled={uploading}
            />
            {errors.avatar && <p className="field__error">{errors.avatar}</p>}
            {uploading && <p className="status">Subiendo foto…</p>}
          </Module>
        </>
      }
    >
      <Module title="Edita tu perfil" as="h1" action={<Link to={`/perfil/${profile.id}`}>Ver mi perfil</Link>}>
        {notice && <Alert kind="ok">{notice}</Alert>}
        {error && <Alert>{error}</Alert>}
        <form onSubmit={handleSave} noValidate>
          <Field
            label="Nombre"
            value={form.first_name}
            onChange={(event) => setValue('first_name', event.target.value)}
            error={errors.first_name}
            required
          />
          <Field
            label="Apellidos"
            value={form.last_name}
            onChange={(event) => setValue('last_name', event.target.value)}
            error={errors.last_name}
            required
          />
          <Field
            label="Fecha de nacimiento"
            type="date"
            value={form.birthdate}
            onChange={(event) => setValue('birthdate', event.target.value)}
            error={errors.birthdate}
            required
          />
          <Field
            label="Sobre mí"
            value={form.bio}
            onChange={(event) => setValue('bio', event.target.value)}
            error={errors.bio}
            maxLength={500}
          />
          <Field
            label="Estado"
            value={form.status}
            onChange={(event) => setValue('status', event.target.value)}
            error={errors.status}
            maxLength={160}
          />
          <fieldset className="privacy-fields">
            <legend>Quién puede ver cada dato</legend>
            {(['birthdate', 'bio', 'status', 'avatar'] as const).map((field) => (
              <label className="privacy-fields__row" key={field}>
                <span>{FIELD_LABELS[field]}</span>
                <select
                  value={form[`${field}_visibility`]}
                  onChange={(event) => setValue(`${field}_visibility`, event.target.value as Visibility)}
                  aria-label={`Visibilidad: ${FIELD_LABELS[field]}`}
                >
                  {Object.entries(VISIBILITY_LABELS).map(([value, label]) => (
                    <option key={value} value={value}>
                      {label}
                    </option>
                  ))}
                </select>
              </label>
            ))}
          </fieldset>
          <button type="submit" className="button button--primary" disabled={saving}>
            {saving ? 'Guardando…' : 'Guardar cambios'}
          </button>
        </form>
      </Module>
      <AccountData />
    </Columns>
  )
}
