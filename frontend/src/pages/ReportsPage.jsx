import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import {
  AlertIcon,
  DownloadIcon,
  ReportIcon,
} from '../components/Icons'
import { useAuth } from '../context/useAuth'
import { apiDownload, apiRequest } from '../lib/api'
import { formatDate, formatDateTime, formatNumber, formatRupiah } from '../lib/format'

const REPORT_DEFINITIONS = [
  {
    key: 'sales',
    label: 'Penjualan',
    description:
      'Rekap nilai pesanan, pembayaran diterima, dan sisa tagihan per periode.',
    roles: ['owner', 'admin'],
  },
  {
    key: 'purchases',
    label: 'Pembelian',
    description:
      'Rekap pembelian bahan per pemasok, status, dan nilai transaksi.',
    roles: ['owner', 'admin'],
  },
  {
    key: 'stock-card',
    label: 'Kartu Stok',
    description:
      'Mutasi stok bahan dari penerimaan pembelian dan serah bahan produksi.',
    roles: ['owner', 'admin', 'gudang'],
  },
  {
    key: 'productivity',
    label: 'Produktivitas',
    description:
      'Jumlah sesi dan kuantitas selesai karyawan pada setiap tahap produksi.',
    roles: ['owner', 'admin', 'produksi'],
  },
]

const ORDER_STATUS_OPTIONS = ['Baru', 'Diproses', 'Selesai', 'Dibatalkan']
const PURCHASE_STATUS_OPTIONS = ['Dipesan', 'Diterima', 'Dibatalkan']
const MOVEMENT_TYPE_OPTIONS = ['MASUK', 'KELUAR']
const STAGE_OPTIONS = [
  { value: 'CUTTING', label: 'Cutting' },
  { value: 'SEWING', label: 'Sewing' },
  { value: 'FINISHING', label: 'Finishing' },
  { value: 'QC', label: 'QC' },
]

const STATUS_BADGE = {
  Baru: 'badge badge--muted',
  Diproses: 'badge badge--warning',
  Selesai: 'badge badge--success',
  Dibatalkan: 'badge badge--danger',
  Dipesan: 'badge badge--warning',
  Diterima: 'badge badge--success',
  MASUK: 'badge badge--success',
  KELUAR: 'badge badge--warning',
}

const EMPTY_FILTERS = {
  date_from: '',
  date_to: '',
  status: '',
  material_id: '',
  movement_type: '',
  employee_id: '',
  stage_code: '',
}

function allowedReports(roleNames = []) {
  return REPORT_DEFINITIONS.filter((report) =>
    report.roles.some((role) => roleNames.includes(role)),
  )
}

export default function ReportsPage() {
  const { user } = useAuth()
  const reports = allowedReports(user?.role_names || [])
  const [tab, setTab] = useState(reports[0]?.key || 'sales')
  const [filters, setFilters] = useState(EMPTY_FILTERS)
  const [exporting, setExporting] = useState(null)
  const [exportError, setExportError] = useState('')

  const activeReport =
    reports.find((report) => report.key === tab) || reports[0]

  const materialsQuery = useQuery({
    queryKey: ['materials', 'options'],
    queryFn: () => apiRequest('/materials?per_page=100'),
    enabled: tab === 'stock-card',
  })

  const employeesQuery = useQuery({
    queryKey: ['employees', 'options'],
    queryFn: () => apiRequest('/employees?per_page=100'),
    enabled: tab === 'productivity',
  })

  const params = buildParams(tab, filters)

  const reportQuery = useQuery({
    queryKey: ['report', tab, params],
    queryFn: () => apiRequest(`/reports/${tab}?${params}`),
    enabled: Boolean(activeReport),
  })

  const report = reportQuery.data?.data
  const summary = report?.summary || {}
  const rows = report?.rows || []
  const materials = materialsQuery.data?.data || []
  const employees = employeesQuery.data?.data || []

  function changeFilter(event) {
    const { name, value } = event.target

    setFilters((current) => ({ ...current, [name]: value }))
  }

  function resetFilters() {
    setFilters(EMPTY_FILTERS)
    setExportError('')
  }

  function changeTab(nextTab) {
    setTab(nextTab)
    setExportError('')
  }

  async function exportReport(format) {
    setExporting(format)
    setExportError('')

    try {
      const exportParams = new URLSearchParams(params)
      exportParams.set('format', format)

      const { blob, filename } = await apiDownload(
        `/reports/${tab}/export?${exportParams.toString()}`,
      )
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')

      link.href = url
      link.download = filename
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)
    } catch (error) {
      setExportError(error.message)
    } finally {
      setExporting(null)
    }
  }

  if (!activeReport) {
    return (
      <section className="page-stack">
        <div className="banner banner--error" role="alert">
          Peran Anda tidak memiliki akses ke laporan.
        </div>
      </section>
    )
  }

  return (
    <section className="page-stack">
      <div className="page-heading">
        <div>
          <h2>Laporan Manajemen</h2>
          <p>
            Pilih jenis laporan, atur periode atau filter, lalu ekspor data
            sebagai CSV atau PDF.
          </p>
        </div>
        <span className="badge badge--muted">
          <ReportIcon size={14} />
          Fase 6
        </span>
      </div>

      <div className="report-tabs" role="tablist" aria-label="Jenis laporan">
        {reports.map((reportItem) => (
          <button
            key={reportItem.key}
            type="button"
            role="tab"
            aria-selected={tab === reportItem.key}
            className={
              tab === reportItem.key
                ? 'report-tab is-active'
                : 'report-tab'
            }
            onClick={() => changeTab(reportItem.key)}
          >
            {reportItem.label}
          </button>
        ))}
      </div>

      <div className="panel">
        <div className="panel-heading">
          <div>
            <h3>{activeReport.label}</h3>
            <p>{activeReport.description}</p>
          </div>
          <div className="report-export-actions">
            <button
              type="button"
              className="button button--ghost button--small"
              disabled={exporting !== null}
              onClick={() => exportReport('csv')}
            >
              <DownloadIcon size={15} />
              {exporting === 'csv' ? 'Menyiapkan...' : 'Ekspor CSV'}
            </button>
            <button
              type="button"
              className="button button--primary button--small"
              disabled={exporting !== null}
              onClick={() => exportReport('pdf')}
            >
              <DownloadIcon size={15} />
              {exporting === 'pdf' ? 'Menyiapkan...' : 'Ekspor PDF'}
            </button>
          </div>
        </div>

        <div className="panel-toolbar">
          <div className="toolbar-filters">
            <label className="filter-field">
              Dari
              <input
                type="date"
                name="date_from"
                value={filters.date_from}
                onChange={changeFilter}
                aria-label="Tanggal mulai laporan"
              />
            </label>

            <label className="filter-field">
              Sampai
              <input
                type="date"
                name="date_to"
                value={filters.date_to}
                onChange={changeFilter}
                aria-label="Tanggal akhir laporan"
              />
            </label>

            {(tab === 'sales' || tab === 'purchases') && (
              <label className="filter-field">
                Status
                <select
                  name="status"
                  value={filters.status}
                  onChange={changeFilter}
                >
                  <option value="">Semua status</option>
                  {(tab === 'sales'
                    ? ORDER_STATUS_OPTIONS
                    : PURCHASE_STATUS_OPTIONS
                  ).map((option) => (
                    <option key={option} value={option}>
                      {option}
                    </option>
                  ))}
                </select>
              </label>
            )}

            {tab === 'stock-card' && (
              <>
                <label className="filter-field">
                  Bahan
                  <select
                    name="material_id"
                    value={filters.material_id}
                    onChange={changeFilter}
                  >
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
                  <select
                    name="movement_type"
                    value={filters.movement_type}
                    onChange={changeFilter}
                  >
                    <option value="">Semua tipe</option>
                    {MOVEMENT_TYPE_OPTIONS.map((option) => (
                      <option key={option} value={option}>
                        {option}
                      </option>
                    ))}
                  </select>
                </label>
              </>
            )}

            {tab === 'productivity' && (
              <>
                <label className="filter-field">
                  Karyawan
                  <select
                    name="employee_id"
                    value={filters.employee_id}
                    onChange={changeFilter}
                  >
                    <option value="">Semua karyawan</option>
                    {employees.map((employee) => (
                      <option
                        key={employee.employee_id}
                        value={employee.employee_id}
                      >
                        {employee.name}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="filter-field">
                  Tahap
                  <select
                    name="stage_code"
                    value={filters.stage_code}
                    onChange={changeFilter}
                  >
                    <option value="">Semua tahap</option>
                    {STAGE_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </label>
              </>
            )}

            <button
              type="button"
              className="button button--ghost button--small"
              onClick={resetFilters}
            >
              Reset
            </button>
          </div>

          {report && (
            <span className="panel-meta">
              {report.period?.date_from || 'awal data'} s.d.{' '}
              {report.period?.date_to || 'akhir data'}
            </span>
          )}
        </div>

        {exportError && (
          <div className="banner banner--error" role="alert">
            {exportError}
          </div>
        )}

        {reportQuery.isLoading ? (
          <div className="panel-state">
            <span className="spinner spinner--dark" />
            Memuat laporan...
          </div>
        ) : reportQuery.isError ? (
          <div className="panel-state panel-state--error">
            <AlertIcon size={18} />
            {reportQuery.error.message}
          </div>
        ) : (
          <>
            <SummaryCards tab={tab} summary={summary} />

            {rows.length === 0 ? (
              <div className="panel-state">
                Tidak ada data pada periode atau filter ini.
              </div>
            ) : (
              <ReportTable
                tab={tab}
                rows={rows}
                material={summary.material}
              />
            )}
          </>
        )}
      </div>

      <p className="report-footnote">
        Laporan mengikuti data transaksi yang sudah tercatat. Untuk analisis
        lebih lanjut, gunakan ekspor CSV. PDF memuat ringkasan tabel yang siap
        dicetak. <Link className="text-link" to="/stock-movements">Buka kartu stok</Link>
      </p>
    </section>
  )
}

function buildParams(tab, filters) {
  const params = new URLSearchParams()

  if (filters.date_from) {
    params.set('date_from', filters.date_from)
  }

  if (filters.date_to) {
    params.set('date_to', filters.date_to)
  }

  if ((tab === 'sales' || tab === 'purchases') && filters.status) {
    params.set('status', filters.status)
  }

  if (tab === 'stock-card') {
    if (filters.material_id) {
      params.set('material_id', filters.material_id)
    }

    if (filters.movement_type) {
      params.set('movement_type', filters.movement_type)
    }
  }

  if (tab === 'productivity') {
    if (filters.employee_id) {
      params.set('employee_id', filters.employee_id)
    }

    if (filters.stage_code) {
      params.set('stage_code', filters.stage_code)
    }
  }

  return params.toString()
}

function SummaryCards({ tab, summary }) {
  const cards = []

  if (tab === 'sales') {
    cards.push(
      { label: 'Total pesanan', value: formatNumber(summary.order_count || 0) },
      { label: 'Nilai penjualan', value: formatRupiah(summary.total_sales || 0) },
      {
        label: 'Pembayaran diterima',
        value: formatRupiah(summary.paid_amount || 0),
      },
      { label: 'Sisa tagihan', value: formatRupiah(summary.remaining_amount || 0) },
    )
  }

  if (tab === 'purchases') {
    cards.push(
      {
        label: 'Total pembelian',
        value: formatNumber(summary.purchase_count || 0),
      },
      {
        label: 'Nilai pembelian',
        value: formatRupiah(summary.total_purchases || 0),
      },
      { label: 'Diterima', value: formatRupiah(summary.received_total || 0) },
      { label: 'Dipesan', value: formatRupiah(summary.ordered_total || 0) },
    )
  }

  if (tab === 'stock-card') {
    cards.push(
      { label: 'Jumlah mutasi', value: formatNumber(summary.movement_count || 0) },
      { label: 'Total masuk', value: formatNumber(summary.total_in || 0) },
      { label: 'Total keluar', value: formatNumber(summary.total_out || 0) },
      {
        label: 'Stok akhir',
        value:
          summary.material === null
            ? '—'
            : formatNumber(summary.closing_stock || 0),
      },
    )
  }

  if (tab === 'productivity') {
    cards.push(
      { label: 'Karyawan', value: formatNumber(summary.employee_count || 0) },
      { label: 'Sesi kerja', value: formatNumber(summary.session_count || 0) },
      {
        label: 'Sesi selesai',
        value: formatNumber(summary.completed_sessions || 0),
      },
      {
        label: 'Qty selesai',
        value: formatNumber(summary.completed_quantity || 0),
      },
    )
  }

  return (
    <div className="stat-grid report-summary">
      {cards.map((card) => (
        <article className="stat-card" key={card.label}>
          <div>
            <span className="stat-value">{card.value}</span>
            <span className="stat-label">{card.label}</span>
          </div>
        </article>
      ))}
    </div>
  )
}

function ReportTable({ tab, rows, material }) {
  if (tab === 'sales') {
    return (
      <div className="table-wrap">
        <table className="data-table">
          <thead>
            <tr>
              <th>No Pesanan</th>
              <th>Tanggal</th>
              <th>Jatuh Tempo</th>
              <th>Pelanggan</th>
              <th>Status</th>
              <th className="align-right">Total</th>
              <th className="align-right">Dibayar</th>
              <th className="align-right">Sisa</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.order_id}>
                <td>
                  <Link className="text-link" to={`/orders/${row.order_id}`}>
                    #{row.order_id}
                  </Link>
                </td>
                <td>{formatDate(row.order_date)}</td>
                <td>{formatDate(row.due_date)}</td>
                <td>{row.customer || '—'}</td>
                <td>
                  <span className={STATUS_BADGE[row.status] || 'badge'}>
                    {row.status}
                  </span>
                </td>
                <td className="align-right">{formatRupiah(row.total_amount)}</td>
                <td className="align-right">{formatRupiah(row.paid_amount)}</td>
                <td className="align-right">
                  {formatRupiah(row.remaining_amount)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )
  }

  if (tab === 'purchases') {
    return (
      <div className="table-wrap">
        <table className="data-table">
          <thead>
            <tr>
              <th>No Pembelian</th>
              <th>Tanggal</th>
              <th>Pemasok</th>
              <th>Status</th>
              <th className="align-right">Item</th>
              <th className="align-right">Total</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.purchase_id}>
                <td>
                  <Link
                    className="text-link"
                    to={`/purchases/${row.purchase_id}`}
                  >
                    #{row.purchase_id}
                  </Link>
                </td>
                <td>{formatDate(row.purchase_date)}</td>
                <td>{row.supplier || '—'}</td>
                <td>
                  <span className={STATUS_BADGE[row.status] || 'badge'}>
                    {row.status}
                  </span>
                </td>
                <td className="align-right">{formatNumber(row.item_count)}</td>
                <td className="align-right">{formatRupiah(row.total_amount)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )
  }

  if (tab === 'stock-card') {
    return (
      <div className="table-wrap">
        <table className="data-table">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Bahan</th>
              <th>Tipe</th>
              <th className="align-right">Jumlah</th>
              <th className="align-right">Saldo</th>
              <th>Sumber</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.movement_id}>
                <td>{formatDateTime(row.movement_date)}</td>
                <td>
                  <strong>{row.material_name || '—'}</strong>
                  {row.unit_name && (
                    <small className="order-line-note">{row.unit_name}</small>
                  )}
                </td>
                <td>
                  <span className={STATUS_BADGE[row.movement_type] || 'badge'}>
                    {row.movement_type}
                  </span>
                </td>
                <td className="align-right">
                  <span
                    className={
                      row.movement_type === 'MASUK'
                        ? 'movement-in'
                        : 'movement-out'
                    }
                  >
                    {row.movement_type === 'MASUK' ? '+' : '−'}
                    {formatNumber(row.quantity)}
                  </span>
                </td>
                <td className="align-right">
                  {row.balance === null ? '—' : formatNumber(row.balance)}
                </td>
                <td>
                  {row.purchase_id ? (
                    <Link className="text-link" to={`/purchases/${row.purchase_id}`}>
                      Pembelian #{row.purchase_id}
                    </Link>
                  ) : row.production_order_id ? (
                    <Link
                      className="text-link"
                      to={`/production-orders/${row.production_order_id}`}
                    >
                      Produksi #{row.production_order_id}
                    </Link>
                  ) : (
                    '—'
                  )}
                </td>
                <td>{row.notes || '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {material && (
          <p className="report-table-note">
            Saldo dihitung dari stok awal bahan {material.material_name} untuk
            periode yang dipilih.
          </p>
        )}
      </div>
    )
  }

  return (
    <div className="table-wrap">
      <table className="data-table">
        <thead>
          <tr>
            <th>Karyawan</th>
            <th>Jabatan</th>
            <th>Tahap</th>
            <th className="align-right">Sesi</th>
            <th className="align-right">Selesai</th>
            <th className="align-right">Qty Selesai</th>
            <th className="align-right">Durasi</th>
            <th>Selesai Terakhir</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={`${row.employee_id}-${row.stage_code}`}>
              <td>{row.employee_name || '—'}</td>
              <td>{row.position || '—'}</td>
              <td>
                <span className="badge badge--muted">{row.stage_label}</span>
              </td>
              <td className="align-right">{formatNumber(row.sessions)}</td>
              <td className="align-right">
                {formatNumber(row.completed_sessions)}
              </td>
              <td className="align-right">
                {formatNumber(row.completed_quantity)}
              </td>
              <td className="align-right">
                {formatNumber(row.duration_minutes)} menit
              </td>
              <td>{formatDateTime(row.last_completed_at)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
