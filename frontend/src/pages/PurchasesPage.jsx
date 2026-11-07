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
  PlusIcon,
  PurchaseIcon,
  SearchIcon,
} from '../components/Icons'

const PER_PAGE = 10
const STATUS_OPTIONS = ['Dipesan', 'Diterima', 'Dibatalkan']

const STATUS_BADGE = {
  Dipesan: 'badge badge--warning',
  Diterima: 'badge badge--success',
  Dibatalkan: 'badge badge--danger',
}

export default function PurchasesPage() {
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
    queryKey: ['purchases', page, search, status],
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

      return apiRequest(`/purchases?${params.toString()}`)
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
          <h2>Pembelian</h2>
          <p>
            Catat pembelian bahan ke pemasok, lalu gudang menandai penerimaan
            agar stok bertambah lewat buku besar.
          </p>
        </div>

        {can('purchases', 'C') && (
          <Link className="button button--primary" to="/purchases/new">
            <PlusIcon size={17} />
            Buat pembelian
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
              placeholder="Cari nama pemasok..."
              aria-label="Cari pembelian"
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
                {meta.total} pembelian · halaman {meta.current_page} dari{' '}
                {meta.last_page}
              </span>
            )}
          </div>
        </div>

        {query.isLoading ? (
          <div className="panel-state">
            <span className="spinner spinner--dark" />
            Memuat pembelian...
          </div>
        ) : query.isError ? (
          <div className="panel-state panel-state--error">
            <AlertIcon size={18} />
            {query.error.message}
          </div>
        ) : rows.length === 0 ? (
          <div className="panel-state">
            Belum ada pembelian yang sesuai filter.
          </div>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>No.</th>
                  <th>Pemasok</th>
                  <th>Tanggal</th>
                  <th>Status</th>
                  <th className="align-right">Item</th>
                  <th className="align-right">Total</th>
                  <th className="align-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((purchase) => (
                  <tr key={purchase.purchase_id}>
                    <td>
                      <Link
                        className="text-link"
                        to={`/purchases/${purchase.purchase_id}`}
                      >
                        #{purchase.purchase_id}
                      </Link>
                    </td>
                    <td>{purchase.supplier?.name || '—'}</td>
                    <td>{formatDate(purchase.purchase_date)}</td>
                    <td>
                      <span
                        className={
                          STATUS_BADGE[purchase.status] || 'badge'
                        }
                      >
                        {purchase.status}
                      </span>
                    </td>
                    <td className="align-right">{purchase.details_count}</td>
                    <td className="align-right">
                      {formatRupiah(purchase.total_amount)}
                    </td>
                    <td className="align-right">
                      <Link
                        className="button button--ghost button--small"
                        to={`/purchases/${purchase.purchase_id}`}
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
          <PurchaseIcon size={18} />
          Pembelian baru akan tampil di sini setelah dibuat.
        </div>
      )}
    </section>
  )
}
