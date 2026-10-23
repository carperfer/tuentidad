import { useCallback, useEffect, useState, type FormEvent } from "react";
import { errorMessage, fieldErrors } from "../api/client";
import {
  listInvitations,
  sendInvitation,
  type InvitationList,
  type InvitationStatus,
} from "../api/invitations";
import { getDashboardSummary, type DashboardSummary } from "../api/profile";
import { useAuth } from "../auth/context";
import { Alert } from "../components/Alert";
import { Field } from "../components/Field";
import { FriendsPanel } from "../components/FriendsPanel";
import { Link } from "react-router";

const STATUS: Record<InvitationStatus, string> = {
  pending: "Pendiente",
  accepted: "Ya está dentro",
  expired: "Caducada",
};

export function Home() {
  const { user } = useAuth();
  const [data, setData] = useState<InvitationList | null>(null);
  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [email, setEmail] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [sending, setSending] = useState(false);

  const load = useCallback(() => {
    listInvitations()
      .then(setData)
      .catch((caught: unknown) => setError(errorMessage(caught)));
  }, []);

  useEffect(load, [load]);

  useEffect(() => {
    getDashboardSummary()
      .then(setSummary)
      .catch((caught: unknown) => setError(errorMessage(caught)));
  }, []);

  async function handleInvite(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSending(true);
    setErrors({});
    setError("");
    setNotice("");
    try {
      const result = await sendInvitation(email);
      setNotice(result.message);
      setEmail("");
      load();
    } catch (caught) {
      const fields = fieldErrors(caught);
      setErrors(fields);
      if (Object.keys(fields).length === 0) setError(errorMessage(caught));
    } finally {
      setSending(false);
    }
  }

  return (
    <div className="landing">
      <div className="landing__main">
        <section className="box">
          <h1>Hola, {user?.first_name}</h1>
          <p>Este es el resumen de tu actividad en tuentidad.</p>
          {summary && (
            <dl className="activity-summary">
              <div>
                <dt>Amigos</dt>
                <dd>{summary.friends_count}</dd>
              </div>
              <div>
                <dt>Invitaciones enviadas</dt>
                <dd>{summary.invitations_sent}</dd>
              </div>
              <div>
                <dt>Ya se han unido</dt>
                <dd>{summary.invitations_accepted}</dd>
              </div>
            </dl>
          )}
          <p>
            <Link to={`/perfil/${user?.id}`}>Ver y editar mi perfil</Link>
          </p>
        </section>
        <FriendsPanel />
      </div>

      <aside className="box">
        <h2 className="box__title">Invita a tus amigos</h2>
        {data && (
          <p className="status">
            Te quedan <strong>{data.remaining}</strong> invitaciones.
          </p>
        )}
        <form onSubmit={handleInvite} noValidate>
          {notice && <Alert kind="ok">{notice}</Alert>}
          {error && <Alert>{error}</Alert>}
          <Field
            label="Email de tu amigo"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            error={errors.email}
            required
          />
          <button
            type="submit"
            className="button"
            disabled={sending || data?.remaining === 0}
          >
            {sending ? "Enviando…" : "Invitar"}
          </button>
        </form>

        {data && data.invitations.length > 0 && (
          <ul className="invitations">
            {data.invitations.map((invitation) => (
              <li key={invitation.email}>
                <span className="invitations__email">{invitation.email}</span>
                <span className={`badge badge--${invitation.status}`}>
                  {STATUS[invitation.status]}
                </span>
              </li>
            ))}
          </ul>
        )}
      </aside>
    </div>
  );
}
