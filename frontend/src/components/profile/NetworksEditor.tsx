import { useId, useState, type FormEvent } from 'react'
import { errorMessage, fieldErrors } from '../../api/client'
import { updateLinks, updateNetworks, type NetworkKind, type Profile, type Visibility } from '../../api/profile'
import { Alert } from '../Alert'
import { Module } from '../ui/Module'

// Etiquetas cortas para que quepan en la fila; en el perfil salen los títulos completos
const KINDS: { value: NetworkKind; label: string }[] = [
  { value: 'university', label: 'Estudios' },
  { value: 'work', label: 'Trabajo' },
  { value: 'school', label: 'Colegio' },
]

const VISIBILITY: { value: Visibility; label: string }[] = [
  { value: 'public', label: 'Todos los usuarios' },
  { value: 'friends', label: 'Solo mis amigos' },
  { value: 'private', label: 'Solo yo' },
]

type Row = { key: number; kind: NetworkKind; name: string; detail: string; start: string; end: string }

let nextKey = 0

function rowsFrom(profile: Profile): Row[] {
  const networks = profile.networks
  if (!networks) return []
  return KINDS.flatMap(({ value }) =>
    networks[value].map((item) => ({
      key: nextKey++,
      kind: value,
      name: item.name,
      detail: item.detail,
      start: item.start_year === null ? '' : String(item.start_year),
      end: item.end_year === null ? '' : String(item.end_year),
    })),
  )
}

/** Edición de «Redes»: estudios y trabajos con sus años, enlaces a redes sociales y su privacidad. */
export function NetworksEditor({ profile, onSaved }: { profile: Profile; onSaved: (profile: Profile) => void }) {
  const id = useId()
  const [rows, setRows] = useState<Row[]>(() => rowsFrom(profile))
  const [networksVisibility, setNetworksVisibility] = useState<Visibility>(profile.visibility?.networks ?? 'friends')
  const [links, setLinks] = useState<string[]>(() => [...(profile.links ?? []).map((link) => link.url), ''])
  const [linksVisibility, setLinksVisibility] = useState<Visibility>(profile.visibility?.links ?? 'friends')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  const update = (key: number, changes: Partial<Row>) => setRows((current) => current.map((row) => (row.key === key ? { ...row, ...changes } : row)))

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setErrors({})
    setNotice('')
    setError('')
    const items = rows.map((row) => ({
      kind: row.kind,
      name: row.name,
      detail: row.detail,
      start_year: row.start === '' ? null : Number(row.start),
      end_year: row.end === '' ? null : Number(row.end),
    }))
    try {
      await updateNetworks(networksVisibility, items)
      const { profile: saved } = await updateLinks(
        linksVisibility,
        links.filter((link) => link.trim() !== ''),
      )
      onSaved(saved)
      setLinks([...(saved.links ?? []).map((link) => link.url), ''])
      setNotice('Redes guardadas.')
    } catch (caught) {
      setErrors(fieldErrors(caught))
      setError(errorMessage(caught))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Module title="Redes" className="networks-editor">
      <span id="redes" />
      {notice && <Alert kind="ok">{notice}</Alert>}
      {error && <Alert>{error}</Alert>}
      <form onSubmit={handleSubmit} noValidate>
        <fieldset className="fieldset">
          <legend>Estudios y trabajos</legend>
          {rows.length === 0 && <p className="status">Añade dónde has estudiado y trabajado.</p>}
          {rows.map((row, index) => (
            <div key={row.key} className="networks-editor__row">
              <select aria-label="Tipo" value={row.kind} onChange={(event) => update(row.key, { kind: event.target.value as NetworkKind })}>
                {KINDS.map((kind) => (
                  <option key={kind.value} value={kind.value}>
                    {kind.label}
                  </option>
                ))}
              </select>
              <input
                aria-label="Nombre (universidad, empresa o colegio)"
                placeholder="Nombre"
                maxLength={120}
                value={row.name}
                onChange={(event) => update(row.key, { name: event.target.value })}
              />
              <input
                aria-label="Detalle (carrera o puesto)"
                placeholder="Carrera o puesto"
                maxLength={150}
                value={row.detail}
                onChange={(event) => update(row.key, { detail: event.target.value })}
              />
              <input
                aria-label="Desde (año)"
                placeholder="Desde"
                type="number"
                min={1940}
                value={row.start}
                onChange={(event) => update(row.key, { start: event.target.value })}
              />
              <input
                aria-label="Hasta (año; vacío si sigues)"
                placeholder="Hasta"
                type="number"
                min={1940}
                value={row.end}
                onChange={(event) => update(row.key, { end: event.target.value })}
              />
              <button
                type="button"
                className="link-button"
                aria-label={`Quitar ${row.name || 'esta fila'}`}
                onClick={() => setRows((current) => current.filter((item) => item.key !== row.key))}
              >
                Quitar
              </button>
              {errors[`items.${index}`] && <p className="field__error">{errors[`items.${index}`]}</p>}
            </div>
          ))}
          <p>
            <button
              type="button"
              className="button"
              onClick={() => setRows((current) => [...current, { key: nextKey++, kind: 'university', name: '', detail: '', start: '', end: '' }])}
            >
              Añadir estudios o trabajo
            </button>
          </p>
          <p className="muted">Deja «Hasta» vacío si sigues ahí.</p>
          <label htmlFor={`${id}-networks`}>Quién puede verlos</label>{' '}
          <select id={`${id}-networks`} value={networksVisibility} onChange={(event) => setNetworksVisibility(event.target.value as Visibility)}>
            {VISIBILITY.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </fieldset>

        <fieldset className="fieldset">
          <legend>En otras redes</legend>
          <p className="muted">Enlaces a tus perfiles de Instagram, X, TikTok, Facebook, LinkedIn, YouTube, Twitch, GitHub, Threads o Bluesky.</p>
          {links.map((link, index) => (
            <div key={index} className="field">
              <input
                type="url"
                aria-label={`Enlace ${index + 1}`}
                placeholder="https://instagram.com/tu_usuario"
                value={link}
                aria-invalid={errors[`links.${index}`] ? true : undefined}
                onChange={(event) => {
                  const value = event.target.value
                  setLinks((current) => {
                    const next = current.map((item, position) => (position === index ? value : item))
                    // Siempre queda un hueco vacío al final para añadir otro (hasta 8)
                    return next.at(-1) !== '' && next.length < 8 ? [...next, ''] : next
                  })
                }}
              />
              {errors[`links.${index}`] && <p className="field__error">{errors[`links.${index}`]}</p>}
            </div>
          ))}
          <label htmlFor={`${id}-links`}>Quién puede verlos</label>{' '}
          <select id={`${id}-links`} value={linksVisibility} onChange={(event) => setLinksVisibility(event.target.value as Visibility)}>
            {VISIBILITY.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </fieldset>
        <button type="submit" className="button button--primary" disabled={saving}>
          {saving ? 'Guardando…' : 'Guardar redes'}
        </button>
      </form>
    </Module>
  )
}
