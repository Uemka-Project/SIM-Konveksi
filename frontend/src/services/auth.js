import { apiRequest } from '../lib/api'

const TOKEN_KEY = 'simkonveksi.auth.token'
const USER_KEY = 'simkonveksi.auth.user'

export function getStoredSession() {
  try {
    const token = window.localStorage.getItem(TOKEN_KEY)
    const rawUser = window.localStorage.getItem(USER_KEY)

    if (!token || !rawUser) {
      return null
    }

    const user = JSON.parse(rawUser)

    if (!user || typeof user !== 'object') {
      return null
    }

    return { token, user }
  } catch {
    return null
  }
}

export function saveSession({ token, user }) {
  window.localStorage.setItem(TOKEN_KEY, token)
  window.localStorage.setItem(USER_KEY, JSON.stringify(user))
}

export function clearSession() {
  window.localStorage.removeItem(TOKEN_KEY)
  window.localStorage.removeItem(USER_KEY)
}

export async function login(credentials) {
  const response = await apiRequest('/auth/login', {
    method: 'POST',
    body: credentials,
  })

  return {
    token: response.data.token,
    user: response.data.user,
  }
}

export async function fetchCurrentUser(token) {
  const response = await apiRequest('/auth/me', { token })

  return response.data
}

export async function logout(token) {
  await apiRequest('/auth/logout', { method: 'POST', token })
}
