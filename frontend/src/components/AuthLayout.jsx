import BrandPanel from './BrandPanel'

export default function AuthLayout({ children }) {
  return (
    <div className="auth-shell">
      <BrandPanel />
      <main className="auth-main">
        <div className="auth-main-inner">{children}</div>
      </main>
    </div>
  )
}
