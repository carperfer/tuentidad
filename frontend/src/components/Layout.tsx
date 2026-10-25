import { Link, Outlet } from 'react-router'
import { useAuth } from '../auth/context'
import { Topbar } from './Topbar'

const YEAR = new Date().getFullYear()

/**
 * Carcasa de todas las páginas: con sesión, barra superior azul sobre fondo blanco;
 * sin sesión, fondo azul con el logotipo como la portada de Tuenti.
 */
export function Layout() {
  const { user, loading } = useAuth()
  const guest = !loading && !user

  return (
    <div className={guest ? 'shell shell--guest' : 'shell'}>
      <a className="skip-link" href="#contenido">
        Saltar al contenido
      </a>
      {guest ? (
        <header className="guest-header">
          <Link to="/" className="guest-header__logo">
            tuentidad
          </Link>
        </header>
      ) : (
        <Topbar />
      )}
      <div className="shell__content">
        <Outlet />
      </div>
      <footer className="footer">
        <div className="footer__inner">
          <span>© tuentidad {YEAR} · Proyecto formativo inspirado en tuenti.es</span>
          <nav aria-label="Información legal">
            <Link to="/privacidad">Privacidad</Link>
            <Link to="/condiciones">Condiciones de uso</Link>
            <Link to="/cookies">Cookies</Link>
          </nav>
        </div>
      </footer>
    </div>
  )
}
