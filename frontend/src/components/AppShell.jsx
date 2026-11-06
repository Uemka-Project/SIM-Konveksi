import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../context/useAuth'
import {
  CustomerIcon,
  DashboardIcon,
  EmployeeIcon,
  GarmentIcon,
  LogoutIcon,
  MaterialIcon,
  ProductIcon,
  SupplierIcon,
  UsersIcon,
} from './Icons'

const NAV_ITEMS = [
  { to: '/', label: 'Dasbor', icon: DashboardIcon, module: 'materials', end: true },
  { to: '/customers', label: 'Pelanggan', icon: CustomerIcon, module: 'customers' },
  { to: '/suppliers', label: 'Pemasok', icon: SupplierIcon, module: 'suppliers' },
  { to: '/employees', label: 'Karyawan', icon: EmployeeIcon, module: 'employees' },
  { to: '/materials', label: 'Bahan Baku', icon: MaterialIcon, module: 'materials' },
  { to: '/products', label: 'Produk', icon: ProductIcon, module: 'products' },
  { to: '/users', label: 'Pengguna', icon: UsersIcon, module: 'users' },
]

const PAGE_TITLES = {
  '/': 'Dasbor',
  '/customers': 'Pelanggan',
  '/suppliers': 'Pemasok',
  '/employees': 'Karyawan',
  '/materials': 'Bahan Baku',
  '/products': 'Produk & Varian',
  '/users': 'Pengguna & Peran',
}

function pageTitle(pathname) {
  if (pathname.startsWith('/products/')) {
    return 'Detail Produk'
  }

  return PAGE_TITLES[pathname] || 'SIM Konveksi'
}

export default function AppShell() {
  const { user, logout, can } = useAuth()
  const location = useLocation()

  const roleLabel = user?.role_names?.length
    ? user.role_names
        .map((name) => name.charAt(0).toUpperCase() + name.slice(1))
        .join(', ')
    : 'Pengguna'

  return (
    <div className="app-shell">
      <aside className="app-sidebar">
        <div className="app-brand">
          <span className="app-brand-logo">
            <GarmentIcon size={22} />
          </span>
          <span>
            <strong>SIM Konveksi</strong>
            <small>Sejahtera</small>
          </span>
        </div>

        <nav className="app-nav" aria-label="Navigasi utama">
          {NAV_ITEMS.filter(
            (item) => item.module === 'materials' || can(item.module, 'R'),
          ).map((item) => {
            const Icon = item.icon

            return (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                className={({ isActive }) =>
                  isActive ? 'app-nav-link is-active' : 'app-nav-link'
                }
              >
                <Icon size={18} />
                {item.label}
              </NavLink>
            )
          })}
        </nav>

        <div className="app-sidebar-footer">
          <p className="app-sidebar-note">
            Fase 2 · Autentikasi &amp; Data Master
          </p>
          <button type="button" className="app-logout" onClick={logout}>
            <LogoutIcon size={17} />
            Keluar
          </button>
        </div>
      </aside>

      <div className="app-body">
        <header className="app-topbar">
          <div>
            <span className="app-topbar-eyebrow">Konveksi Sejahtera</span>
            <h1>{pageTitle(location.pathname)}</h1>
          </div>

          <div className="app-user">
            <span className="app-avatar" aria-hidden="true">
              {(user?.name || user?.username || '?')
                .charAt(0)
                .toUpperCase()}
            </span>
            <span>
              <strong>{user?.name || user?.username}</strong>
              <small>{roleLabel}</small>
            </span>
          </div>
        </header>

        <main className="app-content">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
