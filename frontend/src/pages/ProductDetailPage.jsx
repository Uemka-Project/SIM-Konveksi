import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { useAuth } from '../context/useAuth'
import { apiRequest } from '../lib/api'
import { formatNumber } from '../lib/format'
import {
  AlertIcon,
  ArrowLeftIcon,
  EditIcon,
  PlusIcon,
  TrashIcon,
} from '../components/Icons'

const EMPTY_VARIANT = { size: '', color: '', sku: '' }

function BomEditor({ variant, materials, canEdit }) {
  const queryClient = useQueryClient()
  const [editedItems, setEditedItems] = useState(null)
  const [error, setError] = useState('')
  const [feedback, setFeedback] = useState('')

  const bomQuery = useQuery({
    queryKey: ['bom', variant.variant_id],
    queryFn: () => apiRequest(`/variants/${variant.variant_id}/bom`),
  })

  const serverItems = bomQuery.data
    ? bomQuery.data.data.items.map((item) => ({
        material_id: String(item.material_id),
        quantity_per_unit: String(item.quantity_per_unit),
      }))
    : []

  const items = editedItems ?? serverItems

  const saveMutation = useMutation({
    mutationFn: (payload) =>
      apiRequest(`/variants/${variant.variant_id}/bom`, {
        method: 'PUT',
        body: payload,
      }),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['bom', variant.variant_id] })
      queryClient.invalidateQueries({ queryKey: ['product'] })
      setEditedItems(null)
      setFeedback(response.message || 'BOM berhasil disimpan.')
      setError('')
    },
    onError: (requestError) => {
      const firstError = Object.values(requestError.errors || {})[0]

      setError(
        Array.isArray(firstError)
          ? firstError[0]
          : requestError.message || 'Gagal menyimpan BOM.',
      )
    },
  })

  function updateItem(index, name, value) {
    setEditedItems(
      items.map((item, itemIndex) =>
        itemIndex === index ? { ...item, [name]: value } : item,
      ),
    )
    setError('')
    setFeedback('')
  }

  function addItem() {
    setEditedItems([
      ...items,
      { material_id: '', quantity_per_unit: '1' },
    ])
  }

  function removeItem(index) {
    setEditedItems(items.filter((_, itemIndex) => itemIndex !== index))
  }

  function handleSave() {
    setError('')
    setFeedback('')

    if (items.some((item) => !item.material_id)) {
      setError('Setiap baris BOM harus memilih bahan.')
      return
    }

    if (items.some((item) => Number(item.quantity_per_unit) <= 0)) {
      setError('Kebutuhan per unit harus lebih dari 0.')
      return
    }

    saveMutation.mutate({
      items: items.map((item) => ({
        material_id: Number(item.material_id),
        quantity_per_unit: Number(item.quantity_per_unit),
      })),
    })
  }

  return (
    <div className="bom-editor">
      {error && (
        <div className="banner banner--error" role="alert">
          {error}
        </div>
      )}
      {feedback && (
        <div className="banner banner--success" role="status">
          {feedback}
        </div>
      )}

      {bomQuery.isLoading ? (
        <div className="panel-state">Memuat BOM...</div>
      ) : items.length === 0 ? (
        <div className="panel-state">
          Varian ini belum memiliki BOM. Tambahkan bahan bila diperlukan.
        </div>
      ) : (
        <div className="bom-rows">
          {items.map((item, index) => {
            const material = materials.find(
              (candidate) => String(candidate.material_id) === item.material_id,
            )

            return (
              <div className="bom-row" key={index}>
                <select
                  value={item.material_id}
                  onChange={(event) =>
                    updateItem(index, 'material_id', event.target.value)
                  }
                  aria-label={`Bahan baris ${index + 1}`}
                  disabled={!canEdit}
                >
                  <option value="">— Pilih bahan —</option>
                  {materials.map((candidate) => (
                    <option
                      key={candidate.material_id}
                      value={candidate.material_id}
                    >
                      {candidate.material_name} ({candidate.unit_name})
                    </option>
                  ))}
                </select>

                <input
                  type="number"
                  min="0.01"
                  step="0.01"
                  value={item.quantity_per_unit}
                  onChange={(event) =>
                    updateItem(index, 'quantity_per_unit', event.target.value)
                  }
                  aria-label={`Kebutuhan per unit baris ${index + 1}`}
                  disabled={!canEdit}
                />

                <span className="bom-unit">
                  {material?.unit_name || 'per unit'}
                  {material && (
                    <small>
                      stok {formatNumber(material.current_stock)}
                    </small>
                  )}
                </span>

                {canEdit && (
                  <button
                    type="button"
                    className="icon-button icon-button--danger"
                    onClick={() => removeItem(index)}
                    aria-label="Hapus baris"
                  >
                    <TrashIcon size={16} />
                  </button>
                )}
              </div>
            )
          })}
        </div>
      )}

      {canEdit && (
        <div className="bom-actions">
          <button type="button" className="button button--ghost" onClick={addItem}>
            <PlusIcon size={16} />
            Tambah bahan
          </button>
          <button
            type="button"
            className="button button--primary"
            onClick={handleSave}
            disabled={saveMutation.isPending}
          >
            {saveMutation.isPending ? 'Menyimpan...' : 'Simpan BOM'}
          </button>
        </div>
      )}
    </div>
  )
}

export default function ProductDetailPage() {
  const { productId } = useParams()
  const { can } = useAuth()
  const queryClient = useQueryClient()
  const [variantModal, setVariantModal] = useState(null)
  const [variantForm, setVariantForm] = useState(EMPTY_VARIANT)
  const [selectedVariantId, setSelectedVariantId] = useState(null)
  const [formError, setFormError] = useState('')
  const [feedback, setFeedback] = useState(null)

  const productQuery = useQuery({
    queryKey: ['product', productId],
    queryFn: () => apiRequest(`/products/${productId}`),
  })

  const materialsQuery = useQuery({
    queryKey: ['materials', 'options'],
    queryFn: () => apiRequest('/materials?per_page=100'),
  })

  const saveVariantMutation = useMutation({
    mutationFn: ({ variantId, payload }) =>
      apiRequest(
        variantId
          ? `/products/${productId}/variants/${variantId}`
          : `/products/${productId}/variants`,
        {
          method: variantId ? 'PUT' : 'POST',
          body: payload,
        },
      ),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['product', productId] })
      setVariantModal(null)
      setFeedback(response.message || 'Varian disimpan.')
    },
    onError: (error) => {
      const firstError = Object.values(error.errors || {})[0]

      setFormError(
        Array.isArray(firstError) ? firstError[0] : error.message || 'Gagal menyimpan varian.',
      )
    },
  })

  const deleteVariantMutation = useMutation({
    mutationFn: (variantId) =>
      apiRequest(`/products/${productId}/variants/${variantId}`, {
        method: 'DELETE',
      }),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['product', productId] })
      setFeedback(response.message || 'Varian dihapus.')
      setSelectedVariantId(null)
    },
    onError: (error) => {
      setFeedback(error.message)
    },
  })

  if (productQuery.isLoading) {
    return (
      <div className="panel-state">
        <span className="spinner spinner--dark" />
        Memuat produk...
      </div>
    )
  }

  if (productQuery.isError) {
    return (
      <div className="banner banner--error" role="alert">
        <AlertIcon size={18} />
        {productQuery.error.message}
      </div>
    )
  }

  const product = productQuery.data.data
  const variants = product.variants || []
  const materials = materialsQuery.data?.data || []
  const selectedVariant = variants.find(
    (variant) => variant.variant_id === selectedVariantId,
  )

  function openCreate() {
    setVariantForm(EMPTY_VARIANT)
    setFormError('')
    setVariantModal({ mode: 'create' })
  }

  function openEdit(variant) {
    setVariantForm({
      size: variant.size,
      color: variant.color,
      sku: variant.sku,
    })
    setFormError('')
    setVariantModal({ mode: 'edit', item: variant })
  }

  function handleVariantSubmit(event) {
    event.preventDefault()
    setFormError('')
    saveVariantMutation.mutate({
      variantId:
        variantModal.mode === 'edit' ? variantModal.item.variant_id : null,
      payload: variantForm,
    })
  }

  function handleDeleteVariant(variant) {
    if (!window.confirm(`Hapus varian ${variant.sku}?`)) {
      return
    }

    deleteVariantMutation.mutate(variant.variant_id)
  }

  return (
    <section className="page-stack">
      <div>
        <Link className="text-link" to="/products">
          <ArrowLeftIcon size={15} />
          Kembali ke produk
        </Link>
      </div>

      <div className="page-heading">
        <div>
          <h2>{product.product_name}</h2>
          <p>{product.description || 'Produk tanpa deskripsi.'}</p>
        </div>

        {can('variants', 'C') && (
          <button type="button" className="button button--primary" onClick={openCreate}>
            <PlusIcon size={17} />
            Tambah varian
          </button>
        )}
      </div>

      {feedback && (
        <div className="banner banner--success" role="status">
          {feedback}
        </div>
      )}

      <div className="panel">
        <div className="panel-heading">
          <div>
            <h3>Varian</h3>
            <p>Ukuran, warna, dan SKU untuk produk ini.</p>
          </div>
        </div>

        {variants.length === 0 ? (
          <div className="panel-state">Belum ada varian.</div>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Ukuran</th>
                  <th>Warna</th>
                  <th>Bahan (BOM)</th>
                  <th className="align-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {variants.map((variant) => (
                  <tr
                    key={variant.variant_id}
                    className={
                      selectedVariantId === variant.variant_id ? 'is-selected' : ''
                    }
                  >
                    <td>
                      <strong>{variant.sku}</strong>
                    </td>
                    <td>{variant.size}</td>
                    <td>{variant.color}</td>
                    <td>{variant.materials?.length ?? 0} bahan</td>
                    <td className="align-right">
                      <div className="row-actions">
                        <button
                          type="button"
                          className="button button--ghost button--small"
                          onClick={() =>
                            setSelectedVariantId(
                              selectedVariantId === variant.variant_id
                                ? null
                                : variant.variant_id,
                            )
                          }
                        >
                          {selectedVariantId === variant.variant_id
                            ? 'Tutup BOM'
                            : 'Atur BOM'}
                        </button>
                        {can('variants', 'U') && (
                          <button
                            type="button"
                            className="icon-button"
                            onClick={() => openEdit(variant)}
                            title="Ubah varian"
                            aria-label="Ubah varian"
                          >
                            <EditIcon size={16} />
                          </button>
                        )}
                        {can('variants', 'D') && (
                          <button
                            type="button"
                            className="icon-button icon-button--danger"
                            onClick={() => handleDeleteVariant(variant)}
                            title="Hapus varian"
                            aria-label="Hapus varian"
                          >
                            <TrashIcon size={16} />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {selectedVariant && (
        <div className="panel">
          <div className="panel-heading">
            <div>
              <h3>BOM · {selectedVariant.sku}</h3>
              <p>Kebutuhan bahan per unit untuk varian ini.</p>
            </div>
          </div>

          <BomEditor
            key={selectedVariant.variant_id}
            variant={selectedVariant}
            materials={materials}
            canEdit={can('bom', 'U')}
          />
        </div>
      )}

      {variantModal && (
        <div className="modal-backdrop" role="presentation">
          <div className="modal" role="dialog" aria-modal="true">
            <div className="modal-header">
              <h3>
                {variantModal.mode === 'edit' ? 'Ubah varian' : 'Tambah varian'}
              </h3>
              <button
                type="button"
                className="icon-button"
                onClick={() => setVariantModal(null)}
                aria-label="Tutup"
              >
                ✕
              </button>
            </div>

            <form className="modal-form" onSubmit={handleVariantSubmit}>
              {formError && (
                <div className="banner banner--error" role="alert">
                  {formError}
                </div>
              )}

              <div className="field">
                <label className="field-label" htmlFor="variant-size">
                  Ukuran *
                </label>
                <input
                  id="variant-size"
                  value={variantForm.size}
                  onChange={(event) =>
                    setVariantForm((current) => ({
                      ...current,
                      size: event.target.value,
                    }))
                  }
                  required
                />
              </div>

              <div className="field">
                <label className="field-label" htmlFor="variant-color">
                  Warna *
                </label>
                <input
                  id="variant-color"
                  value={variantForm.color}
                  onChange={(event) =>
                    setVariantForm((current) => ({
                      ...current,
                      color: event.target.value,
                    }))
                  }
                  required
                />
              </div>

              <div className="field">
                <label className="field-label" htmlFor="variant-sku">
                  SKU *
                </label>
                <input
                  id="variant-sku"
                  value={variantForm.sku}
                  onChange={(event) =>
                    setVariantForm((current) => ({
                      ...current,
                      sku: event.target.value,
                    }))
                  }
                  required
                />
              </div>

              <div className="modal-actions">
                <button
                  type="button"
                  className="button button--ghost"
                  onClick={() => setVariantModal(null)}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="button button--primary"
                  disabled={saveVariantMutation.isPending}
                >
                  {saveVariantMutation.isPending ? 'Menyimpan...' : 'Simpan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </section>
  )
}
