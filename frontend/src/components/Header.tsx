import { Link } from 'react-router'

export function Header() {
  return (
    <header className="header">
      <div className="header__inner">
        <Link to="/" className="header__logo">
          tuentidad
        </Link>
      </div>
    </header>
  )
}
