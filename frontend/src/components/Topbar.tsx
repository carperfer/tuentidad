import { useState, type FormEvent } from 'react'
import { Link, NavLink, useNavigate } from 'react-router'
import { useAuth } from '../auth/context'

/** Barra superior azul con navegación, buscador y acciones de la cuenta (guía §3). */
export function Topbar() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [query, setQuery] = useState('')
  const [menuOpen, setMenuOpen] = useState(false)

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
        </button>
        <div id="topbar-menu" className={`topbar__menu${menuOpen ? ' topbar__menu--open' : ''}`}>
          <nav className="topbar__nav" aria-label="Principal">
            <NavLink to="/inicio" onClick={close}>
              Inicio
            </NavLink>
            <NavLink to={`/perfil/${user.id}`} onClick={close}>
              Perfil
            </NavLink>
            <NavLink to="/gente" onClick={close}>
              Gente
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
          <Link to={`/perfil/${user.id}/fotos`} className="button button--primary topbar__upload" onClick={close}>
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
