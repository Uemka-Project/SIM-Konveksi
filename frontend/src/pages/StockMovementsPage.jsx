import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router-dom'
import { apiRequest } from '../lib/api'
import { formatDateTime, formatNumber } from '../lib/format'
import {
  AlertIcon,
  AlertTriangleIcon,
  ArrowLeftIcon,
  ArrowRightIcon,
  SearchIcon,
  StockIcon,
} from '../components/Icons'

const PER_PAGE = 15
const TYPE_OPTIONS = ['MASUK', 'KELUAR']

const TYPE_BADGE = {
  MASUK: 'badge badge--success',
  KELUAR: 'badge badge--warning',
}

export default function StockMovementsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const materialId = searchParams.get('material_id') || ''
  const [type, setType] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [page, setPage] = useState(1)

  const materialsQuery = useQuery({
    queryKey: ['materials', 'options'],
    queryFn: () => apiRequest('/materials?per_page=100'),
  })

  const lowStockQuery = useQuery({
    queryKey: ['materials', 'low-stock'],
    queryFn: () => apiRequest('/materials/low-stock'),
  })

  const movementsQuery = useQuery({
    queryKey: ['stock-movements', page, materialId, type, dateFrom, dateTo],
    queryFn: () => {
      const params = new URLSearchParams({
        page: String(page),
        per_page: String(PER_PAGE),
      })

      if (materialId) {
        params.set('material_id', materialId)
      }

      if (type) {
        params.set('movement_type', type)
      }

      if (dateFrom) {
        params.set('date_from', dateFrom)
      }

      if (dateTo) {
        params.set('date_to', dateTo)
      }

      return apiRequest(`/stock-movements?${params.toString()}`)
    },
  })

  const materials = materialsQuery.data?.data || []
  const lowStock = lowStockQuery.data?.data || []
  const rows = movementsQuery.data?.data || []
  const meta = movementsQuery.data?.meta

  function changeMaterial(event) {
    const next = new URLSearchParams(searchParams)

    if (event.target.value) {
      next.set('material_id', event.target.value)
    } else {
      next.delete('material_id')
    }

    setSearchParams(next, { replace: true })
    setPage(1)
  }

  function changeType(event) {
    setType(event.target.value)
    setPage(1)
  }

  function changeDateFrom(event) {
    setDateFrom(event.target.value)
    setPage(1)
  }

  function changeDateTo(event) {
    setDateTo(event.target.value)
    setPage(1)
  }

  function resetFilters() {
    setSearchParams({}, { replace: true })
    setType('')
    setDateFrom('')
    setDateTo('')
    setPage(1)
  }

  const hasFilters = materialId || type || dateFrom || dateTo

  return (
    <section className="page-stack">
      <div className="page-heading">
        <div>
          <h2>Stok &amp; Kartu Stok</h2>
          <p>
            Mutasi stok hanya dibuat lewat penerimaan pembelian atau serah
            bahan produksi. Stok bahan tidak diedit langsung dari menu ini.
          </p>
        </div>

        <Link className="button button--ghost" to="/materials">
          <StockIcon size={16} />
          Daftar bahan
        </Link>
      </div>

      <div className="panel">
        <div className="panel-heading">
          <div>
            <h3>Peringatan Stok Minimum</h3>
            <p>Bahan yang stoknya sudah di bawah batas minimum.</p>
          </div>
          {lowStock.length > 0 && (
            <span className="badge badge--danger">
              {lowStock.length} bahan menipis
            </span>
          )}
        </div>

        {lowStockQuery.isLoading ? (
          <div className="panel-state">
            <span className="spinner spinner--dark" />
            Memuat peringatan stok...
          </div>
        ) : lowStockQuery.isError ? (
          <div className="panel-state panel-state--error">
            <AlertIcon size={18} />
            {lowStockQuery.error.message}
          </div>
        ) : lowStock.length === 0 ? (
          <div className="panel-state">
            Semua bahan berada pada atau di atas stok minimum.
          </div>
        ) : (
          <div className="stock-alert-list">
            {lowStock.map((material) => (
              <Link
                key={material.material_id}
                className="stock-alert-item"
                to={`/stock-movements?material_id=${material.material_id}`}
              >
                <AlertTriangleIcon size={17} />
                <span>
                  <strong>{material.material_name}</strong>
                  <small>
                    stok {formatNumber(material.current_stock)}{' '}
                    {material.unit_name} · minimum{' '}
                    {formatNumber(material.minimum_stock)}{' '}
                    {material.unit_name}
                  </small>
                </span>
                <span className="badge badge--danger">Menipis</span>
              </Link>
            ))}
          </div>
        )}
      </div>

      <div className="panel">
        <div className="panel-toolbar">
          <div className="toolbar-filters">
            <label className="filter-field">
              Bahan
              <select value={materialId} onChange={changeMaterial}>
                <option value="">Semua bahan</option>
                {materials.map((material) => (
                  <option
                    key={material.material_id}
                    value={material.material_id}
                  >
                    {material.material_name}
                  </option>
                ))}
              </select>
            </label>

            <label className="filter-field">
              Tipe
              <select value={type} onChange={changeType}>
                <option value="">Semua tipe</option>
                {TYPE_OPTIONS.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </label>

            <label className="filter-field">
              Dari
              <input
                type="date"
                value={dateFrom}
                onChange={changeDateFrom}
                aria-label="Tanggal mulai"
              />
            </label>

            <label className="filter-field">
              Sampai
              <input
                type="date"
                value={dateTo}
                onChange={changeDateTo}
                aria-label="Tanggal akhir"
              />
            </label>

            {hasFilters && (
              <button
                type="button"
                className="button button--ghost button--small"
                onClick={resetFilters}
              >
                Reset
              </button>
            )}
          </div>

          {meta && (
            <span className="panel-meta">
              {meta.total} mutasi · halaman {meta.current_page} dari{' '}
              {meta.last_page}
            </span>
          )}
        </div>

        {movementsQuery.isLoading ? (
          <div className="panel-state">
            <span className="spinner spinner--dark" />
            Memuat mutasi stok...
          </div>
        ) : movementsQuery.isError ? (
          <div className="panel-state panel-state--error">
            <AlertIcon size={18} />
            {movementsQuery.error.message}
          </div>
        ) : rows.length === 0 ? (
          <div className="panel-state">
            Belum ada mutasi stok yang sesuai filter.
          </div>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Bahan</th>
                  <th>Tipe</th>
                  <th className="align-right">Jumlah</th>
                  <th>Sumber</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((movement) => (
                  <tr key={movement.movement_id}>
                    <td>{formatDateTime(movement.movement_date)}</td>
                    <td>
                      <strong>{movement.material?.material_name || '—'}</strong>
                      {movement.material?.unit_name && (
                        <small className="order-line-note">
                          {movement.material.unit_name}
                        </small>
                      )}
                    </td>
                    <td>
                      <span
                        className={
                          TYPE_BADGE[movement.movement_type] || 'badge'
                        }
                      >
                        {movement.movement_type}
                      </span>
                    </td>
                    <td className="align-right">
                      <span
                        className={
                          movement.movement_type === 'MASUK'
                            ? 'movement-in'
                            : 'movement-out'
                        }
                      >
                        {movement.movement_type === 'MASUK' ? '+' : '−'}
                        {formatNumber(movement.quantity)}
                      </span>
                    </td>
                    <td>
                      {movement.purchase_id ? (
                        <Link
                          className="text-link"
                          to={`/purchases/${movement.purchase_id}`}
                        >
                          Pembelian #{movement.purchase_id}
                        </Link>
                      ) : movement.production_order_id ? (
                        <span>Produksi #{movement.production_order_id}</span>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td>{movement.notes || '—'}</td>
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

      {rows.length === 0 &&
        !movementsQuery.isLoading &&
        !movementsQuery.isError && (
          <div className="empty-hint">
            <SearchIcon size={18} />
            Mutasi muncul setelah pembelian diterima atau bahan diserahkan ke
            produksi.
          </div>
        )}
    </section>
  )
}
