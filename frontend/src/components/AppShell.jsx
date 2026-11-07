import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../context/useAuth'
import {
  CustomerIcon,
  DashboardIcon,
  EmployeeIcon,
  GarmentIcon,
  LogoutIcon,
  MaterialIcon,
  OrderIcon,
  ProductIcon,
  ProductionIcon,
  PurchaseIcon,
  ReportIcon,
  StockIcon,
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
  { to: '/orders', label: 'Pesanan', icon: OrderIcon, module: 'orders' },
  { to: '/production-orders', label: 'Produksi', icon: ProductionIcon, module: 'production' },
  { to: '/purchases', label: 'Pembelian', icon: PurchaseIcon, module: 'purchases' },
  { to: '/stock-movements', label: 'Stok', icon: StockIcon, module: 'stock' },
  { to: '/reports', label: 'Laporan', icon: ReportIcon, module: 'reports' },
  { to: '/users', label: 'Pengguna', icon: UsersIcon, module: 'users' },
]

const PAGE_TITLES = {
  '/': 'Dasbor',
  '/customers': 'Pelanggan',
  '/suppliers': 'Pemasok',
  '/employees': 'Karyawan',
  '/materials': 'Bahan Baku',
  '/products': 'Produk & Varian',
  '/orders': 'Pesanan',
  '/orders/new': 'Buat Pesanan',
  '/production-orders': 'Produksi',
  '/purchases': 'Pembelian',
  '/purchases/new': 'Buat Pembelian',
  '/stock-movements': 'Stok & Kartu Stok',
  '/reports': 'Laporan Manajemen',
  '/users': 'Pengguna & Peran',
}

function pageTitle(pathname) {
  if (pathname.startsWith('/products/')) {
    return 'Detail Produk'
  }

  if (/^\/orders\/[^/]+\/edit$/.test(pathname)) {
    return 'Ubah Pesanan'
  }

  if (/^\/orders\/[^/]+$/.test(pathname)) {
    return 'Detail Pesanan'
  }

  if (/^\/production-orders\/[^/]+$/.test(pathname)) {
    return 'Detail Produksi'
  }

  if (/^\/purchases\/[^/]+\/edit$/.test(pathname)) {
    return 'Ubah Pembelian'
  }

  if (/^\/purchases\/[^/]+$/.test(pathname)) {
    return 'Detail Pembelian'
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
            Fase 6 · Dasbor &amp; Laporan
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
