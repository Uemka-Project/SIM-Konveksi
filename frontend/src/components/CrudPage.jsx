import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../context/useAuth'
import { apiRequest } from '../lib/api'
import {
  AlertIcon,
  ArrowLeftIcon,
  ArrowRightIcon,
  EditIcon,
  PlusIcon,
  SearchIcon,
  TrashIcon,
} from './Icons'

const PER_PAGE = 10

function buildInitialValues(fields) {
  return Object.fromEntries(
    fields.map((field) => [field.name, field.defaultValue ?? '']),
  )
}

export default function CrudPage({
  title,
  description,
  endpoint,
  queryKey,
  module,
  columns,
  fields,
  itemKey,
  toFormValues,
  transformPayload,
  searchPlaceholder = 'Cari data...',
  emptyMessage = 'Belum ada data.',
  rowActions,
}) {
  const { can } = useAuth()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [modal, setModal] = useState(null)
  const [form, setForm] = useState(() => buildInitialValues(fields))
  const [formError, setFormError] = useState('')
  const [feedback, setFeedback] = useState(null)

  useEffect(() => {
    if (!feedback) {
      return undefined
    }

    const timer = window.setTimeout(() => setFeedback(null), 4000)

    return () => window.clearTimeout(timer)
  }, [feedback])

  const query = useQuery({
    queryKey: [queryKey, page, search],
    queryFn: () => {
      const params = new URLSearchParams({
        page: String(page),
        per_page: String(PER_PAGE),
      })

      if (search) {
        params.set('search', search)
      }

      return apiRequest(`${endpoint}?${params.toString()}`)
    },
  })

  const saveMutation = useMutation({
    mutationFn: ({ id, payload }) =>
      apiRequest(id ? `${endpoint}/${id}` : endpoint, {
        method: id ? 'PUT' : 'POST',
        body: transformPayload ? transformPayload(payload) : payload,
      }),
    onSuccess: (response, variables) => {
      queryClient.invalidateQueries({ queryKey: [queryKey] })
      setModal(null)
      setFeedback({
        type: 'success',
        message:
          response.message ||
          (variables.id ? 'Data berhasil diperbarui.' : 'Data berhasil ditambahkan.'),
      })
    },
    onError: (error) => {
      const firstError = Object.values(error.errors || {})[0]

      setFormError(
        Array.isArray(firstError) ? firstError[0] : error.message || 'Gagal menyimpan data.',
      )
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id) =>
      apiRequest(`${endpoint}/${id}`, {
        method: 'DELETE',
      }),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: [queryKey] })
      setFeedback({
        type: 'success',
        message: response.message || 'Data berhasil dihapus.',
      })
    },
    onError: (error) => {
      setFeedback({
        type: 'error',
        message: error.message || 'Data tidak dapat dihapus.',
      })
    },
  })

  const rows = query.data?.data || []
  const meta = query.data?.meta
  const canCreate = can(module, 'C')

  function openCreate() {
    setForm(buildInitialValues(fields))
    setFormError('')
    setModal({ mode: 'create' })
  }

  function openEdit(item) {
    setForm(
      toFormValues
        ? toFormValues(item)
        : Object.fromEntries(
            fields.map((field) => [
              field.name,
              item[field.name] ?? field.defaultValue ?? '',
            ]),
          ),
    )
    setFormError('')
    setModal({ mode: 'edit', item })
  }

  function handleDelete(item) {
    const label = item.name || item.product_name || item.material_name || 'data ini'

    if (!window.confirm(`Hapus ${label}? Tindakan ini tidak dapat dibatalkan.`)) {
      return
    }

    deleteMutation.mutate(item[itemKey])
  }

  function handleFieldChange(event) {
    const { name, type, value, checked } = event.target

    setForm((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }))
    setFormError('')
  }

  function handleSubmit(event) {
    event.preventDefault()
    setFormError('')

    const payload = { ...form }

    for (const field of fields) {
      if (field.type === 'number') {
        payload[field.name] =
          payload[field.name] === '' ? '' : Number(payload[field.name])
      }

      if (field.type === 'checkbox') {
        payload[field.name] = Boolean(payload[field.name])
      }

      if (!field.required && (payload[field.name] === '' || payload[field.name] == null)) {
        delete payload[field.name]
      }
    }

    saveMutation.mutate({
      id: modal.mode === 'edit' ? modal.item[itemKey] : null,
      payload,
    })
  }

  function submitSearch(event) {
    event.preventDefault()
    setPage(1)
    setSearch(searchInput.trim())
  }

  return (
    <section className="page-stack">
      <div className="page-heading">
        <div>
          <h2>{title}</h2>
          {description && <p>{description}</p>}
        </div>

        {canCreate && (
          <button type="button" className="button button--primary" onClick={openCreate}>
            <PlusIcon size={17} />
            Tambah
          </button>
        )}
      </div>

      {feedback && (
        <div
          className={
            feedback.type === 'error' ? 'banner banner--error' : 'banner banner--success'
          }
          role="status"
        >
          {feedback.message}
        </div>
      )}

      <div className="panel">
        <div className="panel-toolbar">
          <form className="search-box" onSubmit={submitSearch} role="search">
            <SearchIcon size={17} />
            <input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder={searchPlaceholder}
              aria-label={searchPlaceholder}
            />
            <button type="submit">Cari</button>
          </form>

          {meta && (
            <span className="panel-meta">
              {meta.total} data · halaman {meta.current_page} dari {meta.last_page}
            </span>
          )}
        </div>

        {query.isLoading ? (
          <div className="panel-state">
            <span className="spinner spinner--dark" />
            Memuat data...
          </div>
        ) : query.isError ? (
          <div className="panel-state panel-state--error">
            <AlertIcon size={18} />
            {query.error.message}
          </div>
        ) : rows.length === 0 ? (
          <div className="panel-state">{emptyMessage}</div>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  {columns.map((column) => (
                    <th key={column.key}>{column.label}</th>
                  ))}
                  {(can(module, 'U') || can(module, 'D') || rowActions) && (
                    <th className="align-right">Aksi</th>
                  )}
                </tr>
              </thead>
              <tbody>
                {rows.map((item) => (
                  <tr key={item[itemKey]}>
                    {columns.map((column) => (
                      <td key={column.key}>
                        {column.render
                          ? column.render(item)
                          : item[column.key] ?? '—'}
                      </td>
                    ))}
                    {(can(module, 'U') || can(module, 'D') || rowActions) && (
                      <td className="align-right">
                        <div className="row-actions">
                          {rowActions?.(item)}
                          {can(module, 'U') && (
                            <button
                              type="button"
                              className="icon-button"
                              onClick={() => openEdit(item)}
                              title="Ubah"
                              aria-label="Ubah"
                            >
                              <EditIcon size={16} />
                            </button>
                          )}
                          {can(module, 'D') && (
                            <button
                              type="button"
                              className="icon-button icon-button--danger"
                              onClick={() => handleDelete(item)}
                              title="Hapus"
                              aria-label="Hapus"
                            >
                              <TrashIcon size={16} />
                            </button>
                          )}
                        </div>
                      </td>
                    )}
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

      {modal && (
        <div className="modal-backdrop" role="presentation">
          <div className="modal" role="dialog" aria-modal="true">
            <div className="modal-header">
              <h3>{modal.mode === 'edit' ? `Ubah ${title}` : `Tambah ${title}`}</h3>
              <button
                type="button"
                className="icon-button"
                onClick={() => setModal(null)}
                aria-label="Tutup"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleSubmit} className="modal-form">
              {formError && (
                <div className="banner banner--error" role="alert">
                  {formError}
                </div>
              )}

              {fields.map((field) => (
                <div className="field" key={field.name}>
                  {field.type === 'checkbox' ? (
                    <label className="checkbox-field">
                      <input
                        type="checkbox"
                        name={field.name}
                        checked={Boolean(form[field.name])}
                        onChange={handleFieldChange}
                      />
                      <span>{field.label}</span>
                    </label>
                  ) : (
                    <>
                      <label className="field-label" htmlFor={`field-${field.name}`}>
                        {field.label}
                        {field.required ? ' *' : ''}
                      </label>

                      {field.type === 'textarea' ? (
                        <textarea
                          id={`field-${field.name}`}
                          name={field.name}
                          value={form[field.name] ?? ''}
                          onChange={handleFieldChange}
                          rows={3}
                          required={field.required}
                        />
                      ) : field.type === 'select' ? (
                        <select
                          id={`field-${field.name}`}
                          name={field.name}
                          value={form[field.name] ?? ''}
                          onChange={handleFieldChange}
                          required={field.required}
                        >
                          <option value="">— Pilih —</option>
                          {field.options?.map((option) => (
                            <option key={option.value} value={option.value}>
                              {option.label}
                            </option>
                          ))}
                        </select>
                      ) : (
                        <input
                          id={`field-${field.name}`}
                          name={field.name}
                          type={field.type || 'text'}
                          value={form[field.name] ?? ''}
                          onChange={handleFieldChange}
                          placeholder={field.placeholder}
                          required={field.required}
                          min={field.min}
                          max={field.max}
                          step={field.step}
                          autoComplete={field.autoComplete}
                        />
                      )}

                      {field.help && <small className="field-help">{field.help}</small>}
                    </>
                  )}
                </div>
              ))}

              <div className="modal-actions">
                <button
                  type="button"
                  className="button button--ghost"
                  onClick={() => setModal(null)}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="button button--primary"
                  disabled={saveMutation.isPending}
                >
                  {saveMutation.isPending ? 'Menyimpan...' : 'Simpan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </section>
  )
}
