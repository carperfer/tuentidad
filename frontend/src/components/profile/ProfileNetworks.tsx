import { Link } from 'react-router'
import type { NetworkKind, Profile } from '../../api/profile'
import { yearsLabel } from '../../lib/networks'
import { Module } from '../ui/Module'

const GROUPS: { kind: NetworkKind; title: string }[] = [
  { kind: 'university', title: 'Estudios universitarios' },
  { kind: 'work', title: 'Trayectoria profesional' },
  { kind: 'school', title: 'Colegios' },
]

/** «Redes»: estudios, trayectoria profesional, colegios y enlaces a redes sociales (guía §6.1). */
export function ProfileNetworks({ profile }: { profile: Profile }) {
  const networks = profile.networks
  const links = profile.links ?? []
  const groups = GROUPS.filter((group) => (networks?.[group.kind].length ?? 0) > 0)

  if (groups.length === 0 && links.length === 0) {
    if (!profile.is_owner || networks === undefined) return null
    return (
      <Module title="Redes">
        <p className="status">
          <Link to="/perfil/editar#redes">Añade dónde has estudiado y trabajado y tus otras redes</Link>
        </p>
      </Module>
    )
  }

  return (
    <Module title="Redes">
      <dl className="networks">
        {groups.map((group) => (
          <div key={group.kind} className="networks__group">
            <dt>{group.title}</dt>
            {networks?.[group.kind].map((item, index) => (
              <dd key={index}>
                <span className="networks__name">{item.name}</span>
                {item.detail && <span className="networks__detail">{item.detail}</span>}
                {yearsLabel(item) && <span className="muted"> {yearsLabel(item)}</span>}
              </dd>
            ))}
          </div>
        ))}
        {links.length > 0 && (
          <div className="networks__group">
            <dt>En otras redes</dt>
            <dd>
              <ul className="networks__links">
                {links.map((link) => (
                  <li key={link.url}>
                    <a href={link.url} target="_blank" rel="me noopener noreferrer">
                      {link.network}
                    </a>
                  </li>
                ))}
              </ul>
            </dd>
          </div>
        )}
      </dl>
    </Module>
  )
}
