import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useLocation, useParams } from 'react-router-dom'
import { useAuth } from '../context/useAuth'
import { apiRequest } from '../lib/api'
import {
  formatDate,
  formatDateTime,
  formatNumber,
} from '../lib/format'
import {
  AlertIcon,
  ArrowLeftIcon,
  CheckIcon,
  ClockIcon,
  EditIcon,
  PlusIcon,
  ProductionIcon,
} from '../components/Icons'

const STATUS_BADGE = {
  Menunggu: 'badge badge--muted',
  Berjalan: 'badge badge--warning',
  Selesai: 'badge badge--success',
}

const PROGRESS_BADGE = {
  Proses: 'badge badge--warning',
  Selesai: 'badge badge--success',
}

const STAGES = [
  { code: 'CUTTING', label: 'Cutting', description: 'Pemotongan bahan' },
  { code: 'SEWING', label: 'Sewing', description: 'Penjahitan' },
  { code: 'FINISHING', label: 'Finishing', description: 'Penyelesaian' },
  { code: 'QC', label: 'Quality Control', description: 'Pemeriksaan akhir' },
]

function pad(value) {
  return String(value).padStart(2, '0')
}

function nowLocalInput() {
  const date = new Date()

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
    date.getDate(),
  )}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function toLocalInput(value) {
  if (!value) {
    return ''
  }

  return String(value).replace(' ', 'T').slice(0, 16)
}

export default function ProductionOrderDetailPage() {
  const { productionOrderId } = useParams()
  const location = useLocation()
  const queryClient = useQueryClient()
  const { can, user } = useAuth()
  const [feedback, setFeedback] = useState(location.state?.feedback || null)
  const [error, setError] = useState('')
  const [progressModal, setProgressModal] = useState(null)
  const [progressForm, setProgressForm] = useState({
    employee_id: '',
    stage_code: 'CUTTING',
    status: 'Proses',
    started_at: nowLocalInput(),
    completed_at: '',
    completed_quantity: '',
  })

  const productionQuery = useQuery({
    queryKey: ['production-order', productionOrderId],
    queryFn: () => apiRequest(`/production-orders/${productionOrderId}`),
  })

  const production = productionQuery.data?.data
  const isProductionRole = Boolean(user?.role_names?.includes('produksi'))
  const canIssueMaterials =
    can('production', 'U') &&
    (can('stock', 'C') || can('production', 'D'))
  const canRecordProgress =
    can('production', 'U') && (can('production', 'D') || isProductionRole)

  const requirementsQuery = useQuery({
    queryKey: ['production-order', productionOrderId, 'requirements'],
    queryFn: () =>
      apiRequest(`/production-orders/${productionOrderId}/requirements`),
    enabled: productionQuery.isSuccess,
  })

  const employeesQuery = useQuery({
    queryKey: ['employees', 'production-options'],
    queryFn: () => apiRequest('/employees?per_page=100&active_status=1'),
    enabled: canRecordProgress,
  })

  useEffect(() => {
    if (!feedback) {
      return undefined
    }

    const timer = window.setTimeout(() => setFeedback(null), 4500)

    return () => window.clearTimeout(timer)
  }, [feedback])

  const requirements = requirementsQuery.data?.data
  const progressRows = production?.progress || []
  const stockMovements = production?.stock_movements || []
  const employees = employeesQuery.data?.data || []

  function invalidateProduction() {
    queryClient.invalidateQueries({
      queryKey: ['production-order', productionOrderId],
    })
    queryClient.invalidateQueries({
      queryKey: ['production-order', productionOrderId, 'requirements'],
    })
    queryClient.invalidateQueries({ queryKey: ['production-orders'] })
    queryClient.invalidateQueries({ queryKey: ['stock-movements'] })
    queryClient.invalidateQueries({ queryKey: ['materials'] })
    queryClient.invalidateQueries({ queryKey: ['materials', 'low-stock'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })

    if (production?.order?.order_id) {
      queryClient.invalidateQueries({
        queryKey: ['order', String(production.order.order_id)],
      })
      queryClient.invalidateQueries({ queryKey: ['orders'] })
    }
  }

  function handleError(requestError) {
    const firstError = Object.values(requestError.errors || {})[0]

    setError(
      Array.isArray(firstError)
        ? firstError[0]
        : requestError.message || 'Terjadi kesalahan. Silakan coba lagi.',
    )
  }

  const issueMaterialsMutation = useMutation({
    mutationFn: () =>
      apiRequest(
        `/production-orders/${productionOrderId}/issue-materials`,
        { method: 'POST' },
      ),
    onSuccess: (response) => {
      invalidateProduction()
      setError('')
      setFeedback(response.message)
    },
    onError: handleError,
  })

  const progressMutation = useMutation({
    mutationFn: (payload) =>
      progressModal?.progress_id
        ? apiRequest(`/progress/${progressModal.progress_id}`, {
            method: 'PATCH',
            body: payload,
          })
        : apiRequest(
            `/production-orders/${productionOrderId}/progress`,
            { method: 'POST', body: payload },
          ),
    onSuccess: (response) => {
      invalidateProduction()
      setProgressModal(null)
      setError('')
      setFeedback(response.message)
    },
    onError: handleError,
  })

  if (productionQuery.isLoading) {
    return (
      <div className="panel-state">
        <span className="spinner spinner--dark" />
        Memuat detail perintah produksi...
      </div>
    )
  }

  if (productionQuery.isError) {
    return (
      <div className="banner banner--error" role="alert">
        <AlertIcon size={18} />
        {productionQuery.error.message}
      </div>
    )
  }

  const isWaiting = production.status === 'Menunggu'
  const isRunning = production.status === 'Berjalan'
  const canIssue = canIssueMaterials && isWaiting
  const canRecord = canRecordProgress && isRunning
  const nextStage =
    STAGES.find((stage) => {
      const rows = progressRows.filter(
        (row) => row.stage_code === stage.code,
      )

      return !rows.some((row) => row.status === 'Selesai')
    })?.code || 'CUTTING'

  function handleIssueMaterials() {
    if (
      !window.confirm(
        'Serahkan bahan untuk SPK ini? Stok bahan akan berkurang sesuai BOM dan status produksi menjadi Berjalan.',
      )
    ) {
      return
    }

    issueMaterialsMutation.mutate()
  }

  function openCreateProgress(stageCode) {
    setProgressModal({ mode: 'create', progress_id: null })
    setProgressForm({
      employee_id: '',
      stage_code: stageCode,
      status: 'Proses',
      started_at: nowLocalInput(),
      completed_at: '',
      completed_quantity: String(production.planned_quantity || ''),
    })
    setError('')
  }

  function openEditProgress(row, status = row.status) {
    setProgressModal({
      mode: 'edit',
      progress_id: row.progress_id,
      progress: row,
    })
    setProgressForm({
      employee_id: String(row.employee_id),
      stage_code: row.stage_code,
      status,
      started_at: toLocalInput(row.started_at),
      completed_at:
        status === 'Selesai'
          ? toLocalInput(row.completed_at) || nowLocalInput()
          : toLocalInput(row.completed_at),
      completed_quantity: String(
        row.completed_quantity || production.planned_quantity || '',
      ),
    })
    setError('')
  }

  function updateProgressField(name, value) {
    setProgressForm((current) => ({ ...current, [name]: value }))
    setError('')
  }

  function handleProgressSubmit(event) {
    event.preventDefault()
    setError('')

    if (!progressForm.employee_id) {
      setError('Karyawan wajib dipilih.')
      return
    }

    if (!progressForm.started_at) {
      setError('Waktu mulai wajib diisi.')
      return
    }

    if (progressForm.status === 'Selesai' && !progressForm.completed_at) {
      setError('Waktu selesai wajib diisi bila progres berstatus Selesai.')
      return
    }

    progressMutation.mutate({
      employee_id: Number(progressForm.employee_id),
      stage_code: progressForm.stage_code,
      status: progressForm.status,
      started_at: progressForm.started_at.replace('T', ' '),
      completed_at:
        progressForm.status === 'Selesai'
          ? progressForm.completed_at.replace('T', ' ')
          : null,
      completed_quantity:
        progressForm.completed_quantity === ''
          ? 0
          : Number(progressForm.completed_quantity),
    })
  }

  return (
    <section className="page-stack">
      <div>
        <Link className="text-link" to="/production-orders">
          <ArrowLeftIcon size={15} />
          Kembali ke daftar produksi
        </Link>
      </div>

      <div className="page-heading">
        <div>
          <div className="order-title-row">
            <h2>SPK #{production.production_order_id}</h2>
            <span className={STATUS_BADGE[production.status] || 'badge'}>
              {production.status}
            </span>
          </div>
          <p>
            {production.item?.product_name || 'Tanpa produk'} ·{' '}
            {production.item?.sku || '—'} · {production.item?.size} /{' '}
            {production.item?.color}
          </p>
        </div>

        <div className="page-heading-actions">
          {canIssue && (
            <button
              type="button"
              className="button button--primary"
              onClick={handleIssueMaterials}
              disabled={issueMaterialsMutation.isPending}
            >
              <ProductionIcon size={16} />
              {issueMaterialsMutation.isPending
                ? 'Memproses...'
                : 'Serahkan Bahan'}
            </button>
          )}

          {canRecord && (
            <button
              type="button"
              className="button button--primary"
              onClick={() => openCreateProgress(nextStage)}
            >
              <PlusIcon size={16} />
              Catat progres
            </button>
          )}
        </div>
      </div>

      {feedback && (
        <div className="banner banner--success" role="status">
          {feedback}
        </div>
      )}

      {error && (
        <div className="banner banner--error" role="alert">
          {error}
        </div>
      )}

      <div className="stat-grid">
        <article className="stat-card">
          <div>
            <span className="stat-value">{production.planned_quantity}</span>
            <span className="stat-label">Qty rencana batch</span>
          </div>
        </article>

        <article className="stat-card">
          <div>
            <span className="stat-value">
              {production.item?.order_quantity ?? '—'}
            </span>
            <span className="stat-label">Qty item pesanan</span>
          </div>
        </article>

        <article className="stat-card">
          <div>
            <span className="stat-value">{stockMovements.length}</span>
            <span className="stat-label">Baris bahan keluar</span>
          </div>
        </article>

        <article className="stat-card stat-card--accent">
          <div>
            <span className="stat-value">{production.status}</span>
            <span className="stat-label">Status produksi</span>
          </div>
        </article>
      </div>

      <div className="detail-grid">
        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Informasi SPK</h3>
              <p>Ringkasan perintah produksi.</p>
            </div>
          </div>

          <dl className="info-list">
            <div>
              <dt>Pesanan</dt>
              <dd>
                {production.order ? (
                  <Link
                    className="text-link"
                    to={`/orders/${production.order.order_id}`}
                  >
                    #{production.order.order_id}
                  </Link>
                ) : (
                  '—'
                )}
              </dd>
            </div>
            <div>
              <dt>Pelanggan</dt>
              <dd>{production.order?.customer?.name || '—'}</dd>
            </div>
            <div>
              <dt>Tanggal mulai</dt>
              <dd>{formatDate(production.start_date)}</dd>
            </div>
            <div>
              <dt>Target selesai</dt>
              <dd>{formatDate(production.target_date)}</dd>
            </div>
          </dl>
        </div>

        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Alur Produksi</h3>
              <p>
                Bahan diserahkan gudang saat SPK berstatus Menunggu. Setelah itu
                produksi mencatat progres per tahap, dan SPK otomatis Selesai
                ketika Cutting, Sewing, Finishing, dan QC masing-masing punya
                progres Selesai.
              </p>
            </div>
          </div>

          <div className="status-actions">
            <span className={STATUS_BADGE[production.status] || 'badge'}>
              {production.status}
            </span>

            <span className="muted-text">
              {isWaiting
                ? canIssueMaterials
                  ? 'Menunggu gudang menyerahkan bahan sesuai kebutuhan BOM.'
                  : 'Menunggu gudang menyerahkan bahan sebelum produksi berjalan.'
                : isRunning
                  ? 'Produksi berjalan. Catat setiap tahap dan tandai Selesai bila tuntas.'
                  : 'Seluruh tahap selesai; produk siap diserahkan lewat detail pesanan.'}
            </span>
          </div>
        </div>
      </div>

      <div className="panel">
        <div className="panel-heading">
          <div>
            <h3>Kebutuhan Bahan</h3>
            <p>
              Kebutuhan dihitung dari BOM varian dikali qty rencana batch ini.
            </p>
          </div>

          {requirements && (
            <span
              className={
                requirements.is_sufficient
                  ? 'badge badge--success'
                  : 'badge badge--danger'
              }
            >
              {requirements.is_sufficient
                ? 'Stok mencukupi'
                : 'Stok belum mencukupi'}
            </span>
          )}
        </div>

        {requirementsQuery.isLoading ? (
          <div className="panel-state">
            <span className="spinner spinner--dark" />
            Menghitung kebutuhan bahan...
          </div>
        ) : requirementsQuery.isError ? (
          <div className="panel-state panel-state--error">
            <AlertIcon size={18} />
            {requirementsQuery.error.message}
          </div>
        ) : !requirements || requirements.items.length === 0 ? (
          <div className="panel-state">
            Varian pada item pesanan ini belum memiliki komponen BOM, sehingga
            bahan tidak dapat diserahkan.
          </div>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Bahan</th>
                  <th className="align-right">Kebutuhan</th>
                  <th className="align-right">Stok saat ini</th>
                  <th className="align-right">Kekurangan</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {requirements.items.map((item) => (
                  <tr key={item.material_id}>
                    <td>{item.material_name}</td>
                    <td className="align-right">
                      {formatNumber(item.required_quantity)} {item.unit_name}
                    </td>
                    <td className="align-right">
                      {formatNumber(item.current_stock)} {item.unit_name}
                    </td>
                    <td className="align-right">
                      {item.shortage_quantity > 0
                        ? `${formatNumber(item.shortage_quantity)} ${item.unit_name}`
                        : '—'}
                    </td>
                    <td>
                      {item.is_sufficient ? (
                        <span className="badge badge--success">Cukup</span>
                      ) : (
                        <span className="badge badge--danger">Kurang</span>
                      )}
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
            <h3>Timeline Produksi</h3>
            <p>
              Urutan tahap Cutting → Sewing → Finishing → QC. Rework dicatat
              sebagai entri baru pada tahap yang sama.
            </p>
          </div>

          {canRecord && (
            <button
              type="button"
              className="button button--ghost button--small"
              onClick={() => openCreateProgress(nextStage)}
            >
              <PlusIcon size={15} />
              Catat progres
            </button>
          )}
        </div>

        {isWaiting && (
          <div className="panel-note">
            Timeline aktif setelah gudang menyerahkan bahan.
          </div>
        )}

        <div className="production-timeline">
          {STAGES.map((stage, index) => {
            const rows = progressRows.filter(
              (row) => row.stage_code === stage.code,
            )
            const isDone = rows.some((row) => row.status === 'Selesai')
            const latest = rows.length ? rows[rows.length - 1] : null
            const state = isDone ? 'done' : latest ? 'active' : 'idle'

            return (
              <article
                className={`timeline-step timeline-step--${state}`}
                key={stage.code}
              >
                <div className="timeline-marker" aria-hidden="true">
                  <span>
                    {isDone ? <CheckIcon size={16} /> : index + 1}
                  </span>
                </div>

                <div className="timeline-body">
                  <div className="timeline-head">
                    <div>
                      <h4>{stage.label}</h4>
                      <p>{stage.description}</p>
                    </div>
                    <span
                      className={
                        isDone
                          ? 'badge badge--success'
                          : latest
                            ? 'badge badge--warning'
                            : 'badge badge--muted'
                      }
                    >
                      {isDone ? 'Selesai' : latest ? latest.status : 'Belum mulai'}
                    </span>
                  </div>

                  {rows.length > 0 && (
                    <ul className="timeline-entries">
                      {rows.map((row) => (
                        <li key={row.progress_id}>
                          <span
                            className={
                              PROGRESS_BADGE[row.status] || 'badge'
                            }
                          >
                            {row.status}
                          </span>
                          <span>{row.employee?.name || '—'}</span>
                          <span className="muted-text">
                            <ClockIcon size={13} />{' '}
                            {formatDateTime(row.started_at)}
                            {row.completed_at
                              ? ` → ${formatDateTime(row.completed_at)}`
                              : ''}
                          </span>
                          <span>{row.completed_quantity} pcs</span>

                          {canRecord && (
                            <>
                              {row.status === 'Proses' && (
                                <button
                                  type="button"
                                  className="button button--ghost button--small"
                                  onClick={() =>
                                    openEditProgress(row, 'Selesai')
                                  }
                                >
                                  Selesaikan
                                </button>
                              )}
                              <button
                                type="button"
                                className="icon-button"
                                onClick={() => openEditProgress(row)}
                                title="Ubah progres"
                                aria-label="Ubah progres"
                              >
                                <EditIcon size={15} />
                              </button>
                            </>
                          )}
                        </li>
                      ))}
                    </ul>
                  )}

                  {canRecord && (
                    <div className="timeline-actions">
                      <button
                        type="button"
                        className="button button--ghost button--small"
                        onClick={() => openCreateProgress(stage.code)}
                      >
                        <PlusIcon size={14} />
                        Catat progres {stage.label}
                      </button>
                    </div>
                  )}
                </div>
              </article>
            )
          })}
        </div>
      </div>

      <div className="panel">
        <div className="panel-heading">
          <div>
            <h3>Bahan Keluar</h3>
            <p>
              Mutasi KELUAR yang tercatat saat gudang menyerahkan bahan untuk
              SPK ini.
            </p>
          </div>
        </div>

        {stockMovements.length === 0 ? (
          <div className="panel-state">
            Belum ada bahan yang diserahkan untuk SPK ini.
          </div>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Bahan</th>
                  <th className="align-right">Jumlah</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody>
                {stockMovements.map((movement) => (
                  <tr key={movement.movement_id}>
                    <td>{formatDateTime(movement.movement_date)}</td>
                    <td>{movement.material?.material_name || '—'}</td>
                    <td className="align-right">
                      {formatNumber(movement.quantity)}{' '}
                      {movement.material?.unit_name || ''}
                    </td>
                    <td>{movement.notes || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {progressModal && (
        <div className="modal-backdrop" role="presentation">
          <div className="modal" role="dialog" aria-modal="true">
            <div className="modal-header">
              <h3>
                {progressModal.mode === 'edit'
                  ? 'Ubah Progres Produksi'
                  : 'Catat Progres Produksi'}
              </h3>
              <button
                type="button"
                className="icon-button"
                onClick={() => setProgressModal(null)}
                aria-label="Tutup"
              >
                ✕
              </button>
            </div>

            <form className="modal-form" onSubmit={handleProgressSubmit}>
              {error && (
                <div className="banner banner--error" role="alert">
                  {error}
                </div>
              )}

              <div className="field">
                <label className="field-label" htmlFor="progress-employee">
                  Karyawan *
                </label>
                <select
                  id="progress-employee"
                  value={progressForm.employee_id}
                  onChange={(event) =>
                    updateProgressField('employee_id', event.target.value)
                  }
                  required
                >
                  <option value="">Pilih karyawan</option>
                  {employees.map((employee) => (
                    <option key={employee.employee_id} value={employee.employee_id}>
                      {employee.name} · {employee.position}
                    </option>
                  ))}
                </select>
                {employeesQuery.isLoading && (
                  <small className="field-help">Memuat karyawan...</small>
                )}
              </div>

              <div className="form-grid">
                <div className="field">
                  <label className="field-label" htmlFor="progress-stage">
                    Tahap *
                  </label>
                  <select
                    id="progress-stage"
                    value={progressForm.stage_code}
                    onChange={(event) =>
                      updateProgressField('stage_code', event.target.value)
                    }
                    required
                  >
                    {STAGES.map((stage) => (
                      <option key={stage.code} value={stage.code}>
                        {stage.label}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="field">
                  <label className="field-label" htmlFor="progress-status">
                    Status *
                  </label>
                  <select
                    id="progress-status"
                    value={progressForm.status}
                    onChange={(event) =>
                      updateProgressField('status', event.target.value)
                    }
                    required
                  >
                    <option value="Proses">Proses</option>
                    <option value="Selesai">Selesai</option>
                  </select>
                </div>
              </div>

              <div className="form-grid">
                <div className="field">
                  <label className="field-label" htmlFor="progress-started">
                    Waktu mulai *
                  </label>
                  <input
                    id="progress-started"
                    type="datetime-local"
                    value={progressForm.started_at}
                    onChange={(event) =>
                      updateProgressField('started_at', event.target.value)
                    }
                    required
                  />
                </div>

                <div className="field">
                  <label className="field-label" htmlFor="progress-completed">
                    Waktu selesai
                    {progressForm.status === 'Selesai' ? ' *' : ''}
                  </label>
                  <input
                    id="progress-completed"
                    type="datetime-local"
                    value={progressForm.completed_at}
                    onChange={(event) =>
                      updateProgressField('completed_at', event.target.value)
                    }
                    required={progressForm.status === 'Selesai'}
                  />
                  <small className="field-help">
                    Wajib diisi bila status Selesai dan tidak boleh lebih awal
                    dari waktu mulai.
                  </small>
                </div>
              </div>

              <div className="field">
                <label className="field-label" htmlFor="progress-quantity">
                  Jumlah selesai
                </label>
                <input
                  id="progress-quantity"
                  type="number"
                  min="0"
                  step="1"
                  value={progressForm.completed_quantity}
                  onChange={(event) =>
                    updateProgressField(
                      'completed_quantity',
                      event.target.value,
                    )
                  }
                />
                <small className="field-help">
                  Qty rencana batch ini {production.planned_quantity} pcs.
                </small>
              </div>

              <div className="modal-actions">
                <button
                  type="button"
                  className="button button--ghost"
                  onClick={() => setProgressModal(null)}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="button button--primary"
                  disabled={progressMutation.isPending}
                >
                  {progressMutation.isPending
                    ? 'Menyimpan...'
                    : 'Simpan progres'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </section>
  )
}
