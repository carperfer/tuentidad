import { Link, Outlet } from 'react-router'
import { Header } from './Header'

export function Layout() {
  return (
    <>
      <Header />
      <main className="page">
        <Outlet />
      </main>
      <footer className="footer">
        <p>tuentidad.es · Proyecto formativo inspirado en tuenti.es</p>
        <nav aria-label="Información legal">
          <Link to="/privacidad">Privacidad</Link> · <Link to="/condiciones">Condiciones de uso</Link> ·{' '}
          <Link to="/cookies">Cookies</Link>
        </nav>
      </footer>
    </>
  )
}
