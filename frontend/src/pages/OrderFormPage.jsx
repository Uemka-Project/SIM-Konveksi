import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../context/useAuth'
import { apiRequest } from '../lib/api'
import { formatRupiah } from '../lib/format'
import {
  AlertIcon,
  ArrowLeftIcon,
  PlusIcon,
  TrashIcon,
} from '../components/Icons'

const EMPTY_ITEM = { variant_id: '', quantity: '1', unit_price: '' }

function formatInputDate(date) {
  const pad = (value) => String(value).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function today() {
  return formatInputDate(new Date())
}

function addDays(days) {
  const date = new Date()
  date.setDate(date.getDate() + days)

  return formatInputDate(date)
}

function initialForm(order) {
  if (order) {
    return {
      customer_id: String(order.customer_id),
      order_date: order.order_date,
      due_date: order.due_date,
      details: order.details.map((detail) => ({
        variant_id: String(detail.variant_id),
        quantity: String(detail.quantity),
        unit_price: String(detail.unit_price),
      })),
    }
  }

  return {
    customer_id: '',
    order_date: today(),
    due_date: addDays(7),
    details: [{ ...EMPTY_ITEM }],
  }
}

function OrderFormContent({ isEdit, orderId, order, customers, variants }) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = useAuth()
  const [form, setForm] = useState(() => initialForm(order))
  const [error, setError] = useState('')

  const saveMutation = useMutation({
    mutationFn: (payload) =>
      apiRequest(isEdit ? `/orders/${orderId}` : '/orders', {
        method: isEdit ? 'PUT' : 'POST',
        body: payload,
      }),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['orders'] })
      queryClient.invalidateQueries({ queryKey: ['order', orderId] })
      navigate(`/orders/${response.data.order_id}`, {
        state: { feedback: response.message },
      })
    },
    onError: (requestError) => {
      const firstError = Object.values(requestError.errors || {})[0]

      setError(
        Array.isArray(firstError)
          ? firstError[0]
          : requestError.message || 'Gagal menyimpan pesanan.',
      )
    },
  })

  const editable = !isEdit || order?.status === 'Baru'
  const allowed = can('orders', isEdit ? 'U' : 'C')

  const total = useMemo(
    () =>
      form.details.reduce(
        (sum, item) =>
          sum + Number(item.quantity || 0) * Number(item.unit_price || 0),
        0,
      ),
    [form.details],
  )

  function updateField(name, value) {
    setForm((current) => ({ ...current, [name]: value }))
    setError('')
  }

  function updateItem(index, name, value) {
    setForm((current) => ({
      ...current,
      details: current.details.map((item, itemIndex) =>
        itemIndex === index ? { ...item, [name]: value } : item,
      ),
    }))
    setError('')
  }

  function addItem() {
    setForm((current) => ({
      ...current,
      details: [...current.details, { ...EMPTY_ITEM }],
    }))
  }

  function removeItem(index) {
    setForm((current) => ({
      ...current,
      details: current.details.filter((_, itemIndex) => itemIndex !== index),
    }))
  }

  function handleSubmit(event) {
    event.preventDefault()
    setError('')

    if (!form.customer_id) {
      setError('Pilih pelanggan terlebih dahulu.')
      return
    }

    if (form.details.length === 0) {
      setError('Pesanan harus memiliki minimal satu item.')
      return
    }

    if (form.details.some((item) => !item.variant_id)) {
      setError('Setiap item harus memilih varian produk.')
      return
    }

    if (form.details.some((item) => Number(item.quantity) <= 0)) {
      setError('Jumlah setiap item minimal 1.')
      return
    }

    if (
      form.details.some(
        (item) => item.unit_price === '' || Number(item.unit_price) < 0,
      )
    ) {
      setError('Harga satuan setiap item wajib diisi dan tidak boleh negatif.')
      return
    }

    saveMutation.mutate({
      customer_id: Number(form.customer_id),
      order_date: form.order_date,
      due_date: form.due_date,
      details: form.details.map((item) => ({
        variant_id: Number(item.variant_id),
        quantity: Number(item.quantity),
        unit_price: Number(item.unit_price),
      })),
    })
  }

  return (
    <section className="page-stack">
      <div>
        <Link className="text-link" to={isEdit ? `/orders/${orderId}` : '/orders'}>
          <ArrowLeftIcon size={15} />
          {isEdit ? 'Kembali ke detail pesanan' : 'Kembali ke daftar pesanan'}
        </Link>
      </div>

      <div className="page-heading">
        <div>
          <h2>{isEdit ? 'Ubah Pesanan' : 'Buat Pesanan'}</h2>
          <p>
            Subtotal setiap item dan total pesanan dihitung otomatis oleh
            sistem.
          </p>
        </div>
      </div>

      {error && (
        <div className="banner banner--error" role="alert">
          {error}
        </div>
      )}

      {!allowed && (
        <div className="banner banner--error" role="alert">
          Anda tidak memiliki hak untuk {isEdit ? 'mengubah' : 'membuat'} pesanan.
        </div>
      )}

      {isEdit && !editable && (
        <div className="banner banner--error" role="alert">
          Pesanan berstatus {order?.status} tidak dapat diubah. Hanya pesanan
          berstatus Baru yang boleh diedit.
        </div>
      )}

      <form className="order-form" onSubmit={handleSubmit}>
        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Informasi Pesanan</h3>
              <p>Pelanggan dan tanggal pesanan.</p>
            </div>
          </div>

          <div className="modal-form">
            <div className="form-grid">
              <div className="field">
                <label className="field-label" htmlFor="order-customer">
                  Pelanggan *
                </label>
                <select
                  id="order-customer"
                  value={form.customer_id}
                  onChange={(event) =>
                    updateField('customer_id', event.target.value)
                  }
                  required
                >
                  <option value="">— Pilih pelanggan —</option>
                  {customers.map((customer) => (
                    <option key={customer.customer_id} value={customer.customer_id}>
                      {customer.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="field">
                <label className="field-label" htmlFor="order-date">
                  Tanggal pesanan *
                </label>
                <input
                  id="order-date"
                  type="date"
                  value={form.order_date}
                  onChange={(event) =>
                    updateField('order_date', event.target.value)
                  }
                  required
                />
              </div>

              <div className="field">
                <label className="field-label" htmlFor="order-due-date">
                  Jatuh tempo *
                </label>
                <input
                  id="order-due-date"
                  type="date"
                  min={form.order_date}
                  value={form.due_date}
                  onChange={(event) =>
                    updateField('due_date', event.target.value)
                  }
                  required
                />
              </div>
            </div>
          </div>
        </div>

        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Item Pesanan</h3>
              <p>Varian produk, jumlah, dan harga satuan.</p>
            </div>

            <button
              type="button"
              className="button button--ghost button--small"
              onClick={addItem}
              disabled={!editable}
            >
              <PlusIcon size={15} />
              Tambah item
            </button>
          </div>

          <div className="modal-form">
            {form.details.length === 0 ? (
              <div className="panel-state">
                Belum ada item. Tambahkan minimal satu item pesanan.
              </div>
            ) : (
              <div className="order-items">
                <div className="order-item-row order-item-row--head">
                  <span>Varian</span>
                  <span>Jumlah</span>
                  <span>Harga satuan</span>
                  <span className="align-right">Subtotal</span>
                  <span />
                </div>

                {form.details.map((item, index) => {
                  const subtotal =
                    Number(item.quantity || 0) * Number(item.unit_price || 0)

                  return (
                    <div className="order-item-row" key={index}>
                      <select
                        value={item.variant_id}
                        onChange={(event) =>
                          updateItem(index, 'variant_id', event.target.value)
                        }
                        aria-label={`Varian item ${index + 1}`}
                        disabled={!editable}
                        required
                      >
                        <option value="">— Pilih varian —</option>
                        {variants.map((variant) => (
                          <option
                            key={variant.variant_id}
                            value={variant.variant_id}
                          >
                            {variant.sku} · {variant.product?.product_name} ·{' '}
                            {variant.size}/{variant.color}
                          </option>
                        ))}
                      </select>

                      <input
                        type="number"
                        min="1"
                        step="1"
                        value={item.quantity}
                        onChange={(event) =>
                          updateItem(index, 'quantity', event.target.value)
                        }
                        aria-label={`Jumlah item ${index + 1}`}
                        disabled={!editable}
                        required
                      />

                      <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={item.unit_price}
                        onChange={(event) =>
                          updateItem(index, 'unit_price', event.target.value)
                        }
                        placeholder="0"
                        aria-label={`Harga satuan item ${index + 1}`}
                        disabled={!editable}
                        required
                      />

                      <span className="order-line-total">
                        {formatRupiah(subtotal)}
                      </span>

                      <button
                        type="button"
                        className="icon-button icon-button--danger"
                        onClick={() => removeItem(index)}
                        aria-label={`Hapus item ${index + 1}`}
                        disabled={!editable}
                      >
                        <TrashIcon size={16} />
                      </button>
                    </div>
                  )
                })}
              </div>
            )}

            <div className="order-summary">
              <span>Total pesanan</span>
              <strong>{formatRupiah(total)}</strong>
            </div>

            <div className="modal-actions">
              <Link
                className="button button--ghost"
                to={isEdit ? `/orders/${orderId}` : '/orders'}
              >
                Batal
              </Link>
              <button
                type="submit"
                className="button button--primary"
                disabled={saveMutation.isPending || !editable || !allowed}
              >
                {saveMutation.isPending
                  ? 'Menyimpan...'
                  : isEdit
                    ? 'Simpan perubahan'
                    : 'Buat pesanan'}
              </button>
            </div>
          </div>
        </div>
      </form>
    </section>
  )
}

export default function OrderFormPage() {
  const { orderId } = useParams()
  const isEdit = Boolean(orderId)

  const orderQuery = useQuery({
    queryKey: ['order', orderId],
    queryFn: () => apiRequest(`/orders/${orderId}`),
    enabled: isEdit,
  })

  const customersQuery = useQuery({
    queryKey: ['customers', 'options'],
    queryFn: () => apiRequest('/customers?per_page=100'),
  })

  const variantsQuery = useQuery({
    queryKey: ['variants', 'options'],
    queryFn: () => apiRequest('/variants'),
  })

  if (isEdit && orderQuery.isLoading) {
    return (
      <div className="panel-state">
        <span className="spinner spinner--dark" />
        Memuat pesanan...
      </div>
    )
  }

  if (isEdit && orderQuery.isError) {
    return (
      <div className="banner banner--error" role="alert">
        <AlertIcon size={18} />
        {orderQuery.error.message}
      </div>
    )
  }

  if (customersQuery.isError) {
    return (
      <div className="banner banner--error" role="alert">
        <AlertIcon size={18} />
        {customersQuery.error.message}
      </div>
    )
  }

  if (variantsQuery.isError) {
    return (
      <div className="banner banner--error" role="alert">
        <AlertIcon size={18} />
        {variantsQuery.error.message}
      </div>
    )
  }

  return (
    <OrderFormContent
      key={orderId || 'new'}
      isEdit={isEdit}
      orderId={orderId}
      order={orderQuery.data?.data}
      customers={customersQuery.data?.data || []}
      variants={variantsQuery.data?.data || []}
    />
  )
}
