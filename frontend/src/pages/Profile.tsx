import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { errorMessage } from '../api/client'
import {
  acceptFriendRequest,
  cancelFriendRequest,
  listMutualFriends,
  listUserFriends,
  rejectFriendRequest,
  sendFriendRequest,
  type FriendSample,
} from '../api/friends'
import { listAlbums, listRecentPhotos, listTaggedPhotos, type AlbumSummary, type Photo } from '../api/photos'
import { getProfile, type Profile as ProfileView, type Relationship } from '../api/profile'
import { Alert } from '../components/Alert'
import { FriendsGrid } from '../components/people/FriendsGrid'
import { ProfilePhoto } from '../components/ProfileSidebar'
import { TaggedPhotosModule } from '../components/TaggedPhotosModule'
import { Avatar } from '../components/ui/Avatar'
import { Columns } from '../components/ui/Columns'
import { Dropdown } from '../components/ui/Dropdown'
import { Counter, Module } from '../components/ui/Module'
import { formatDay, timeAgo } from '../lib/dates'

const RELATIONSHIP_LABEL: Record<Relationship, string> = {
  self: 'Tú',
  friend: 'Sois amigos',
  incoming: 'Te ha enviado una solicitud de amistad',
  outgoing: 'Solicitud de amistad enviada',
  none: '',
}

type SocialData = {
  photos: { photos: Photo[]; total: number }
  albums: AlbumSummary[]
  friends: FriendSample
}

type ProfileData = {
  profile: ProfileView
  social: SocialData | null
  mutual: FriendSample | null
  tagged: { photos: Photo[]; total: number }
}

/** Perfil y, si la relación lo permite, sus fotos y amigos. */
async function fetchProfileData(profileId: number): Promise<ProfileData> {
  const { profile } = await getProfile(profileId)
  const canSeeSocial = profile.relationship === 'self' || profile.relationship === 'friend'
  const [photos, albums, friends, mutual, tagged] = await Promise.all([
    canSeeSocial ? listRecentPhotos(profileId, 6) : null,
    canSeeSocial ? listAlbums(profileId) : null,
    canSeeSocial ? listUserFriends(profileId, 6) : null,
    profile.relationship === 'self' ? null : listMutualFriends(profileId, 6),
    listTaggedPhotos(profileId, 10),
  ])

  return {
    profile,
    social: photos && albums && friends ? { photos, albums: albums.albums, friends } : null,
    mutual,
    tagged,
  }
}

/** Perfil propio o ajeno (guía §5.2 y §5.3). */
export function Profile() {
  const { id } = useParams()
  const profileId = Number(id)
  const valid = Number.isInteger(profileId) && profileId > 0
  const [profile, setProfile] = useState<ProfileView | null>(null)
  const [social, setSocial] = useState<SocialData | null>(null)
  const [mutual, setMutual] = useState<FriendSample | null>(null)
  const [tagged, setTagged] = useState<ProfileData['tagged'] | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  const apply = useCallback((data: ProfileData) => {
    setProfile(data.profile)
    setSocial(data.social)
    setMutual(data.mutual)
    setTagged(data.tagged)
  }, [])

  useEffect(() => {
    if (!valid) return
    fetchProfileData(profileId)
      .then(apply)
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [valid, profileId, apply])

  async function perform(action: () => Promise<{ message: string }>) {
    setBusy(true)
    setError('')
    setNotice('')
    try {
      setNotice((await action()).message)
      apply(await fetchProfileData(profileId))
    } catch (caught) {
      setError(errorMessage(caught))
    } finally {
      setBusy(false)
    }
  }

  if (!valid) return <Columns><Alert>No se ha encontrado el perfil.</Alert></Columns>
  if (profile === null) {
    return <Columns>{error ? <Alert>{error}</Alert> : <p className="status">Cargando perfil…</p>}</Columns>
  }

  const own = profile.relationship === 'self'
  const requestId = profile.request_id
  const albumCount = social?.albums.length ?? 0

  return (
    <Columns
      left={
        <>
          <ProfilePhoto profile={profile} className="profile-photo--profile" />
          <Module title="Información">
            <dl className="info-list">
              {profile.birthdate && (
                <>
                  <dt>Fecha de nacimiento</dt>
                  <dd>{formatDay(profile.birthdate)}</dd>
                </>
              )}
              {profile.bio && (
                <>
                  <dt>Sobre mí</dt>
                  <dd>{profile.bio}</dd>
                </>
              )}
            </dl>
            {!profile.birthdate && !profile.bio && (
              <p className="status">{own ? 'Todavía no has añadido información.' : 'Sin información visible.'}</p>
            )}
          </Module>
        </>
      }
      right={
        <>
          {social && (
            <Module
              title="Fotos"
              action={
                albumCount > 0 && (
                  <Dropdown
                    label={
                      <>
                        Álbumes <Counter value={albumCount} />
                      </>
                    }
                  >
                    <ul className="dropdown__list">
                      {social.albums.map((album) => (
                        <li key={album.id}>
                          <Link to={`/albumes/${album.id}`}>
                            {album.title} <Counter value={album.photo_count} />
                          </Link>
                        </li>
                      ))}
                    </ul>
                  </Dropdown>
                )
              }
              footer={
                social.photos.total > 0 && (
                  <Link to={`/perfil/${profile.id}/fotos`}>
                    Ver todas <Counter value={social.photos.total} />
                  </Link>
                )
              }
            >
              {social.photos.photos.length === 0 ? (
                <p className="status">{own ? <Link to={`/perfil/${profile.id}/fotos`}>Sube tus primeras fotos</Link> : 'Todavía no hay fotos.'}</p>
              ) : (
                <ul className="photos-sidebar">
                  {social.photos.photos.map((photo, index) => (
                    <li key={photo.id}>
                      <Link to={`/albumes/${photo.album_id}/fotos/${photo.id}`}>
                        <img src={photo.thumb_url} alt={`Foto ${index + 1} de ${profile.first_name}`} loading="lazy" />
                      </Link>
                    </li>
                  ))}
                </ul>
              )}
            </Module>
          )}
          {mutual && (
            <FriendsGrid
              title="Amigos en común"
              people={mutual.friends}
              total={mutual.total}
              moreTo={social ? `/perfil/${profile.id}/amigos` : undefined}
            />
          )}
          {social && (
            <FriendsGrid
              title={own ? 'Mis amigos' : 'Sus amigos'}
              people={social.friends.friends}
              total={social.friends.total}
              moreTo={`/perfil/${profile.id}/amigos`}
              empty={own ? 'Todavía no tienes amigos.' : 'Todavía no tiene amigos.'}
            />
          )}
        </>
      }
    >
      {notice && <Alert kind="ok">{notice}</Alert>}
      {error && <Alert>{error}</Alert>}

      <header className="profile-header">
        <div className="profile-header__photo">
          <Avatar src={profile.avatar_url} name={profile.first_name} size={96} decorative />
        </div>
        <div className="profile-header__text">
        <h1>
          {profile.first_name} {profile.last_name}{' '}
          {RELATIONSHIP_LABEL[profile.relationship] && (
            <small className="muted">{RELATIONSHIP_LABEL[profile.relationship]}</small>
          )}
        </h1>
        <div className="profile-header__actions">
          {own && (
            <>
              <Link to="/perfil/editar" className="button">
                Editar mi perfil
              </Link>
              <Link to={`/perfil/${profile.id}/fotos`} className="button">
                Mis álbumes de fotos <Counter value={albumCount} />
              </Link>
            </>
          )}
          {profile.relationship === 'friend' && (
            <Link to={`/perfil/${profile.id}/fotos`}>
              Ver álbumes de fotos <Counter value={albumCount} />
            </Link>
          )}
          {profile.relationship === 'none' && (
            <button type="button" className="button" disabled={busy} onClick={() => perform(() => sendFriendRequest(profile.id))}>
              Añadir como amigo
            </button>
          )}
          {profile.relationship === 'outgoing' && requestId !== null && (
            <button
              type="button"
              className="link-button"
              disabled={busy}
              onClick={() => perform(() => cancelFriendRequest(requestId))}
            >
              Cancelar solicitud
            </button>
          )}
          {profile.relationship === 'incoming' && requestId !== null && (
            <>
              <button
                type="button"
                className="button"
                disabled={busy}
                onClick={() => perform(() => acceptFriendRequest(requestId))}
              >
                Aceptar solicitud
              </button>
              <button
                type="button"
                className="link-button"
                disabled={busy}
                onClick={() => perform(() => rejectFriendRequest(requestId))}
              >
                Rechazar
              </button>
            </>
          )}
        </div>
        </div>
      </header>

      {profile.status && (
        <p className="status-box">
          {profile.status}{' '}
          {profile.status_updated_at && (
            <time className="muted" dateTime={profile.status_updated_at}>
              {timeAgo(profile.status_updated_at)}
            </time>
          )}
        </p>
      )}

      {tagged && (own || tagged.total > 0) && (
        <TaggedPhotosModule
          name={own ? null : profile.first_name}
          photos={tagged.photos}
          total={tagged.total}
          moreTo={`/perfil/${profile.id}/etiquetas`}
        />
      )}

      {!social && <p className="status">Solo sus amigos pueden ver sus fotos y su lista de amigos.</p>}
    </Columns>
  )
}
