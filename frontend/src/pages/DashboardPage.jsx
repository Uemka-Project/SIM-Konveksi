import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import {
  AlertTriangleIcon,
  CustomerIcon,
  EmployeeIcon,
  MaterialIcon,
  OrderIcon,
  ProductIcon,
  SupplierIcon,
} from '../components/Icons'
import { apiRequest } from '../lib/api'
import { formatDate, formatNumber, formatRupiah } from '../lib/format'

const CARD_CONFIG = [
  { key: 'customers', label: 'Pelanggan', icon: CustomerIcon },
  { key: 'suppliers', label: 'Pemasok', icon: SupplierIcon },
  { key: 'employees', label: 'Karyawan', icon: EmployeeIcon },
  { key: 'materials', label: 'Bahan Baku', icon: MaterialIcon },
  { key: 'products', label: 'Produk', icon: ProductIcon },
  { key: 'orders', label: 'Pesanan', icon: OrderIcon },
]

export default function DashboardPage() {
  const query = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => apiRequest('/dashboard'),
  })

  if (query.isLoading) {
    return (
      <div className="panel-state">
        <span className="spinner spinner--dark" />
        Memuat ringkasan...
      </div>
    )
  }

  if (query.isError) {
    return (
      <div className="banner banner--error" role="alert">
        {query.error.message}
      </div>
    )
  }

  const data = query.data.data

  return (
    <section className="page-stack">
      <div className="stat-grid">
        {CARD_CONFIG.map((card) => {
          const Icon = card.icon

          return (
            <article className="stat-card" key={card.key}>
              <span className="stat-icon">
                <Icon size={20} />
              </span>
              <div>
                <span className="stat-value">{formatNumber(data.counts[card.key])}</span>
                <span className="stat-label">{card.label}</span>
              </div>
            </article>
          )
        })}

        <article className="stat-card stat-card--accent">
          <span className="stat-icon">
            <AlertTriangleIcon size={20} />
          </span>
          <div>
            <span className="stat-value">{formatRupiah(data.unpaid_total)}</span>
            <span className="stat-label">Sisa tagihan pesanan</span>
          </div>
        </article>
      </div>

      <div className="dashboard-grid">
        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Bahan Hampir Habis</h3>
              <p>Stok di bawah batas minimum.</p>
            </div>
            <Link className="text-link" to="/materials">
              Lihat bahan
            </Link>
          </div>

          {data.low_stock.length === 0 ? (
            <div className="panel-state">Semua stok bahan berada di atas minimum.</div>
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Bahan</th>
                    <th>Stok</th>
                    <th>Minimum</th>
                  </tr>
                </thead>
                <tbody>
                  {data.low_stock.map((material) => (
                    <tr key={material.material_id}>
                      <td>{material.material_name}</td>
                      <td>
                        <span className="badge badge--danger">
                          {formatNumber(material.current_stock)} {material.unit_name}
                        </span>
                      </td>
                      <td>
                        {formatNumber(material.minimum_stock)} {material.unit_name}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Pesanan Terbaru</h3>
              <p>Data pesanan dibaca dari transaksi yang sudah ada.</p>
            </div>
            <Link className="text-link" to="/orders">
              Lihat pesanan
            </Link>
          </div>

          {data.recent_orders.length === 0 ? (
            <div className="panel-state">Belum ada pesanan.</div>
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Pelanggan</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th className="align-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {data.recent_orders.map((order) => (
                    <tr key={order.order_id}>
                      <td>{order.customer?.name || '—'}</td>
                      <td>{formatDate(order.order_date)}</td>
                      <td>
                        <span
                          className={
                            order.is_overdue
                              ? 'badge badge--danger'
                              : 'badge badge--muted'
                          }
                        >
                          {order.status}
                        </span>
                      </td>
                      <td className="align-right">{formatRupiah(order.total_amount)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </section>
  )
}
