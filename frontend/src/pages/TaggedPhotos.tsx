import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { errorMessage } from '../api/client'
import { listTaggedPhotos, type Photo } from '../api/photos'
import { getProfile, type Profile } from '../api/profile'
import { Alert } from '../components/Alert'
import { OwnerCard } from '../components/ProfileSidebar'
import { TaggedPhotosModule } from '../components/TaggedPhotosModule'
import { Columns } from '../components/ui/Columns'

/** Todas las fotos en las que sale una persona ("Ver todas"). */
export function TaggedPhotos() {
  const { id } = useParams()
  const userId = Number(id)
  const valid = Number.isInteger(userId) && userId > 0
  const [profile, setProfile] = useState<Profile | null>(null)
  const [data, setData] = useState<{ photos: Photo[]; total: number } | null>(null)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!valid) return
    Promise.all([getProfile(userId), listTaggedPhotos(userId)])
      .then(([{ profile: result }, tagged]) => {
        setProfile(result)
        setData(tagged)
      })
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [valid, userId])

  if (!valid)
    return (
      <Columns>
        <Alert>No se ha encontrado el usuario.</Alert>
      </Columns>
    )

  return (
    <Columns left={<OwnerCard userId={userId} profile={profile} />}>
      {error ? (
        <Alert>{error}</Alert>
      ) : profile === null || data === null ? (
        <p className="status">Cargando fotos…</p>
      ) : (
        <TaggedPhotosModule as="h1" name={profile.is_owner ? null : profile.first_name} photos={data.photos} total={data.total} />
      )}
    </Columns>
  )
}
