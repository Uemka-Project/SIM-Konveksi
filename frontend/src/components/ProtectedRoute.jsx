import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../context/useAuth'

export default function ProtectedRoute({ children }) {
  const { isAuthenticated, checkingSession } = useAuth()
  const location = useLocation()

  if (checkingSession) {
    return (
      <div className="session-loader">
        <span className="spinner spinner--dark" />
        Memeriksa sesi Anda...
      </div>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }

  return children
}
