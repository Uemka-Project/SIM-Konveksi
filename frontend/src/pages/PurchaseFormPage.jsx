import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../context/useAuth'
import { apiRequest } from '../lib/api'
import { formatNumber, formatRupiah } from '../lib/format'
import {
  AlertIcon,
  ArrowLeftIcon,
  PlusIcon,
  TrashIcon,
} from '../components/Icons'

const EMPTY_ITEM = { material_id: '', quantity: '1', unit_price: '' }

function formatInputDate(date) {
  const pad = (value) => String(value).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function today() {
  return formatInputDate(new Date())
}

function initialForm(purchase) {
  if (purchase) {
    return {
      supplier_id: String(purchase.supplier_id),
      purchase_date: purchase.purchase_date,
      details: purchase.details.map((detail) => ({
        material_id: String(detail.material_id),
        quantity: String(detail.quantity),
        unit_price: String(detail.unit_price),
      })),
    }
  }

  return {
    supplier_id: '',
    purchase_date: today(),
    details: [{ ...EMPTY_ITEM }],
  }
}

function PurchaseFormContent({
  isEdit,
  purchaseId,
  purchase,
  suppliers,
  materials,
}) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = useAuth()
  const [form, setForm] = useState(() => initialForm(purchase))
  const [error, setError] = useState('')

  const saveMutation = useMutation({
    mutationFn: (payload) =>
      apiRequest(isEdit ? `/purchases/${purchaseId}` : '/purchases', {
        method: isEdit ? 'PUT' : 'POST',
        body: payload,
      }),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['purchases'] })
      queryClient.invalidateQueries({ queryKey: ['purchase', purchaseId] })
      navigate(`/purchases/${response.data.purchase_id}`, {
        state: { feedback: response.message },
      })
    },
    onError: (requestError) => {
      const firstError = Object.values(requestError.errors || {})[0]

      setError(
        Array.isArray(firstError)
          ? firstError[0]
          : requestError.message || 'Gagal menyimpan pembelian.',
      )
    },
  })

  const editable = !isEdit || purchase?.status === 'Dipesan'
  const allowed = can('purchases', 'C')

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

    if (!form.supplier_id) {
      setError('Pilih pemasok terlebih dahulu.')
      return
    }

    if (form.details.length === 0) {
      setError('Pembelian harus memiliki minimal satu item.')
      return
    }

    if (form.details.some((item) => !item.material_id)) {
      setError('Setiap item harus memilih bahan.')
      return
    }

    const materialIds = form.details
      .map((item) => item.material_id)
      .filter(Boolean)

    if (new Set(materialIds).size !== materialIds.length) {
      setError('Bahan yang sama tidak boleh dipilih dua kali.')
      return
    }

    if (form.details.some((item) => Number(item.quantity) <= 0)) {
      setError('Jumlah setiap item harus lebih dari 0.')
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
      supplier_id: Number(form.supplier_id),
      purchase_date: form.purchase_date,
      details: form.details.map((item) => ({
        material_id: Number(item.material_id),
        quantity: Number(item.quantity),
        unit_price: Number(item.unit_price),
      })),
    })
  }

  return (
    <section className="page-stack">
      <div>
        <Link
          className="text-link"
          to={isEdit ? `/purchases/${purchaseId}` : '/purchases'}
        >
          <ArrowLeftIcon size={15} />
          {isEdit ? 'Kembali ke detail pembelian' : 'Kembali ke daftar pembelian'}
        </Link>
      </div>

      <div className="page-heading">
        <div>
          <h2>{isEdit ? 'Ubah Pembelian' : 'Buat Pembelian'}</h2>
          <p>
            Subtotal setiap item dan total pembelian dihitung otomatis oleh
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
          Anda tidak memiliki hak untuk {isEdit ? 'mengubah' : 'membuat'}{' '}
          pembelian.
        </div>
      )}

      {isEdit && !editable && (
        <div className="banner banner--error" role="alert">
          Pembelian berstatus {purchase?.status} tidak dapat diubah. Hanya
          pembelian berstatus Dipesan yang boleh diedit.
        </div>
      )}

      <form className="order-form" onSubmit={handleSubmit}>
        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>Informasi Pembelian</h3>
              <p>Pemasok dan tanggal pembelian.</p>
            </div>
          </div>

          <div className="modal-form">
            <div className="form-grid">
              <div className="field">
                <label className="field-label" htmlFor="purchase-supplier">
                  Pemasok *
                </label>
                <select
                  id="purchase-supplier"
                  value={form.supplier_id}
                  onChange={(event) =>
                    updateField('supplier_id', event.target.value)
                  }
                  required
                >
                  <option value="">— Pilih pemasok —</option>
                  {suppliers.map((supplier) => (
                    <option key={supplier.supplier_id} value={supplier.supplier_id}>
                      {supplier.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="field">
                <label className="field-label" htmlFor="purchase-date">
                  Tanggal pembelian *
                </label>
                <input
                  id="purchase-date"
                  type="date"
                  value={form.purchase_date}
                  onChange={(event) =>
                    updateField('purchase_date', event.target.value)
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
              <h3>Item Pembelian</h3>
              <p>Bahan, jumlah, dan harga satuan dari pemasok.</p>
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
                Belum ada item. Tambahkan minimal satu item pembelian.
              </div>
            ) : (
              <div className="order-items">
                <div className="order-item-row order-item-row--head">
                  <span>Bahan</span>
                  <span>Jumlah</span>
                  <span>Harga satuan</span>
                  <span className="align-right">Subtotal</span>
                  <span />
                </div>

                {form.details.map((item, index) => {
                  const subtotal =
                    Number(item.quantity || 0) * Number(item.unit_price || 0)
                  const selected = materials.find(
                    (material) =>
                      String(material.material_id) === String(item.material_id),
                  )

                  return (
                    <div className="order-item-row" key={index}>
                      <select
                        value={item.material_id}
                        onChange={(event) =>
                          updateItem(index, 'material_id', event.target.value)
                        }
                        aria-label={`Bahan item ${index + 1}`}
                        disabled={!editable}
                        required
                      >
                        <option value="">— Pilih bahan —</option>
                        {materials.map((material) => (
                          <option
                            key={material.material_id}
                            value={material.material_id}
                          >
                            {material.material_name} ({material.unit_name})
                          </option>
                        ))}
                      </select>

                      <input
                        type="number"
                        min="0.01"
                        step="0.01"
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
                        {selected && (
                          <small className="order-line-note">
                            stok {formatNumber(selected.current_stock)}{' '}
                            {selected.unit_name}
                          </small>
                        )}
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
              <span>Total pembelian</span>
              <strong>{formatRupiah(total)}</strong>
            </div>

            <div className="modal-actions">
              <Link
                className="button button--ghost"
                to={isEdit ? `/purchases/${purchaseId}` : '/purchases'}
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
                    : 'Buat pembelian'}
              </button>
            </div>
          </div>
        </div>
      </form>
    </section>
  )
}

export default function PurchaseFormPage() {
  const { purchaseId } = useParams()
  const isEdit = Boolean(purchaseId)

  const purchaseQuery = useQuery({
    queryKey: ['purchase', purchaseId],
    queryFn: () => apiRequest(`/purchases/${purchaseId}`),
    enabled: isEdit,
  })

  const suppliersQuery = useQuery({
    queryKey: ['suppliers', 'options'],
    queryFn: () => apiRequest('/suppliers?per_page=100'),
  })

  const materialsQuery = useQuery({
    queryKey: ['materials', 'options'],
    queryFn: () => apiRequest('/materials?per_page=100'),
  })

  if (isEdit && purchaseQuery.isLoading) {
    return (
      <div className="panel-state">
        <span className="spinner spinner--dark" />
        Memuat pembelian...
      </div>
    )
  }

  if (isEdit && purchaseQuery.isError) {
    return (
      <div className="banner banner--error" role="alert">
        <AlertIcon size={18} />
        {purchaseQuery.error.message}
      </div>
    )
  }

  if (suppliersQuery.isError) {
    return (
      <div className="banner banner--error" role="alert">
        <AlertIcon size={18} />
        {suppliersQuery.error.message}
      </div>
    )
  }

  if (materialsQuery.isError) {
    return (
      <div className="banner banner--error" role="alert">
        <AlertIcon size={18} />
        {materialsQuery.error.message}
      </div>
    )
  }

  return (
    <PurchaseFormContent
      key={purchaseId || 'new'}
      isEdit={isEdit}
      purchaseId={purchaseId}
      purchase={purchaseQuery.data?.data}
      suppliers={suppliersQuery.data?.data || []}
      materials={materialsQuery.data?.data || []}
    />
  )
}
