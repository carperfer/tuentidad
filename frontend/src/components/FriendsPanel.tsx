import { useCallback, useEffect, useState, type FormEvent } from "react";
import { errorMessage } from "../api/client";
import {
  acceptFriendRequest,
  cancelFriendRequest,
  listFriendActivity,
  listFriendRequests,
  listFriends,
  rejectFriendRequest,
  removeFriend,
  searchPeople,
  sendFriendRequest,
  type FriendRequest,
  type FriendRequests,
  type FriendActivity,
  type Person,
} from "../api/friends";
import { Alert } from "./Alert";
import { Field } from "./Field";
import { Link } from "react-router";

const RELATIONSHIP_LABEL: Record<
  NonNullable<Person["relationship"]>,
  string
> = {
  none: "Añadir amigo",
  incoming: "Solicitud recibida",
  outgoing: "Solicitud enviada",
  friend: "Ya sois amigos",
};

export function FriendsPanel() {
  const [friends, setFriends] = useState<Person[]>([]);
  const [activity, setActivity] = useState<FriendActivity[]>([]);
  const [requests, setRequests] = useState<FriendRequests>({
    incoming: [],
    outgoing: [],
  });
  const [query, setQuery] = useState("");
  const [results, setResults] = useState<Person[] | null>(null);
  const [searched, setSearched] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const load = useCallback(async () => {
    const [friendData, requestData, activityData] = await Promise.all([
      listFriends(),
      listFriendRequests(),
      listFriendActivity(),
    ]);
    setFriends(friendData.friends);
    setRequests(requestData);
    setActivity(activityData.activity);
  }, []);

  useEffect(() => {
    let active = true;
    Promise.all([listFriends(), listFriendRequests(), listFriendActivity()])
      .then(([friendData, requestData, activityData]) => {
        if (!active) return;
        setFriends(friendData.friends);
        setRequests(requestData);
        setActivity(activityData.activity);
      })
      .catch((caught: unknown) => {
        if (active) setError(errorMessage(caught));
      });

    return () => {
      active = false;
    };
  }, []);

  async function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setNotice("");
    setSearched(true);
    if (query.trim().length < 2) {
      setResults([]);
      return;
    }
    setBusy(true);
    try {
      setResults((await searchPeople(query.trim())).users);
    } catch (caught) {
      setError(errorMessage(caught));
    } finally {
      setBusy(false);
    }
  }

  async function perform(action: () => Promise<{ message: string }>) {
    setError("");
    setNotice("");
    setBusy(true);
    try {
      const result = await action();
      setNotice(result.message);
      await load();
      if (searched && query.trim().length >= 2) {
        setResults((await searchPeople(query.trim())).users);
      }
    } catch (caught) {
      setError(errorMessage(caught));
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="box friends-panel" aria-labelledby="friends-title">
      <h2 className="box__title" id="friends-title">
        Amigos
      </h2>
      {notice && <Alert kind="ok">{notice}</Alert>}
      {error && <Alert>{error}</Alert>}

      <form className="friends-search" onSubmit={handleSearch} role="search">
        <Field
          label="Buscar personas"
          placeholder="Nombre o apellido"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          autoComplete="off"
        />
        <button type="submit" className="button" disabled={busy}>
          {busy ? "Buscando…" : "Buscar"}
        </button>
      </form>

      {searched && (
        <section
          className="friends-section"
          aria-labelledby="search-results-title"
        >
          <h3 id="search-results-title">Resultados de búsqueda</h3>
          {query.trim().length < 2 ? (
            <p className="status">Escribe al menos dos caracteres.</p>
          ) : results?.length ? (
            <ul className="friends-list">
              {results.map((person) => (
                <li key={person.id}>
                  <Link to={`/perfil/${person.id}`}>
                    {person.first_name} {person.last_name}
                  </Link>
                  {person.relationship === "none" ? (
                    <button
                      type="button"
                      className="link-button"
                      disabled={busy}
                      onClick={() =>
                        perform(() => sendFriendRequest(person.id))
                      }
                    >
                      {RELATIONSHIP_LABEL.none}
                    </button>
                  ) : (
                    <span className="status">
                      {RELATIONSHIP_LABEL[person.relationship ?? "none"]}
                    </span>
                  )}
                </li>
              ))}
            </ul>
          ) : (
            <p className="status">No se han encontrado personas.</p>
          )}
        </section>
      )}

      <RequestList
        title="Solicitudes recibidas"
        empty="No tienes solicitudes pendientes."
        requests={requests.incoming}
        actions={(request) => (
          <>
            <button
              type="button"
              className="button"
              disabled={busy}
              onClick={() => perform(() => acceptFriendRequest(request.id))}
            >
              Aceptar
            </button>
            <button
              type="button"
              className="link-button"
              disabled={busy}
              onClick={() => perform(() => rejectFriendRequest(request.id))}
            >
              Rechazar
            </button>
          </>
        )}
      />

      <RequestList
        title="Solicitudes enviadas"
        empty="No has enviado solicitudes."
        requests={requests.outgoing}
        actions={(request) => (
          <button
            type="button"
            className="link-button"
            disabled={busy}
            onClick={() => perform(() => cancelFriendRequest(request.id))}
          >
            Cancelar
          </button>
        )}
      />

      <section
        className="friends-section"
        aria-labelledby="friends-activity-title"
      >
        <h3 id="friends-activity-title">Novedades de tus amigos</h3>
        {activity.length ? (
          <ul className="friends-activity">
            {activity.map((item) => (
              <li key={item.id}>
                <div>
                  <Link to={`/perfil/${item.id}`}>
                    {item.first_name} {item.last_name}
                  </Link>
                  <p>{item.status}</p>
                </div>
                {item.updated_at && (
                  <time dateTime={item.updated_at}>
                    {new Intl.DateTimeFormat("es").format(
                      new Date(item.updated_at),
                    )}
                  </time>
                )}
              </li>
            ))}
          </ul>
        ) : (
          <p className="status">Todavía no hay novedades.</p>
        )}
      </section>

      <section className="friends-section" aria-labelledby="friends-list-title">
        <h3 id="friends-list-title">
          Tus amigos <span className="status">({friends.length})</span>
        </h3>
        {friends.length ? (
          <ul className="friends-list">
            {friends.map((friend) => (
              <li key={friend.id}>
                <Link to={`/perfil/${friend.id}`}>
                  {friend.first_name} {friend.last_name}
                </Link>
                <button
                  type="button"
                  className="link-button"
                  disabled={busy}
                  onClick={() => perform(() => removeFriend(friend.id))}
                >
                  Eliminar amistad
                </button>
              </li>
            ))}
          </ul>
        ) : (
          <p className="status">Todavía no tienes amigos.</p>
        )}
      </section>
    </section>
  );
}

type RequestListProps = {
  title: string;
  empty: string;
  requests: FriendRequest[];
  actions: (request: FriendRequest) => React.ReactNode;
};

function RequestList({ title, empty, requests, actions }: RequestListProps) {
  return (
    <section className="friends-section" aria-label={title}>
      <h3>
        {title} <span className="status">({requests.length})</span>
      </h3>
      {requests.length ? (
        <ul className="friends-list">
          {requests.map((request) => (
            <li key={request.id}>
              <Link to={`/perfil/${request.user_id}`}>
                {request.first_name} {request.last_name}
              </Link>
              <span className="friends-list__actions">{actions(request)}</span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="status">{empty}</p>
      )}
    </section>
  );
}
