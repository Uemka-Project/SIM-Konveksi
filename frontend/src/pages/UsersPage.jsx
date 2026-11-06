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
} from '../components/Icons'

const PER_PAGE = 10
const EMPTY_FORM = {
  username: '',
  email: '',
  password: '',
  employee_id: '',
  is_active: true,
  role_ids: [],
}

export default function UsersPage() {
  const { can, user: currentUser } = useAuth()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [modal, setModal] = useState(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [formError, setFormError] = useState('')
  const [feedback, setFeedback] = useState(null)

  useEffect(() => {
    if (!feedback) {
      return undefined
    }

    const timer = window.setTimeout(() => setFeedback(null), 4000)

    return () => window.clearTimeout(timer)
  }, [feedback])

  const usersQuery = useQuery({
    queryKey: ['users', page, search],
    queryFn: () => {
      const params = new URLSearchParams({
        page: String(page),
        per_page: String(PER_PAGE),
      })

      if (search) {
        params.set('search', search)
      }

      return apiRequest(`/users?${params.toString()}`)
    },
    enabled: can('users', 'R'),
  })

  const rolesQuery = useQuery({
    queryKey: ['roles'],
    queryFn: () => apiRequest('/roles'),
    enabled: can('users', 'R'),
  })

  const employeesQuery = useQuery({
    queryKey: ['employees', 'options'],
    queryFn: () => apiRequest('/employees?per_page=100'),
    enabled: can('users', 'R'),
  })

  const saveMutation = useMutation({
    mutationFn: ({ id, payload }) =>
      apiRequest(id ? `/users/${id}` : '/users', {
        method: id ? 'PUT' : 'POST',
        body: payload,
      }),
    onSuccess: (response, variables) => {
      queryClient.invalidateQueries({ queryKey: ['users'] })
      setModal(null)
      setFeedback({
        type: 'success',
        message:
          response.message ||
          (variables.id ? 'Pengguna diperbarui.' : 'Pengguna ditambahkan.'),
      })
    },
    onError: (error) => {
      const firstError = Object.values(error.errors || {})[0]

      setFormError(
        Array.isArray(firstError) ? firstError[0] : error.message || 'Gagal menyimpan pengguna.',
      )
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id) => apiRequest(`/users/${id}`, { method: 'DELETE' }),
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['users'] })
      setFeedback({ type: 'success', message: response.message })
    },
    onError: (error) => {
      setFeedback({ type: 'error', message: error.message })
    },
  })

  if (!can('users', 'R')) {
    return (
      <div className="banner banner--error" role="alert">
        <AlertIcon size={18} />
        Anda tidak memiliki akses ke pengelolaan pengguna.
      </div>
    )
  }

  const rows = usersQuery.data?.data || []
  const meta = usersQuery.data?.meta
  const roles = rolesQuery.data?.data || []
  const employees = employeesQuery.data?.data || []

  function openCreate() {
    setForm(EMPTY_FORM)
    setFormError('')
    setModal({ mode: 'create' })
  }

  function openEdit(user) {
    setForm({
      username: user.username,
      email: user.email,
      password: '',
      employee_id: user.employee_id ? String(user.employee_id) : '',
      is_active: Boolean(user.is_active),
      role_ids: (user.roles || []).map((role) => role.role_id),
    })
    setFormError('')
    setModal({ mode: 'edit', item: user })
  }

  function handleDelete(user) {
    if (
      !window.confirm(
        `Nonaktifkan akun ${user.username}? Seluruh sesi pengguna akan dicabut.`,
      )
    ) {
      return
    }

    deleteMutation.mutate(user.user_id)
  }

  function handleChange(event) {
    const { name, type, value, checked } = event.target

    setForm((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }))
    setFormError('')
  }

  function toggleRole(roleId) {
    setForm((current) => ({
      ...current,
      role_ids: current.role_ids.includes(roleId)
        ? current.role_ids.filter((id) => id !== roleId)
        : [...current.role_ids, roleId],
    }))
    setFormError('')
  }

  function handleSubmit(event) {
    event.preventDefault()
    setFormError('')

    if (form.role_ids.length === 0) {
      setFormError('Pilih minimal satu peran untuk pengguna ini.')
      return
    }

    const payload = {
      username: form.username.trim(),
      email: form.email.trim(),
      employee_id: form.employee_id ? Number(form.employee_id) : null,
      is_active: Boolean(form.is_active),
      role_ids: form.role_ids,
    }

    if (form.password) {
      payload.password = form.password
    } else if (modal.mode === 'create') {
      setFormError('Password wajib diisi untuk pengguna baru.')
      return
    }

    saveMutation.mutate({
      id: modal.mode === 'edit' ? modal.item.user_id : null,
      payload,
    })
  }

  return (
    <section className="page-stack">
      <div className="page-heading">
        <div>
          <h2>Pengguna &amp; Peran</h2>
          <p>Kelola akun, peran akses, dan keterkaitan dengan data karyawan.</p>
        </div>

        {can('users', 'C') && (
          <button type="button" className="button button--primary" onClick={openCreate}>
            <PlusIcon size={17} />
            Tambah pengguna
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
          <form
            className="search-box"
            role="search"
            onSubmit={(event) => {
              event.preventDefault()
              setPage(1)
              setSearch(searchInput.trim())
            }}
          >
            <SearchIcon size={17} />
            <input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Cari username, email, atau nama karyawan..."
              aria-label="Cari pengguna"
            />
            <button type="submit">Cari</button>
          </form>

          {meta && (
            <span className="panel-meta">
              {meta.total} pengguna · halaman {meta.current_page} dari {meta.last_page}
            </span>
          )}
        </div>

        {usersQuery.isLoading ? (
          <div className="panel-state">
            <span className="spinner spinner--dark" />
            Memuat pengguna...
          </div>
        ) : rows.length === 0 ? (
          <div className="panel-state">Belum ada pengguna.</div>
        ) : (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Username</th>
                  <th>Nama</th>
                  <th>Email</th>
                  <th>Peran</th>
                  <th>Status</th>
                  <th className="align-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((user) => (
                  <tr key={user.user_id}>
                    <td>
                      <strong>{user.username}</strong>
                    </td>
                    <td>{user.employee?.name || user.name || '—'}</td>
                    <td>{user.email}</td>
                    <td>
                      <div className="badge-row">
                        {(user.roles || []).map((role) => (
                          <span className="badge badge--muted" key={role.role_id}>
                            {role.role_name}
                          </span>
                        ))}
                      </div>
                    </td>
                    <td>
                      <span
                        className={
                          user.is_active ? 'badge badge--success' : 'badge badge--danger'
                        }
                      >
                        {user.is_active ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </td>
                    <td className="align-right">
                      <div className="row-actions">
                        {can('users', 'U') && (
                          <button
                            type="button"
                            className="icon-button"
                            onClick={() => openEdit(user)}
                            title="Ubah"
                            aria-label="Ubah"
                          >
                            <EditIcon size={16} />
                          </button>
                        )}
                        {can('users', 'D') && user.user_id !== currentUser?.user_id && (
                          <button
                            type="button"
                            className="icon-button icon-button--danger"
                            onClick={() => handleDelete(user)}
                            title="Nonaktifkan"
                            aria-label="Nonaktifkan"
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
              <h3>
                {modal.mode === 'edit' ? 'Ubah pengguna' : 'Tambah pengguna'}
              </h3>
              <button
                type="button"
                className="icon-button"
                onClick={() => setModal(null)}
                aria-label="Tutup"
              >
                ✕
              </button>
            </div>

            <form className="modal-form" onSubmit={handleSubmit}>
              {formError && (
                <div className="banner banner--error" role="alert">
                  {formError}
                </div>
              )}

              <div className="field">
                <label className="field-label" htmlFor="user-username">
                  Username *
                </label>
                <input
                  id="user-username"
                  name="username"
                  value={form.username}
                  onChange={handleChange}
                  required
                  autoComplete="off"
                />
              </div>

              <div className="field">
                <label className="field-label" htmlFor="user-email">
                  Email *
                </label>
                <input
                  id="user-email"
                  name="email"
                  type="email"
                  value={form.email}
                  onChange={handleChange}
                  required
                  autoComplete="off"
                />
              </div>

              <div className="field">
                <label className="field-label" htmlFor="user-password">
                  Password {modal.mode === 'create' ? '*' : '(kosongkan bila tidak diubah)'}
                </label>
                <input
                  id="user-password"
                  name="password"
                  type="password"
                  value={form.password}
                  onChange={handleChange}
                  required={modal.mode === 'create'}
                  minLength={8}
                  autoComplete="new-password"
                />
              </div>

              <div className="field">
                <label className="field-label" htmlFor="user-employee">
                  Karyawan terkait
                </label>
                <select
                  id="user-employee"
                  name="employee_id"
                  value={form.employee_id}
                  onChange={handleChange}
                >
                  <option value="">— Tidak terhubung —</option>
                  {employees.map((employee) => (
                    <option key={employee.employee_id} value={employee.employee_id}>
                      {employee.name} · {employee.position}
                    </option>
                  ))}
                </select>
              </div>

              <fieldset className="role-fieldset">
                <legend>Peran *</legend>
                <div className="role-options">
                  {roles.map((role) => (
                    <label className="checkbox-field" key={role.role_id}>
                      <input
                        type="checkbox"
                        checked={form.role_ids.includes(role.role_id)}
                        onChange={() => toggleRole(role.role_id)}
                      />
                      <span>{role.role_name}</span>
                    </label>
                  ))}
                </div>
              </fieldset>

              <label className="checkbox-field">
                <input
                  type="checkbox"
                  name="is_active"
                  checked={form.is_active}
                  onChange={handleChange}
                />
                <span>Akun aktif</span>
              </label>

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
