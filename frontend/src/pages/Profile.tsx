import { useEffect, useState, type ChangeEvent, type FormEvent } from 'react'
import { useParams } from 'react-router'
import { errorMessage, fieldErrors } from '../api/client'
import {
  getProfile,
  updateProfile,
  uploadAvatar,
  type Profile as ProfileDataView,
  type ProfileData,
  type Visibility,
} from '../api/profile'
import { Alert } from '../components/Alert'
import { Field } from '../components/Field'

const VISIBILITY_LABELS: Record<Visibility, string> = {
  public: 'Todos los usuarios',
  friends: 'Solo mis amigos',
  private: 'Solo yo',
}

const EMPTY_PROFILE: ProfileData = {
  first_name: '',
  last_name: '',
  birthdate: '',
  bio: '',
  status: '',
  birthdate_visibility: 'friends',
  bio_visibility: 'public',
  status_visibility: 'public',
  avatar_visibility: 'public',
}

export function Profile() {
  const { id } = useParams()
  const profileId = Number(id)
  const [profile, setProfile] = useState<ProfileDataView | null>(null)
  const [form, setForm] = useState<ProfileData>(EMPTY_PROFILE)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [saving, setSaving] = useState(false)
  const [uploading, setUploading] = useState(false)

  useEffect(() => {
    if (!Number.isInteger(profileId) || profileId < 1) return

    getProfile(profileId)
      .then(({ profile: result }) => {
        setProfile(result)
        if (result.is_owner) {
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
        }
      })
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [profileId])

  function setValue<K extends keyof ProfileData>(key: K, value: ProfileData[K]) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  async function handleSave(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setErrors({})
    setError('')
    setNotice('')
    try {
      const result = await updateProfile(form)
      setProfile(result.profile)
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
    if (!file) return
    setUploading(true)
    setError('')
    setNotice('')
    try {
      const result = await uploadAvatar(file)
      setProfile(result.profile)
      setNotice('Tu foto de perfil se ha actualizado.')
    } catch (caught) {
      setErrors(fieldErrors(caught))
      setError(errorMessage(caught))
    } finally {
      setUploading(false)
      event.target.value = ''
    }
  }

  if (!Number.isInteger(profileId) || profileId < 1) return <Alert>No se ha encontrado el perfil.</Alert>
  if (error && profile === null) return <Alert>{error}</Alert>
  if (profile === null) return <p className="status">Cargando perfil…</p>

  return (
    <div className="profile-layout">
      <section className="box profile-card">
        <h1>{profile.first_name} {profile.last_name}</h1>
        {profile.avatar_url ? (
          <img className="profile-card__avatar" src={profile.avatar_url} alt={`Foto de ${profile.first_name}`} />
        ) : (
          <div className="profile-card__avatar profile-card__avatar--empty" aria-label="Sin foto de perfil" />
        )}
        {profile.status && <p className="profile-card__status">{profile.status}</p>}
        <p>{profile.bio || (profile.is_owner ? 'Todavía no ha añadido información sobre sí.' : 'Sin información visible.')}</p>
        {profile.birthdate && <p>Fecha de nacimiento: {profile.birthdate}</p>}
      </section>

      {profile.is_owner ? (
        <section className="box">
          <h2 className="box__title">Edita tu perfil</h2>
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
                  <span>{field === 'birthdate' ? 'Fecha de nacimiento' : field === 'bio' ? 'Sobre mí' : field === 'status' ? 'Estado' : 'Foto de perfil'}</span>
                  <select
                    value={form[`${field}_visibility`]}
                    onChange={(event) => setValue(`${field}_visibility`, event.target.value as Visibility)}
                    aria-label={`Visibilidad: ${field}`}
                  >
                    {Object.entries(VISIBILITY_LABELS).map(([value, label]) => (
                      <option key={value} value={value}>{label}</option>
                    ))}
                  </select>
                </label>
              ))}
            </fieldset>
            <button type="submit" className="button" disabled={saving}>
              {saving ? 'Guardando…' : 'Guardar cambios'}
            </button>
          </form>
          <div className="avatar-upload">
            <label htmlFor="avatar-upload">Foto de perfil (JPG, PNG o WebP; máximo 2 MB)</label>
            <input id="avatar-upload" type="file" accept="image/jpeg,image/png,image/webp" onChange={handleAvatar} disabled={uploading} />
            {errors.avatar && <p className="field__error">{errors.avatar}</p>}
            {uploading && <p className="status">Subiendo foto…</p>}
          </div>
        </section>
      ) : (
        <section className="box">
          <h2 className="box__title">Perfil</h2>
          <p>Algunos datos solo son visibles para sus amigos.</p>
        </section>
      )}
    </div>
  )
}
