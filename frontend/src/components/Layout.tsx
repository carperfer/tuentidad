import { Outlet } from 'react-router'
import { Header } from './Header'

export function Layout() {
  return (
    <>
      <Header />
      <main className="page">
        <Outlet />
      </main>
      <footer className="footer">
        tuentidad.es · Proyecto formativo inspirado en tuenti.es
      </footer>
    </>
  )
}
