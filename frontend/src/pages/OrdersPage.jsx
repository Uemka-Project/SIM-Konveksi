import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useLocation } from 'react-router-dom'
import { useAuth } from '../context/useAuth'
import { apiRequest } from '../lib/api'
import { formatDate, formatRupiah } from '../lib/format'
import {
  AlertIcon,
  ArrowLeftIcon,
  ArrowRightIcon,
  OrderIcon,
  PlusIcon,
  SearchIcon,
} from '../components/Icons'

const PER_PAGE = 10
const STATUS_OPTIONS = ['Baru', 'Diproses', 'Selesai', 'Dibatalkan']

const STATUS_BADGE = {
  Baru: 'badge badge--muted',
  Diproses: 'badge badge--warning',
  Selesai: 'badge badge--success',
  Dibatalkan: 'badge badge--danger',
}

export default function OrdersPage() {
  const { can } = useAuth()
  const location = useLocation()
  const [page, setPage] = useState(1)
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [feedback, setFeedback] = useState(location.state?.feedback || null)

  useEffect(() => {
    if (!feedback) {
      return undefined
    }

    const timer = window.setTimeout(() => setFeedback(null), 4500)

    return () => window.clearTimeout(timer)
  }, [feedback])

  const query = useQuery({
    queryKey: ['orders', page, search, status],
    queryFn: () => {
      const params = new URLSearchParams({
        page: String(page),
        per_page: String(PER_PAGE),
      })

      if (search) {
        params.set('search', search)
      }

      if (status) {
        params.set('status', status)
      }

      return apiRequest(`/orders?${params.toString()}`)
    },
  })

  const rows = query.data?.data || []
  const meta = query.data?.meta

  function submitSearch(event) {
    event.preventDefault()
    setPage(1)
    setSearch(searchInput.trim())
  }

  function changeStatus(event) {
    setPage(1)
    setStatus(event.target.value)
  }

  return (
    <section className="page-stack">
      <div className="page-heading">
        <div>
          <h2>Pesanan</h2>
          <p>
            Catat pesanan pelanggan, pantau status, dan kelola pembayaran sampai
            lunas.
          </p>
        </div>

        {can('orders', 'C') && (
          <Link className="button button--primary" to="/orders/new">
            <PlusIcon size={17} />
            Buat pesanan
          </Link>
        )}
      </div>

      {feedback && (
        <div className="banner banner--success" role="status">
          {feedback}
        </div>
      )}

      <div className="panel">
        <div className="panel-toolbar">
          <form className="search-box" onSubmit={submitSearch} role="search">
            <SearchIcon size={17} />
            <input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Cari pelanggan atau nomor pesanan..."
              aria-label="Cari pesanan"
            />
            <button type="submit">Cari</button>
          </form>

          <div className="toolbar-filters">
            <label className="filter-field">
              Status
              <select value={status} onChange={changeStatus}>
                <option value="">Semua status</option>
                {STATUS_OPTIONS.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </label>

            {meta && (
              <span className="panel-meta">
                {meta.total} pesanan · halaman {meta.current_page} dari{' '}
                {meta.last_page}
              </span>
            )}
          </div>
        </div>

        {query.isLoading ? (
          <div className="panel-state">
            <span className="spinner spinner--dark" />
            Memuat pesanan...
          </div>
        ) : query.isError ? (
          <div className="panel-state panel-state--error">
            <AlertIcon size={18} />
            {query.error.message}
          </div>
        ) : rows.length === 0 ? (
          <div className="panel-state">
            Belum ada pesanan yang sesuai filter.
          </div>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>No.</th>
                  <th>Pelanggan</th>
                  <th>Tanggal</th>
                  <th>Jatuh tempo</th>
                  <th>Status</th>
                  <th className="align-right">Total</th>
                  <th className="align-right">Sisa</th>
                  <th>Pembayaran</th>
                  <th className="align-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((order) => (
                  <tr key={order.order_id}>
                    <td>
                      <Link className="text-link" to={`/orders/${order.order_id}`}>
                        #{order.order_id}
                      </Link>
                    </td>
                    <td>{order.customer?.name || '—'}</td>
                    <td>{formatDate(order.order_date)}</td>
                    <td>
                      <span
                        className={
                          order.is_overdue ? 'text-danger' : undefined
                        }
                      >
                        {formatDate(order.due_date)}
                      </span>
                    </td>
                    <td>
                      <span className={STATUS_BADGE[order.status] || 'badge'}>
                        {order.status}
                      </span>
                    </td>
                    <td className="align-right">
                      {formatRupiah(order.total_amount)}
                    </td>
                    <td className="align-right">
                      {formatRupiah(order.remaining_amount)}
                    </td>
                    <td>
                      {order.is_paid ? (
                        <span className="badge badge--success">Lunas</span>
                      ) : (
                        <span className="badge badge--muted">Belum lunas</span>
                      )}
                    </td>
                    <td className="align-right">
                      <Link
                        className="button button--ghost button--small"
                        to={`/orders/${order.order_id}`}
                      >
                        Detail
                        <ArrowRightIcon size={15} />
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {meta && meta.last_page > 1 && (
          <div className="pagination">
            <button
              type="button"
              className="button button--ghost"
              disabled={page <= 1}
              onClick={() => setPage((current) => Math.max(1, current - 1))}
            >
              <ArrowLeftIcon size={16} />
              Sebelumnya
            </button>
            <span>
              {meta.from}–{meta.to} dari {meta.total}
            </span>
            <button
              type="button"
              className="button button--ghost"
              disabled={page >= meta.last_page}
              onClick={() => setPage((current) => current + 1)}
            >
              Berikutnya
              <ArrowRightIcon size={16} />
            </button>
          </div>
        )}
      </div>

      {rows.length === 0 && !query.isLoading && !query.isError && (
        <div className="empty-hint">
          <OrderIcon size={18} />
          Pesanan baru akan tampil di sini setelah dibuat.
        </div>
      )}
    </section>
  )
}
