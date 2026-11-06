import { useState } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import AuthLayout from '../components/AuthLayout'
import {
  AlertIcon,
  ArrowRightIcon,
  EyeIcon,
  EyeOffIcon,
  LockIcon,
  MailIcon,
  ShieldIcon,
} from '../components/Icons'
import { useAuth } from '../context/useAuth'

const DEMO_LOGIN = 'owner'
const DEMO_PASSWORD = 'Konveksi#2026'

export default function LoginPage() {
  const { login, isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [form, setForm] = useState({ login: '', password: '' })
  const [showPassword, setShowPassword] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')

  if (isAuthenticated) {
    return <Navigate to="/" replace />
  }

  function handleChange(event) {
    const { name, value } = event.target

    setForm((current) => ({ ...current, [name]: value }))

    if (error) {
      setError('')
    }
  }

  function fillDemoAccount() {
    setForm({ login: DEMO_LOGIN, password: DEMO_PASSWORD })
    setError('')
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setError('')

    if (!form.login.trim() || !form.password) {
      setError('Username/email dan password wajib diisi.')
      return
    }

    setIsSubmitting(true)

    try {
      await login({
        login: form.login.trim(),
        password: form.password,
      })

      navigate(location.state?.from || '/', { replace: true })
    } catch (requestError) {
      const validationMessage =
        requestError.errors?.login?.[0] || requestError.errors?.password?.[0]

      setError(
        validationMessage ||
          requestError.message ||
          'Login gagal. Silakan coba lagi.',
      )
      setIsSubmitting(false)
    }
  }

  return (
    <AuthLayout>
      <section className="auth-card" aria-labelledby="login-heading">
        <span className="role-pill">
          <ShieldIcon size={14} />
          Portal Internal
        </span>

        <h2 id="login-heading">Masuk ke akun Anda</h2>
        <p className="auth-subtitle">
          Gunakan username atau email yang diberikan pemilik untuk mengelola
          produksi konveksi.
        </p>

        {error && (
          <div className="form-alert" role="alert">
            <AlertIcon size={18} />
            <span>{error}</span>
          </div>
        )}

        <form className="login-form" onSubmit={handleSubmit} noValidate>
          <div className="field">
            <label className="field-label" htmlFor="login">
              Username atau email
            </label>
            <div className="field-control">
              <MailIcon className="field-icon" size={18} />
              <input
                id="login"
                name="login"
                type="text"
                value={form.login}
                onChange={handleChange}
                placeholder="owner"
                autoComplete="username"
                autoFocus
                required
              />
            </div>
          </div>

          <div className="field">
            <label className="field-label" htmlFor="password">
              Password
            </label>
            <div className="field-control">
              <LockIcon className="field-icon" size={18} />
              <input
                id="password"
                name="password"
                type={showPassword ? 'text' : 'password'}
                value={form.password}
                onChange={handleChange}
                placeholder="Masukkan password"
                autoComplete="current-password"
                required
              />
              <button
                type="button"
                className="password-toggle"
                onClick={() => setShowPassword((visible) => !visible)}
                aria-label={
                  showPassword ? 'Sembunyikan password' : 'Tampilkan password'
                }
                aria-pressed={showPassword}
              >
                {showPassword ? <EyeOffIcon size={18} /> : <EyeIcon size={18} />}
              </button>
            </div>
          </div>

          <button type="submit" className="submit-button" disabled={isSubmitting}>
            {isSubmitting ? (
              <>
                <span className="spinner" />
                Memeriksa akun...
              </>
            ) : (
              <>
                Masuk
                <ArrowRightIcon size={18} />
              </>
            )}
          </button>
        </form>

        <div className="demo-account">
          <div>
            <span className="demo-label">Akun demo</span>
            <span className="demo-value">
              {DEMO_LOGIN} · {DEMO_PASSWORD}
            </span>
          </div>
          <button type="button" onClick={fillDemoAccount}>
            Isi otomatis
          </button>
        </div>

        <p className="auth-note">
          <ShieldIcon size={14} />
          Hak akses setiap pengguna mengikuti peran yang diberikan pemilik.
        </p>
      </section>
    </AuthLayout>
  )
}
