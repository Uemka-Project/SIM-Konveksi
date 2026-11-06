export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

const API_BASE_URL = (
  import.meta.env.VITE_API_BASE_URL || '/api/v1'
).replace(/\/$/, '')

let authToken = null

export function setAuthToken(token) {
  authToken = token
}

export async function apiRequest(path, { method = 'GET', body, token } = {}) {
  const headers = {
    Accept: 'application/json',
  }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  const bearer = token ?? authToken

  if (bearer) {
    headers.Authorization = `Bearer ${bearer}`
  }

  let response

  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    })
  } catch {
    throw new ApiError(
      'Tidak dapat terhubung ke server. Pastikan backend Laravel sudah berjalan.',
      0,
    )
  }

  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new ApiError(
      payload.message || 'Terjadi kesalahan. Silakan coba lagi.',
      response.status,
      payload.errors || {},
    )
  }

  return payload
}
