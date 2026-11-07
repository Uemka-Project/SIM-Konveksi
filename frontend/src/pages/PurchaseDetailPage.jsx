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
  TrashIcon,
} from '../components/Icons'

const STATUS_BADGE = {
  Dipesan: 'badge badge--warning',
  Diterima: 'badge badge--success',
  Dibatalkan: 'badge badge--danger',
}

export default function PurchaseDetailPage() {
  const { purchaseId } = useParams()
  const location = useLocation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = useAuth()
  const [feedback, setFeedback] = useState(location.state?.feedback || null)
  const [error, setError] = useState('')

  const purchaseQuery = useQuery({
    queryKey: ['purchase', purchaseId],
    queryFn: () => apiRequest(`/purchases/${purchaseId}`),
  })

  useEffect(() => {
    if (!feedback) {
      return undefined
    }

    const timer = window.setTimeout(() => setFeedback(null), 4500)

    return () => window.clearTimeout(timer)
  }, [feedback])

  const purchase = purchaseQuery.data?.data

  function invalidatePurchase() {
    queryClient.invalidateQueries({ queryKey: ['purchase', purchaseId] })
    queryClient.invalidateQueries({ queryKey: ['purchases'] })
    queryClient.invalidateQueries({ queryKey: ['stock-movements'] })
    queryClient.invalidateQueries({ queryKey: ['materials'] })
    queryClient.invalidateQueries({ queryKey: ['materials', 'low-stock'] })
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

  const receiveMutation = useMutation({
    mutationFn: () =>
      apiRequest(`/purchases/${purchaseId}/receive`, { method: 'POST' }),
    onSuccess: (response) => {
      invalidatePurchase()
      setError('')
      setFeedback(response.message)
    },
    onError: handleError,
  })

  const cancelMutation = useMutation({
    mutationFn: () =>
      apiRequest(`/purchases/${purchaseId}/cancel`, { method: 'POST' }),
    onSuccess: (response) => {
      invalidatePurchase()
      setError('')
      setFeedback(response.message)
    },
    onError: handleError,
  })

  const deleteMutation = useMutation({
    mutationFn: () => apiRequest(`/purchases/${purchaseId}`, { method: 'DELETE' }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchases'] })
      navigate('/purchases', {
        state: { feedback: 'Pembelian berhasil dihapus.' },
      })
    },
    onError: handleError,
  })

  if (purchaseQuery.isLoading) {
    return (
      <div className="panel-state">
        <span className="spinner spinner--dark" />
        Memuat detail pembelian...
      </div>
    )
  }

  if (purchaseQuery.isError) {
    return (
      <div className="banner banner--error" role="alert">
        <AlertIcon size={18} />
        {purchaseQuery.error.message}
      </div>
    )
  }

  const isPending = purchase.status === 'Dipesan'
  const canUpdate = can('purchases', 'C') && isPending
  const canDelete = can('purchases', 'D') && isPending
  const canCancel =
    (can('purchases', 'C') || can('purchases', 'D')) && isPending
  const canReceive =
    can('purchases', 'U') &&
    (can('stock', 'C') || can('purchases', 'D')) &&
    isPending

  function handleReceive() {
    if (
      !window.confirm(
        'Tandai pembelian ini diterima? Stok bahan akan bertambah dan tindakan ini tidak dapat dibatalkan.',
      )
    ) {
      return
    }

    receiveMutation.mutate()
  }

  function handleCancel() {
    if (
      !window.confirm(
        'Batalkan pembelian ini? Pembelian berstatus Dibatalkan tidak dapat diterima lagi.',
      )
    ) {
      return
    }

    cancelMutation.mutate()
  }

  function handleDelete() {
    if (
      !window.confirm(
        `Hapus pembelian #${purchase.purchase_id}? Tindakan ini tidak dapat dibatalkan.`,
      )
    ) {
      return
    }

    deleteMutation.mutate()
  }

  return (
    <section className="page-stack">
      <div>
        <Link className="text-link" to="/purchases">
          <ArrowLeftIcon size={15} />
          Kembali ke daftar pembelian
        </Link>
      </div>

      <div className="page-heading">
        <div>
          <div className="order-title-row">
            <h2>Pembelian #{purchase.purchase_id}</h2>
            <span className={STATUS_BADGE[purchase.status] || 'badge'}>
              {purchase.status}
            </span>
          </div>
          <p>
            {purchase.supplier?.name || 'Tanpa pemasok'} · tanggal{' '}
            {formatDate(purchase.purchase_date)}
          </p>
        </div>

        <div className="page-heading-actions">
          {canReceive && (
            <button
              type="button"
              className="button button--primary"
              onClick={handleReceive}
              disabled={receiveMutation.isPending}
            >
              {receiveMutation.isPending ? 'Memproses...' : 'Terima pembelian'}
            </button>
          )}

          {canUpdate && (
            <Link
              className="button button--ghost"
              to={`/purchases/${purchase.purchase_id}/edit`}
            >
              <EditIcon size={16} />
              Ubah
            </Link>
          )}

          {canCancel && (
            <button
              type="button"
              className="button button--ghost"
              onClick={handleCancel}
              disabled={cancelMutation.isPending}
            >
              Batalkan
            </button>
          )}

          {canDelete && (
            <button
              type="button"
              className="button button--danger"
              onClick={handleDelete}
              disabled={deleteMutation.isPending}
            >
              <TrashIcon size={16} />
              Hapus
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
            <span className="stat-value">
              {formatRupiah(purchase.total_amount)}
            </span>
            <span className="stat-label">Total pembelian</span>
          </div>
        </article>

        <article className="stat-card">
          <div>
            <span className="stat-value">{purchase.details.length}</span>
            <span className="stat-label">Baris item</span>
          </div>
        </article>

        <article className="stat-card stat-card--accent">
          <div>
            <span className="stat-value">{purchase.status}</span>
            <span className="stat-label">Status pembelian</span>
          </div>
        </article>
      </div>

      <div className="detail-grid">
        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Informasi Pembelian</h3>
              <p>Ringkasan data pembelian.</p>
            </div>
          </div>

          <dl className="info-list">
            <div>
              <dt>Pemasok</dt>
              <dd>{purchase.supplier?.name || '—'}</dd>
            </div>
            <div>
              <dt>Tanggal pembelian</dt>
              <dd>{formatDate(purchase.purchase_date)}</dd>
            </div>
            <div>
              <dt>Status</dt>
              <dd>{purchase.status}</dd>
            </div>
            <div>
              <dt>Jumlah item</dt>
              <dd>{purchase.details.length} baris</dd>
            </div>
          </dl>
        </div>

        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Alur Pembelian</h3>
              <p>
                Pembelian dibuat berstatus Dipesan. Gudang menandai Diterima
                untuk menambah stok lewat satu mutasi MASUK per item. Pembelian
                yang belum diterima dapat dibatalkan.
              </p>
            </div>
          </div>

          <div className="status-actions">
            <span className={STATUS_BADGE[purchase.status] || 'badge'}>
              {purchase.status}
            </span>

            <span className="muted-text">
              {purchase.status === 'Diterima'
                ? 'Stok bahan sudah diperbarui dan pembelian tidak dapat diubah.'
                : purchase.status === 'Dibatalkan'
                  ? 'Pembelian dibatalkan dan tidak dapat diterima.'
                  : canReceive
                    ? 'Tandai diterima setelah bahan benar-benar datang.'
                    : 'Menunggu gudang menandai penerimaan.'}
            </span>
          </div>
        </div>
      </div>

      <div className="panel">
        <div className="panel-heading">
          <div>
            <h3>Item Pembelian</h3>
            <p>Bahan, jumlah, dan harga yang dibeli.</p>
          </div>
        </div>

        {purchase.details.length === 0 ? (
          <div className="panel-state">
            Pembelian ini belum memiliki item.
          </div>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Bahan</th>
                  <th>Satuan</th>
                  <th className="align-right">Jumlah</th>
                  <th className="align-right">Harga satuan</th>
                  <th className="align-right">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                {purchase.details.map((detail) => (
                  <tr key={detail.purchase_detail_id}>
                    <td>{detail.material?.material_name || '—'}</td>
                    <td>{detail.material?.unit_name || '—'}</td>
                    <td className="align-right">
                      {formatNumber(detail.quantity)}
                    </td>
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
                  <td colSpan={4} className="align-right">
                    Total pembelian
                  </td>
                  <td className="align-right">
                    <strong>{formatRupiah(purchase.total_amount)}</strong>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </div>
    </section>
  )
}
