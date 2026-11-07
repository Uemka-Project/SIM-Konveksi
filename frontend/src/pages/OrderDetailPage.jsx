import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../context/useAuth'
import { apiRequest } from '../lib/api'
import { formatDate, formatNumber, formatRupiah } from '../lib/format'
import {
  AlertIcon,
  ArrowLeftIcon,
  EditIcon,
  PlusIcon,
  TrashIcon,
  WalletIcon,
} from '../components/Icons'

const STATUS_BADGE = {
  Baru: 'badge badge--muted',
  Diproses: 'badge badge--warning',
  Selesai: 'badge badge--success',
  Dibatalkan: 'badge badge--danger',
}

function today() {
  const date = new Date()
  const pad = (value) => String(value).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function emptyPayment(order) {
  return {
    payment_date: today(),
    amount: order && order.remaining_amount > 0 ? String(order.remaining_amount) : '',
    payment_method: 'Tunai',
    payment_type: order && order.paid_amount > 0 ? 'Cicilan' : 'DP',
    notes: '',
  }
}

export default function OrderDetailPage() {
  const { orderId } = useParams()
  const location = useLocation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = useAuth()
  const [feedback, setFeedback] = useState(location.state?.feedback || null)
  const [error, setError] = useState('')
  const [paymentOpen, setPaymentOpen] = useState(false)
  const [paymentForm, setPaymentForm] = useState(() => emptyPayment(null))

  const orderQuery = useQuery({
    queryKey: ['order', orderId],
    queryFn: () => apiRequest(`/orders/${orderId}`),
  })

  const requirementsQuery = useQuery({
    queryKey: ['order', orderId, 'material-requirements'],
    queryFn: () => apiRequest(`/orders/${orderId}/material-requirements`),
    enabled: orderQuery.isSuccess,
  })

  useEffect(() => {
    if (!feedback) {
      return undefined
    }

    const timer = window.setTimeout(() => setFeedback(null), 4500)

    return () => window.clearTimeout(timer)
  }, [feedback])

  const order = orderQuery.data?.data
  const payments = order?.payments || []
  const productionOrders = order?.production_orders || []
  const requirements = requirementsQuery.data?.data

  function invalidateOrder() {
    queryClient.invalidateQueries({ queryKey: ['order', orderId] })
    queryClient.invalidateQueries({
      queryKey: ['order', orderId, 'material-requirements'],
    })
    queryClient.invalidateQueries({ queryKey: ['orders'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
  }

  function handleError(requestError) {
    const firstError = Object.values(requestError.errors || {})[0]

    setError(
      Array.isArray(firstError)
        ? firstError[0]
        : requestError.message || 'Terjadi kesalahan. Silakan coba lagi.',
    )
  }

  const cancelOrderMutation = useMutation({
    mutationFn: () =>
      apiRequest(`/orders/${orderId}/cancel`, { method: 'POST' }),
    onSuccess: (response) => {
      invalidateOrder()
      setError('')
      setFeedback(response.message)
    },
    onError: handleError,
  })

  const deleteOrderMutation = useMutation({
    mutationFn: () => apiRequest(`/orders/${orderId}`, { method: 'DELETE' }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['orders'] })
      navigate('/orders', { state: { feedback: 'Pesanan berhasil dihapus.' } })
    },
    onError: handleError,
  })

  const createPaymentMutation = useMutation({
    mutationFn: (payload) =>
      apiRequest(`/orders/${orderId}/payments`, {
        method: 'POST',
        body: payload,
      }),
    onSuccess: (response) => {
      invalidateOrder()
      setPaymentOpen(false)
      setError('')
      setFeedback(response.message)
    },
    onError: handleError,
  })

  const deletePaymentMutation = useMutation({
    mutationFn: (paymentId) =>
      apiRequest(`/payments/${paymentId}`, { method: 'DELETE' }),
    onSuccess: (response) => {
      invalidateOrder()
      setError('')
      setFeedback(response.message)
    },
    onError: handleError,
  })

  if (orderQuery.isLoading) {
    return (
      <div className="panel-state">
        <span className="spinner spinner--dark" />
        Memuat detail pesanan...
      </div>
    )
  }

  if (orderQuery.isError) {
    return (
      <div className="banner banner--error" role="alert">
        <AlertIcon size={18} />
        {orderQuery.error.message}
      </div>
    )
  }

  const canEdit = can('orders', 'U') && order.status === 'Baru'
  const canDelete = can('orders', 'D') && order.status === 'Baru'
  const canPay = can('payments', 'C')
  const isCancelled = order.status === 'Dibatalkan'
  const isFirstPayment = payments.length === 0
  const canCancel =
    can('orders', 'U') &&
    ['Baru', 'Diproses'].includes(order.status) &&
    productionOrders.length === 0

  function openPayment() {
    setPaymentForm(emptyPayment(order))
    setError('')
    setPaymentOpen(true)
  }

  function handleCancelOrder() {
    if (
      !window.confirm(
        'Batalkan pesanan ini? Pembatalan hanya dapat dilakukan sebelum produksi dimulai dan status tidak dapat dikembalikan.',
      )
    ) {
      return
    }

    cancelOrderMutation.mutate()
  }

  function handleDeleteOrder() {
    if (
      !window.confirm(
        `Hapus pesanan #${order.order_id}? Tindakan ini tidak dapat dibatalkan.`,
      )
    ) {
      return
    }

    deleteOrderMutation.mutate()
  }

  function handlePaymentSubmit(event) {
    event.preventDefault()
    setError('')

    if (!paymentForm.amount || Number(paymentForm.amount) <= 0) {
      setError('Jumlah pembayaran harus lebih dari 0.')
      return
    }

    createPaymentMutation.mutate({
      payment_date: paymentForm.payment_date,
      amount: Number(paymentForm.amount),
      payment_method: paymentForm.payment_method,
      payment_type: isFirstPayment ? 'DP' : paymentForm.payment_type,
      notes: paymentForm.notes || null,
    })
  }

  function handleDeletePayment(payment) {
    if (
      !window.confirm(
        `Hapus pembayaran ${formatRupiah(payment.amount)} pada ${formatDate(
          payment.payment_date,
        )}?`,
      )
    ) {
      return
    }

    deletePaymentMutation.mutate(payment.payment_id)
  }

  function updatePaymentField(name, value) {
    setPaymentForm((current) => ({ ...current, [name]: value }))
    setError('')
  }

  return (
    <section className="page-stack">
      <div>
        <Link className="text-link" to="/orders">
          <ArrowLeftIcon size={15} />
          Kembali ke daftar pesanan
        </Link>
      </div>

      <div className="page-heading">
        <div>
          <div className="order-title-row">
            <h2>Pesanan #{order.order_id}</h2>
            <span className={STATUS_BADGE[order.status] || 'badge'}>
              {order.status}
            </span>
            {order.is_overdue && (
              <span className="badge badge--danger">Lewat jatuh tempo</span>
            )}
          </div>
          <p>
            {order.customer?.name || 'Tanpa pelanggan'} · tanggal{' '}
            {formatDate(order.order_date)} · jatuh tempo{' '}
            {formatDate(order.due_date)}
          </p>
        </div>

        {(canEdit || canDelete) && (
          <div className="page-heading-actions">
            {canEdit && (
              <Link
                className="button button--ghost"
                to={`/orders/${order.order_id}/edit`}
              >
                <EditIcon size={16} />
                Ubah
              </Link>
            )}
            {canDelete && (
              <button
                type="button"
                className="button button--danger"
                onClick={handleDeleteOrder}
                disabled={deleteOrderMutation.isPending}
              >
                <TrashIcon size={16} />
                Hapus
              </button>
            )}
          </div>
        )}
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
            <span className="stat-value">{formatRupiah(order.total_amount)}</span>
            <span className="stat-label">Total pesanan</span>
          </div>
        </article>

        <article className="stat-card">
          <div>
            <span className="stat-value">{formatRupiah(order.paid_amount)}</span>
            <span className="stat-label">Sudah dibayar</span>
          </div>
        </article>

        <article className="stat-card stat-card--accent">
          <div>
            <span className="stat-value">
              {formatRupiah(order.remaining_amount)}
            </span>
            <span className="stat-label">Sisa tagihan</span>
          </div>
        </article>

        <article className="stat-card">
          <div>
            <span className="stat-value">
              {order.is_paid ? 'Lunas' : 'Belum lunas'}
            </span>
            <span className="stat-label">Status pembayaran</span>
          </div>
        </article>
      </div>

      <div className="detail-grid">
        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Informasi Pesanan</h3>
              <p>Ringkasan data pesanan.</p>
            </div>
          </div>

          <dl className="info-list">
            <div>
              <dt>Pelanggan</dt>
              <dd>{order.customer?.name || '—'}</dd>
            </div>
            <div>
              <dt>Tanggal pesanan</dt>
              <dd>{formatDate(order.order_date)}</dd>
            </div>
            <div>
              <dt>Jatuh tempo</dt>
              <dd>{formatDate(order.due_date)}</dd>
            </div>
            <div>
              <dt>Jumlah item</dt>
              <dd>{order.details.length} baris</dd>
            </div>
          </dl>
        </div>

        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Status Pesanan</h3>
              <p>
                Status berubah otomatis: Baru → Diproses saat DP pertama dicatat,
                lalu Selesai setelah produk diserahkan. Pembatalan hanya dapat
                dilakukan sebelum perintah produksi dibuat.
              </p>
            </div>
          </div>

          <div className="status-actions">
            <span className={STATUS_BADGE[order.status] || 'badge'}>
              {order.status}
            </span>

            {canCancel ? (
              <button
                type="button"
                className="button button--ghost button--small"
                onClick={handleCancelOrder}
                disabled={cancelOrderMutation.isPending}
              >
                Batalkan pesanan
              </button>
            ) : (
              <span className="muted-text">
                {order.status === 'Selesai' || order.status === 'Dibatalkan'
                  ? 'Status akhir, tidak ada perubahan lebih lanjut.'
                  : productionOrders.length > 0
                    ? 'Pesanan sudah memiliki perintah produksi, pembatalan tidak tersedia.'
                    : 'Anda tidak memiliki hak membatalkan pesanan.'}
              </span>
            )}
          </div>
        </div>
      </div>

      <div className="panel">
        <div className="panel-heading">
          <div>
            <h3>Item Pesanan</h3>
            <p>Varian, jumlah, dan harga yang dipesan.</p>
          </div>
        </div>

        {order.details.length === 0 ? (
          <div className="panel-state">Pesanan ini belum memiliki item.</div>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Produk</th>
                  <th>SKU</th>
                  <th>Ukuran / Warna</th>
                  <th className="align-right">Jumlah</th>
                  <th className="align-right">Harga satuan</th>
                  <th className="align-right">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                {order.details.map((detail) => (
                  <tr key={detail.order_detail_id}>
                    <td>{detail.product_name || '—'}</td>
                    <td>
                      <strong>{detail.sku || '—'}</strong>
                    </td>
                    <td>
                      {detail.size} / {detail.color}
                    </td>
                    <td className="align-right">{detail.quantity}</td>
                    <td className="align-right">
                      {formatRupiah(detail.unit_price)}
                    </td>
                    <td className="align-right">
                      {formatRupiah(detail.subtotal)}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="table-total-row">
                  <td colSpan={5} className="align-right">
                    Total pesanan
                  </td>
                  <td className="align-right">
                    <strong>{formatRupiah(order.total_amount)}</strong>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </div>

      <div className="panel">
        <div className="panel-heading">
          <div>
            <h3>Kebutuhan Bahan</h3>
            <p>
              Perhitungan kebutuhan bahan dari BOM varian dikali jumlah pesanan,
              dibandingkan dengan stok saat ini.
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
            Varian pada pesanan ini belum memiliki komponen BOM.
          </div>
        ) : (
          <>
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

            {!requirements.is_sufficient && can('purchases', 'C') && (
              <div className="panel-note">
                Stok belum mencukupi.{' '}
                <Link className="text-link" to="/purchases/new">
                  Buat pembelian bahan
                </Link>{' '}
                untuk menambah stok lewat penerimaan gudang.
              </div>
            )}
          </>
        )}
      </div>

      {can('payments', 'R') && (
        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Pembayaran</h3>
              <p>
                DP, cicilan, dan pelunasan. Pembayaran pertama wajib DP; total
                pembayaran tidak dapat melebihi total pesanan.
              </p>
            </div>

            {canPay && !isCancelled && !order.is_paid && (
              <button
                type="button"
                className="button button--primary button--small"
                onClick={openPayment}
              >
                <PlusIcon size={15} />
                Catat pembayaran
              </button>
            )}
          </div>

          {payments.length === 0 ? (
            <div className="panel-state">
              <WalletIcon size={18} />
              Belum ada pembayaran untuk pesanan ini.
            </div>
          ) : (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Tanggal</th>
                    <th>Metode</th>
                    <th>Jenis</th>
                    <th>Catatan</th>
                    <th className="align-right">Jumlah</th>
                    {can('payments', 'D') && <th className="align-right">Aksi</th>}
                  </tr>
                </thead>
                <tbody>
                  {payments.map((payment) => (
                    <tr key={payment.payment_id}>
                      <td>{formatDate(payment.payment_date)}</td>
                      <td>{payment.payment_method}</td>
                      <td>
                        <span className="badge badge--muted">
                          {payment.payment_type}
                        </span>
                      </td>
                      <td>{payment.notes || '—'}</td>
                      <td className="align-right">
                        {formatRupiah(payment.amount)}
                      </td>
                      {can('payments', 'D') && (
                        <td className="align-right">
                          <button
                            type="button"
                            className="icon-button icon-button--danger"
                            onClick={() => handleDeletePayment(payment)}
                            title="Hapus pembayaran"
                            aria-label="Hapus pembayaran"
                            disabled={deletePaymentMutation.isPending}
                          >
                            <TrashIcon size={16} />
                          </button>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {isCancelled && (
            <div className="panel-note">
              Pesanan dibatalkan, pembayaran baru tidak dapat dicatat.
            </div>
          )}
        </div>
      )}

      {productionOrders.length > 0 && (
        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Produksi</h3>
              <p>Perintah produksi yang terkait dengan pesanan ini.</p>
            </div>
          </div>

          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Perintah</th>
                  <th>Qty rencana</th>
                  <th>Status</th>
                  <th>Mulai</th>
                  <th>Target</th>
                </tr>
              </thead>
              <tbody>
                {productionOrders.map((productionOrder) => (
                  <tr key={productionOrder.production_order_id}>
                    <td>#{productionOrder.production_order_id}</td>
                    <td>{productionOrder.planned_quantity}</td>
                    <td>
                      <span className="badge badge--muted">
                        {productionOrder.status}
                      </span>
                    </td>
                    <td>{formatDate(productionOrder.start_date)}</td>
                    <td>{formatDate(productionOrder.target_date)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {paymentOpen && (
        <div className="modal-backdrop" role="presentation">
          <div className="modal" role="dialog" aria-modal="true">
            <div className="modal-header">
              <h3>Catat Pembayaran</h3>
              <button
                type="button"
                className="icon-button"
                onClick={() => setPaymentOpen(false)}
                aria-label="Tutup"
              >
                ✕
              </button>
            </div>

            <form className="modal-form" onSubmit={handlePaymentSubmit}>
              {error && (
                <div className="banner banner--error" role="alert">
                  {error}
                </div>
              )}

              <div className="payment-summary">
                <span>
                  Sisa tagihan <strong>{formatRupiah(order.remaining_amount)}</strong>
                </span>
                <span>
                  Total pesanan <strong>{formatRupiah(order.total_amount)}</strong>
                </span>
              </div>

              <div className="field">
                <label className="field-label" htmlFor="payment-date">
                  Tanggal pembayaran *
                </label>
                <input
                  id="payment-date"
                  type="date"
                  value={paymentForm.payment_date}
                  onChange={(event) =>
                    updatePaymentField('payment_date', event.target.value)
                  }
                  required
                />
              </div>

              <div className="field">
                <label className="field-label" htmlFor="payment-amount">
                  Jumlah *
                </label>
                <input
                  id="payment-amount"
                  type="number"
                  min="0.01"
                  max={order.remaining_amount}
                  step="0.01"
                  value={paymentForm.amount}
                  onChange={(event) =>
                    updatePaymentField('amount', event.target.value)
                  }
                  required
                />
                <small className="field-help">
                  Maksimal {formatRupiah(order.remaining_amount)}.
                </small>
              </div>

              <div className="form-grid">
                <div className="field">
                  <label className="field-label" htmlFor="payment-method">
                    Metode *
                  </label>
                  <select
                    id="payment-method"
                    value={paymentForm.payment_method}
                    onChange={(event) =>
                      updatePaymentField('payment_method', event.target.value)
                    }
                    required
                  >
                    <option value="Tunai">Tunai</option>
                    <option value="Transfer">Transfer</option>
                    <option value="QRIS">QRIS</option>
                  </select>
                </div>

                <div className="field">
                  <label className="field-label" htmlFor="payment-type">
                    Jenis *
                  </label>
                  <select
                    id="payment-type"
                    value={isFirstPayment ? 'DP' : paymentForm.payment_type}
                    onChange={(event) =>
                      updatePaymentField('payment_type', event.target.value)
                    }
                    disabled={isFirstPayment}
                    required
                  >
                    {isFirstPayment ? (
                      <option value="DP">DP</option>
                    ) : (
                      <>
                        <option value="DP">DP</option>
                        <option value="Cicilan">Cicilan</option>
                        <option value="Pelunasan">Pelunasan</option>
                      </>
                    )}
                  </select>
                  {isFirstPayment && (
                    <small className="field-help">
                      Pembayaran pertama wajib berjenis DP dan otomatis mengubah
                      status pesanan menjadi Diproses.
                    </small>
                  )}
                </div>
              </div>

              <div className="field">
                <label className="field-label" htmlFor="payment-notes">
                  Catatan
                </label>
                <textarea
                  id="payment-notes"
                  rows={2}
                  value={paymentForm.notes}
                  onChange={(event) =>
                    updatePaymentField('notes', event.target.value)
                  }
                  placeholder="Opsional, misalnya DP 50 persen."
                />
              </div>

              <div className="modal-actions">
                <button
                  type="button"
                  className="button button--ghost"
                  onClick={() => setPaymentOpen(false)}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="button button--primary"
                  disabled={createPaymentMutation.isPending}
                >
                  {createPaymentMutation.isPending
                    ? 'Menyimpan...'
                    : 'Simpan pembayaran'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </section>
  )
}
