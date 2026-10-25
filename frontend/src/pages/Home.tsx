import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../api/client'
import { listFriendActivity, listFriendRequests, type FriendActivity, type FriendRequests } from '../api/friends'
import { getDashboardSummary, type DashboardSummary } from '../api/profile'
import { ActivityFeed } from '../components/ActivityFeed'
import { Alert } from '../components/Alert'
import { InviteFriends } from '../components/InviteFriends'
import { RequestsModule } from '../components/people/RequestsModule'
import { StatusBar } from '../components/StatusBar'
import { UserCard } from '../components/UserCard'
import { Columns } from '../components/ui/Columns'
import { Module } from '../components/ui/Module'

/** Inicio (guía §5.1): tarjeta e invitaciones, novedades y solicitudes. */
export function Home() {
  const [summary, setSummary] = useState<DashboardSummary | null>(null)
  const [activity, setActivity] = useState<FriendActivity[] | null>(null)
  const [requests, setRequests] = useState<FriendRequests>({ incoming: [], outgoing: [] })
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  const load = useCallback(() => {
    Promise.all([getDashboardSummary(), listFriendActivity(), listFriendRequests()])
      .then(([summaryData, activityData, requestData]) => {
        setSummary(summaryData)
        setActivity(activityData.activity)
        setRequests(requestData)
      })
      .catch((caught: unknown) => setError(errorMessage(caught)))
  }, [])

  useEffect(load, [load])

  function handleRequestChange(message: string) {
    setNotice(message)
    load()
  }

  return (
    <>
      <StatusBar />
      <Columns
        left={
          <>
            <UserCard friendsCount={summary?.friends_count} pendingRequests={requests.incoming.length} />
            <InviteFriends />
          </>
        }
        right={
          <>
            <Module title="Añadir amigos">
              <p>¿Tus amigos ya están en tuentidad?</p>
              <Link to="/gente" className="button">
                Buscar amigos
              </Link>
            </Module>
            <RequestsModule kind="incoming" requests={requests.incoming} onChange={handleRequestChange} showEmpty={false} />
          </>
        }
      >
        {error && <Alert>{error}</Alert>}
        {notice && <Alert kind="ok">{notice}</Alert>}
        <ActivityFeed activity={activity} />
      </Columns>
    </>
  )
}
