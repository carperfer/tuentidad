import { Route, Routes } from 'react-router'
import { Layout } from './components/Layout'
import { RequireAuth, RequireGuest } from './components/RequireAuth'
import { ForgotPassword } from './pages/ForgotPassword'
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
        <Route path="*" element={<NotFound />} />
      </Route>
    </Routes>
  )
}
