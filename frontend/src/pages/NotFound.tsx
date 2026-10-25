import { Link } from 'react-router'
import { Columns } from '../components/ui/Columns'
import { Module } from '../components/ui/Module'

export function NotFound() {
  return (
    <Columns>
      <Module title="Página no encontrada" as="h1">
      <p>
        <Link to="/">Volver al inicio</Link>
      </p>
    </Module>
    </Columns>
  )
}
