import { useState } from 'react'
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
import { login } from '../services/auth'

const DEMO_EMAIL = 'admin@konveksi.test'
const DEMO_PASSWORD = 'Admin123!'

export default function LoginPage({ onAuthenticated }) {
  const [form, setForm] = useState({ email: '', password: '' })
  const [showPassword, setShowPassword] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')

  function handleChange(event) {
    const { name, value } = event.target

    setForm((current) => ({ ...current, [name]: value }))

    if (error) {
      setError('')
    }
  }

  function fillDemoAccount() {
    setForm({ email: DEMO_EMAIL, password: DEMO_PASSWORD })
    setError('')
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setError('')

    if (!form.email.trim() || !form.password) {
      setError('Email dan password wajib diisi.')
      return
    }

    setIsSubmitting(true)

    try {
      const session = await login({
        email: form.email.trim(),
        password: form.password,
      })

      onAuthenticated(session)
    } catch (requestError) {
      const validationMessage =
        requestError.errors?.email?.[0] || requestError.errors?.password?.[0]

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
          Portal Admin
        </span>

        <h2 id="login-heading">Masuk ke akun Anda</h2>
        <p className="auth-subtitle">
          Gunakan akun Administrator untuk mengelola produksi konveksi.
        </p>

        {error && (
          <div className="form-alert" role="alert">
            <AlertIcon size={18} />
            <span>{error}</span>
          </div>
        )}

        <form className="login-form" onSubmit={handleSubmit} noValidate>
          <div className="field">
            <label className="field-label" htmlFor="email">
              Email
            </label>
            <div className="field-control">
              <MailIcon className="field-icon" size={18} />
              <input
                id="email"
                name="email"
                type="email"
                value={form.email}
                onChange={handleChange}
                placeholder="admin@konveksi.test"
                autoComplete="email"
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
                Masuk sebagai Admin
                <ArrowRightIcon size={18} />
              </>
            )}
          </button>
        </form>

        <div className="demo-account">
          <div>
            <span className="demo-label">Akun demo</span>
            <span className="demo-value">
              {DEMO_EMAIL} · {DEMO_PASSWORD}
            </span>
          </div>
          <button type="button" onClick={fillDemoAccount}>
            Isi otomatis
          </button>
        </div>

        <p className="auth-note">
          <ShieldIcon size={14} />
          Akses terbatas untuk pengguna dengan role Admin.
        </p>
      </section>
    </AuthLayout>
  )
}
