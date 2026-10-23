import { Route, Routes } from 'react-router'
import { Layout } from './components/Layout'
import { RequireAuth, RequireGuest } from './components/RequireAuth'
import { ForgotPassword } from './pages/ForgotPassword'
import { Cookies } from './pages/legal/Cookies'
import { Privacy } from './pages/legal/Privacy'
import { Terms } from './pages/legal/Terms'
import { Home } from './pages/Home'
import { Landing } from './pages/Landing'
import { NotFound } from './pages/NotFound'
import { Register } from './pages/Register'
import { ResetPassword } from './pages/ResetPassword'

export function App() {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route
          index
          element={
            <RequireGuest>
              <Landing />
            </RequireGuest>
          }
        />
        <Route
          path="registro/:token"
          element={
            <RequireGuest>
              <Register />
            </RequireGuest>
          }
        />
        <Route path="recuperar-contrasena" element={<ForgotPassword />} />
        <Route path="restablecer-contrasena/:token" element={<ResetPassword />} />
        <Route
          path="inicio"
          element={
            <RequireAuth>
              <Home />
            </RequireAuth>
          }
        />
        <Route path="privacidad" element={<Privacy />} />
        <Route path="condiciones" element={<Terms />} />
        <Route path="cookies" element={<Cookies />} />
        <Route path="*" element={<NotFound />} />
      </Route>
    </Routes>
  )
}
