import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { errorMessage } from '../api/client'
import { listUserFriends, type FriendSample } from '../api/friends'
import { Alert } from '../components/Alert'
import { FriendsGrid } from '../components/people/FriendsGrid'
import { OwnerCard } from '../components/ProfileSidebar'
import { Columns } from '../components/ui/Columns'

/** Lista completa de amigos de una persona ("Ver todos"). */
export function Friends() {
  const { id } = useParams()
  const userId = Number(id)
  const [data, setData] = useState<FriendSample | null>(null)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!Number.isInteger(userId) || userId < 1) return
    listUserFriends(userId)
      .then(setData)
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [userId])

  return (
    <Columns left={<OwnerCard userId={userId} />}>
      {error ? (
        <Alert>{error}</Alert>
      ) : data === null ? (
        <p className="status">Cargando amigos…</p>
      ) : (
        <FriendsGrid as="h1" title={`Amigos (${data.total})`} people={data.friends} total={data.total} empty="Todavía no hay amigos." />
      )}
    </Columns>
  )
}
