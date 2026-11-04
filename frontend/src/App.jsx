import { useEffect, useState } from 'react'
import LoginPage from './pages/LoginPage'
import WelcomePage from './pages/WelcomePage'
import {
  clearSession,
  fetchCurrentUser,
  getStoredSession,
  saveSession,
} from './services/auth'
import './App.css'

function App() {
  const [storedSession] = useState(() => getStoredSession())
  const [session, setSession] = useState(storedSession)
  const [checkingSession, setCheckingSession] = useState(
    Boolean(storedSession?.token),
  )

  useEffect(() => {
    if (!storedSession?.token) {
      return
    }

    let active = true

    fetchCurrentUser(storedSession.token)
      .then((user) => {
        if (!active) {
          return
        }

        const nextSession = { token: storedSession.token, user }
        saveSession(nextSession)
        setSession(nextSession)
      })
      .catch(() => {
        if (!active) {
          return
        }

        clearSession()
        setSession(null)
      })
      .finally(() => {
        if (active) {
          setCheckingSession(false)
        }
      })

    return () => {
      active = false
    }
  }, [storedSession])

  function handleAuthenticated(nextSession) {
    saveSession(nextSession)
    setSession(nextSession)
  }

  function handleLogout() {
    clearSession()
    setSession(null)
  }

  if (checkingSession) {
    return (
      <div className="session-loader">
        <span className="spinner spinner--dark" />
        <span>Memeriksa sesi...</span>
      </div>
    )
  }

  return session ? (
    <WelcomePage session={session} onLogout={handleLogout} />
  ) : (
    <LoginPage onAuthenticated={handleAuthenticated} />
  )
}

export default App
