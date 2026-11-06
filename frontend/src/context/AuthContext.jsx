import { useCallback, useEffect, useMemo, useState } from 'react'
import { setAuthToken } from '../lib/api'
import {
  clearSession,
  fetchCurrentUser,
  getStoredSession,
  login as loginRequest,
  logout as logoutRequest,
  saveSession,
} from '../services/auth'
import { AuthContext } from './useAuth'

export function AuthProvider({ children }) {
  const [storedSession] = useState(() => getStoredSession())
  const [session, setSession] = useState(storedSession)
  const [checkingSession, setCheckingSession] = useState(
    Boolean(storedSession?.token),
  )

  useEffect(() => {
    if (!storedSession?.token) {
      setAuthToken(null)
      return undefined
    }

    let active = true
    setAuthToken(storedSession.token)

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
        setAuthToken(null)
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

  const login = useCallback(async (credentials) => {
    const nextSession = await loginRequest(credentials)
    saveSession(nextSession)
    setAuthToken(nextSession.token)
    setSession(nextSession)

    return nextSession
  }, [])

  const logout = useCallback(async () => {
    const token = session?.token

    try {
      if (token) {
        await logoutRequest(token)
      }
    } finally {
      clearSession()
      setAuthToken(null)
      setSession(null)
    }
  }, [session])

  const can = useCallback(
    (module, action = 'R') => {
      const actions = session?.user?.permissions?.[module] || ''

      return actions.includes(action)
    },
    [session],
  )

  const value = useMemo(
    () => ({
      session,
      user: session?.user ?? null,
      token: session?.token ?? null,
      checkingSession,
      isAuthenticated: Boolean(session?.token),
      login,
      logout,
      can,
    }),
    [session, checkingSession, login, logout, can],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
