import AuthLayout from '../components/AuthLayout'
import { CheckIcon, LogoutIcon, ShieldIcon } from '../components/Icons'

export default function WelcomePage({ session, onLogout }) {
  const { user } = session
  const primaryRole = user.roles?.[0]?.name || 'Admin'

  return (
    <AuthLayout>
      <section className="auth-card success-card" aria-labelledby="welcome-heading">
        <div className="success-icon">
          <CheckIcon size={30} />
        </div>

        <span className="role-pill">
          <ShieldIcon size={14} />
          Login berhasil
        </span>

        <h2 id="welcome-heading">Selamat datang, {user.name}.</h2>
        <p className="auth-subtitle">
          Autentikasi Admin berhasil. Sesi Anda sudah aktif.
        </p>

        <dl className="session-details">
          <div>
            <dt>Email</dt>
            <dd>{user.email}</dd>
          </div>
          <div>
            <dt>Role</dt>
            <dd>
              <span className="role-badge">{primaryRole}</span>
            </dd>
          </div>
          <div>
            <dt>Status</dt>
            <dd>
              <span className="status-active">
                <span />
                Aktif
              </span>
            </dd>
          </div>
        </dl>

        <div className="scope-note">
          <strong>Tahap ini hanya mencakup login Admin.</strong>
          <span>
            Modul produksi, pesanan, dan laporan dapat ditambahkan pada tahap
            berikutnya.
          </span>
        </div>

        <button type="button" className="secondary-button" onClick={onLogout}>
          <LogoutIcon size={18} />
          Keluar
        </button>
      </section>
    </AuthLayout>
  )
}
