import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useLocation } from 'react-router-dom'
import { apiRequest } from '../lib/api'
import { formatDate } from '../lib/format'
import {
  AlertIcon,
  ArrowLeftIcon,
  ArrowRightIcon,
  ProductionIcon,
  SearchIcon,
} from '../components/Icons'

const PER_PAGE = 10
const STATUS_OPTIONS = ['Menunggu', 'Berjalan', 'Selesai']

const STATUS_BADGE = {
  Menunggu: 'badge badge--muted',
  Berjalan: 'badge badge--warning',
  Selesai: 'badge badge--success',
}

export default function ProductionOrdersPage() {
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
    queryKey: ['production-orders', page, search, status],
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

      return apiRequest(`/production-orders?${params.toString()}`)
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
          <h2>Perintah Produksi</h2>
          <p>
            SPK dibuat per item pesanan dan boleh dipecah menjadi beberapa
            batch. Gudang menyerahkan bahan, lalu produksi mencatat tahap
            cutting sampai QC.
          </p>
        </div>
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
              placeholder="Cari pelanggan, produk, SKU, atau nomor SPK..."
              aria-label="Cari perintah produksi"
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
                {meta.total} perintah · halaman {meta.current_page} dari{' '}
                {meta.last_page}
              </span>
            )}
          </div>
        </div>

        {query.isLoading ? (
          <div className="panel-state">
            <span className="spinner spinner--dark" />
            Memuat perintah produksi...
          </div>
        ) : query.isError ? (
          <div className="panel-state panel-state--error">
            <AlertIcon size={18} />
            {query.error.message}
          </div>
        ) : rows.length === 0 ? (
          <div className="panel-state">
            Belum ada perintah produksi yang sesuai filter.
          </div>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>SPK</th>
                  <th>Pesanan</th>
                  <th>Pelanggan</th>
                  <th>Produk</th>
                  <th className="align-right">Qty rencana</th>
                  <th>Status</th>
                  <th>Target</th>
                  <th className="align-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((productionOrder) => (
                  <tr key={productionOrder.production_order_id}>
                    <td>
                      <Link
                        className="text-link"
                        to={`/production-orders/${productionOrder.production_order_id}`}
                      >
                        #{productionOrder.production_order_id}
                      </Link>
                    </td>
                    <td>
                      {productionOrder.order ? (
                        <Link
                          className="text-link"
                          to={`/orders/${productionOrder.order.order_id}`}
                        >
                          #{productionOrder.order.order_id}
                        </Link>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td>{productionOrder.order?.customer?.name || '—'}</td>
                    <td>
                      <div className="production-product-cell">
                        <strong>
                          {productionOrder.item?.product_name || '—'}
                        </strong>
                        <small>
                          {productionOrder.item?.sku || '—'} ·{' '}
                          {productionOrder.item?.size} /{' '}
                          {productionOrder.item?.color}
                        </small>
                      </div>
                    </td>
                    <td className="align-right">
                      {productionOrder.planned_quantity}
                    </td>
                    <td>
                      <span
                        className={
                          STATUS_BADGE[productionOrder.status] || 'badge'
                        }
                      >
                        {productionOrder.status}
                      </span>
                    </td>
                    <td>{formatDate(productionOrder.target_date)}</td>
                    <td className="align-right">
                      <Link
                        className="button button--ghost button--small"
                        to={`/production-orders/${productionOrder.production_order_id}`}
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
          <ProductionIcon size={18} />
          Perintah produksi dibuat dari halaman detail pesanan setelah DP
          dicatat.
        </div>
      )}
    </section>
  )
}
