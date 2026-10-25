import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { getProfile, type Profile } from '../api/profile'
import { Avatar } from './ui/Avatar'

/** Foto de perfil grande con marco (guía §6.1, ProfilePhoto). */
export function ProfilePhoto({
  profile,
  className = '',
}: {
  profile: Pick<Profile, 'first_name' | 'last_name' | 'avatar_url'>
  className?: string
}) {
  return (
    <div className={`profile-photo ${className}`.trim()}>
      <Avatar src={profile.avatar_url} name={`${profile.first_name} ${profile.last_name}`} size={200} />
    </div>
  )
}

/**
 * Columna izquierda de las páginas que pertenecen a una persona (fotos, amigos):
 * su foto y enlaces a su perfil.
 */
export function OwnerCard({ userId, profile: known }: { userId: number; profile?: Profile | null }) {
  const [fetched, setFetched] = useState<Profile | null>(null)
  const profile = known ?? fetched

  useEffect(() => {
    // Si la página ya ha cargado el perfil, no se vuelve a pedir
    if (known !== undefined) return
    getProfile(userId)
      .then(({ profile: result }) => setFetched(result))
      .catch(() => setFetched(null))
  }, [userId, known])

  if (profile === null) return null

  return (
    <>
      <ProfilePhoto profile={profile} />
      <section className="module" aria-label={`${profile.first_name} ${profile.last_name}`}>
        <div className="module__body">
          <p>
            <Link to={`/perfil/${profile.id}`} className="person-name">
              {profile.first_name} {profile.last_name}
            </Link>
          </p>
          <ul className="notifications">
            <li>
              <Link to={`/perfil/${profile.id}`}>{profile.is_owner ? 'Mi perfil' : 'Ver su perfil'}</Link>
            </li>
            <li>
              <Link to={`/perfil/${profile.id}/fotos`}>{profile.is_owner ? 'Mis fotos' : 'Sus fotos'}</Link>
            </li>
            <li>
              <Link to={`/perfil/${profile.id}/amigos`}>{profile.is_owner ? 'Mis amigos' : 'Sus amigos'}</Link>
            </li>
          </ul>
        </div>
      </section>
    </>
  )
}
