import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import {
  AlertTriangleIcon,
  ClockIcon,
  CustomerIcon,
  EmployeeIcon,
  MaterialIcon,
  OrderIcon,
  ProductIcon,
  ProductionIcon,
  SupplierIcon,
  WalletIcon,
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

const SUMMARY_CONFIG = [
  { key: 'active_orders', label: 'Pesanan aktif', icon: OrderIcon },
  { key: 'due_soon_orders', label: 'Jatuh tempo ≤ 7 hari', icon: ClockIcon },
  { key: 'overdue_orders', label: 'Lewat jatuh tempo', icon: AlertTriangleIcon },
  { key: 'running_production', label: 'Produksi berjalan', icon: ProductionIcon },
  { key: 'unpaid_total', label: 'Piutang pesanan', icon: WalletIcon, money: true },
]

const ORDER_STATUS_BADGE = {
  Baru: 'badge badge--muted',
  Diproses: 'badge badge--warning',
  Selesai: 'badge badge--success',
  Dibatalkan: 'badge badge--danger',
}

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
  const summary = data.summary || {}
  const attentionOrders = [
    ...(data.overdue_orders || []).map((order) => ({
      ...order,
      attention: 'Lewat jatuh tempo',
    })),
    ...(data.due_soon_orders || []).map((order) => ({
      ...order,
      attention: 'Mendekati jatuh tempo',
    })),
  ]

  return (
    <section className="page-stack">
      <div className="stat-grid">
        {SUMMARY_CONFIG.map((card) => {
          const Icon = card.icon
          const value = card.money
            ? formatRupiah(summary[card.key] || 0)
            : formatNumber(summary[card.key] || 0)

          return (
            <article
              className={
                card.key === 'overdue_orders'
                  ? 'stat-card stat-card--accent'
                  : 'stat-card'
              }
              key={card.key}
            >
              <span className="stat-icon">
                <Icon size={20} />
              </span>
              <div>
                <span className="stat-value">{value}</span>
                <span className="stat-label">{card.label}</span>
              </div>
            </article>
          )
        })}
      </div>

      <div className="panel">
        <div className="panel-heading">
          <div>
            <h3>Ringkasan Data Master</h3>
            <p>Jumlah data dasar yang tersedia di sistem.</p>
          </div>
          <Link className="text-link" to="/reports">
            Buka laporan
          </Link>
        </div>
        <div className="dashboard-master-counts">
          {CARD_CONFIG.map((card) => (
            <span className="badge badge--muted" key={card.key}>
              {card.label}: {formatNumber(data.counts[card.key])}
            </span>
          ))}
        </div>
      </div>

      <div className="dashboard-grid">
        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Pesanan Aktif</h3>
              <p>Pesanan berstatus Baru atau Diproses.</p>
            </div>
            <Link className="text-link" to="/orders">
              Lihat pesanan
            </Link>
          </div>

          {(data.active_orders || []).length === 0 ? (
            <div className="panel-state">Tidak ada pesanan aktif.</div>
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Pesanan</th>
                    <th>Pelanggan</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                    <th className="align-right">Sisa</th>
                  </tr>
                </thead>
                <tbody>
                  {data.active_orders.map((order) => (
                    <tr key={order.order_id}>
                      <td>
                        <Link className="text-link" to={`/orders/${order.order_id}`}>
                          #{order.order_id}
                        </Link>
                      </td>
                      <td>{order.customer?.name || '—'}</td>
                      <td>{formatDate(order.due_date)}</td>
                      <td>
                        <span
                          className={
                            ORDER_STATUS_BADGE[order.status] || 'badge'
                          }
                        >
                          {order.status}
                        </span>
                      </td>
                      <td className="align-right">
                        {formatRupiah(order.remaining_amount)}
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
              <h3>Pesanan Perlu Perhatian</h3>
              <p>Pesanan aktif yang mendekati atau melewati due date.</p>
            </div>
            <span className="badge badge--danger">
              {attentionOrders.length} pesanan
            </span>
          </div>

          {attentionOrders.length === 0 ? (
            <div className="panel-state">
              Semua pesanan aktif masih dalam tenggat.
            </div>
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Pesanan</th>
                    <th>Pelanggan</th>
                    <th>Jatuh Tempo</th>
                    <th>Keterangan</th>
                    <th className="align-right">Sisa</th>
                  </tr>
                </thead>
                <tbody>
                  {attentionOrders.map((order) => (
                    <tr key={order.order_id}>
                      <td>
                        <Link className="text-link" to={`/orders/${order.order_id}`}>
                          #{order.order_id}
                        </Link>
                      </td>
                      <td>{order.customer?.name || '—'}</td>
                      <td>{formatDate(order.due_date)}</td>
                      <td>
                        <span
                          className={
                            order.is_overdue
                              ? 'badge badge--danger'
                              : 'badge badge--warning'
                          }
                        >
                          {order.attention}
                        </span>
                      </td>
                      <td className="align-right">
                        {formatRupiah(order.remaining_amount)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
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
              <h3>Produksi Berjalan</h3>
              <p>Perintah produksi yang sedang dikerjakan.</p>
            </div>
            <Link className="text-link" to="/production-orders">
              Lihat produksi
            </Link>
          </div>

          {(data.running_production || []).length === 0 ? (
            <div className="panel-state">Tidak ada produksi berjalan.</div>
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Produksi</th>
                    <th>Item</th>
                    <th>Target</th>
                    <th className="align-right">Progres</th>
                  </tr>
                </thead>
                <tbody>
                  {data.running_production.map((production) => (
                    <tr key={production.production_order_id}>
                      <td>
                        <Link
                          className="text-link"
                          to={`/production-orders/${production.production_order_id}`}
                        >
                          #{production.production_order_id}
                        </Link>
                        <small className="order-line-note">
                          {production.order?.customer?.name || '—'}
                        </small>
                      </td>
                      <td>
                        {production.item?.product_name || '—'}
                        <small className="order-line-note">
                          {production.item?.sku} · {production.planned_quantity} pcs
                        </small>
                      </td>
                      <td>{formatDate(production.target_date)}</td>
                      <td className="align-right">
                        {production.progress_count} catatan
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
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
                  <th>Jatuh Tempo</th>
                  <th>Status</th>
                  <th className="align-right">Total</th>
                  <th className="align-right">Sisa</th>
                </tr>
              </thead>
              <tbody>
                {data.recent_orders.map((order) => (
                  <tr key={order.order_id}>
                    <td>{order.customer?.name || '—'}</td>
                    <td>{formatDate(order.order_date)}</td>
                    <td>{formatDate(order.due_date)}</td>
                    <td>
                      <span
                        className={
                          order.is_overdue
                            ? 'badge badge--danger'
                            : ORDER_STATUS_BADGE[order.status] || 'badge'
                        }
                      >
                        {order.status}
                      </span>
                    </td>
                    <td className="align-right">
                      {formatRupiah(order.total_amount)}
                    </td>
                    <td className="align-right">
                      {formatRupiah(order.remaining_amount)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </section>
  )
}
