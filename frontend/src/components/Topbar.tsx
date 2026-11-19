import { useState, type FormEvent } from 'react'
import { Link, NavLink, useNavigate } from 'react-router'
import { useAuth } from '../auth/context'
import { useChat } from '../lib/chat/context'
import { useUnreadCounts } from '../lib/useUnreadCounts'

/**
 * Barra superior azul en el orden del Tuenti original: Inicio, Perfil, Mensajes,
 * Gente, Vídeos, buscador y «Subir fotos»; a la derecha, Mi cuenta y Salir (guía §3).
 */
export function Topbar() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [query, setQuery] = useState('')
  const [menuOpen, setMenuOpen] = useState(false)
  // Los avisos ya no están en la barra (van en la tarjeta de Inicio), pero cuentan en el botón del menú móvil
  const unread = useUnreadCounts()?.total ?? 0
  const messages = useChat()?.state.unread ?? 0

  if (!user) return null

  function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setMenuOpen(false)
    navigate(`/gente?q=${encodeURIComponent(query.trim())}`)
  }

  async function handleLogout() {
    await logout()
    navigate('/')
  }

  const close = () => setMenuOpen(false)

  return (
    <header className="topbar">
      <div className="topbar__inner">
        <Link to="/inicio" className="topbar__logo" onClick={close}>
          tuentidad
        </Link>
        <button
          type="button"
          className="topbar__toggle"
          aria-expanded={menuOpen}
          aria-controls="topbar-menu"
          onClick={() => setMenuOpen((open) => !open)}
        >
          <span aria-hidden="true">☰</span> Menú
          {unread + messages > 0 && (
            <>
              {' '}
              <span className="topbar__badge">
                {unread + messages} <span className="visually-hidden">novedades sin leer</span>
              </span>
            </>
          )}
        </button>
        <div id="topbar-menu" className={`topbar__menu${menuOpen ? ' topbar__menu--open' : ''}`}>
          <nav className="topbar__nav" aria-label="Principal">
            <NavLink to="/inicio" onClick={close}>
              Inicio
            </NavLink>
            <NavLink to={`/perfil/${user.id}`} onClick={close}>
              Perfil
            </NavLink>
            <NavLink to="/mensajes" onClick={close}>
              Mensajes
              {messages > 0 && (
                <>
                  {' '}
                  <span className="topbar__badge">
                    {messages} <span className="visually-hidden">sin leer</span>
                  </span>
                </>
              )}
            </NavLink>
            <NavLink to="/gente" onClick={close}>
              Gente
            </NavLink>
            <NavLink to="/videos" onClick={close}>
              Vídeos
            </NavLink>
          </nav>
          <form className="topbar__search" role="search" onSubmit={handleSearch}>
            <input
              type="search"
              placeholder="Buscar…"
              aria-label="Buscar personas"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
            />
          </form>
          <Link to="/subir-fotos" className="button button--primary topbar__upload" onClick={close}>
            Subir fotos <span aria-hidden="true">⬆</span>
          </Link>
          <nav className="topbar__account" aria-label="Cuenta">
            <NavLink to="/perfil/editar" onClick={close}>
              Mi cuenta
            </NavLink>
            <button type="button" className="topbar__logout" onClick={handleLogout}>
              Salir
            </button>
          </nav>
        </div>
      </div>
    </header>
  )
}

