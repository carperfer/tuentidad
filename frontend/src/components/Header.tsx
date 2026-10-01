import { Link, useNavigate } from 'react-router'
import { useAuth } from '../auth/context'

export function Header() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  async function handleLogout() {
    await logout()
    navigate('/')
  }

  return (
    <header className="header">
      <div className="header__inner">
        <Link to={user ? '/inicio' : '/'} className="header__logo">
          tuentidad
        </Link>
        {user && (
          <nav className="header__nav" aria-label="Principal">
            <Link to="/inicio">Inicio</Link>
            <span className="header__user">{user.first_name}</span>
            <button type="button" className="link-button" onClick={handleLogout}>
              Salir
            </button>
          </nav>
        )}
      </div>
    </header>
  )
}
