import { apiDownload } from './api'

// Mirrors backend App\Support\DocumentAccess; the API still enforces this.
const DOCUMENT_ROLES = {
  'nota-pesanan': ['owner', 'admin'],
  'bukti-bayar': ['owner', 'admin'],
  'nota-pembelian': ['owner', 'admin', 'gudang'],
  spk: ['owner', 'admin', 'produksi', 'gudang'],
  'hasil-produksi': ['owner', 'admin', 'produksi'],
  'surat-jalan': ['owner', 'admin'],
}

export function canAccessDocument(user, type) {
  const actions = user?.permissions?.documents || ''
  const roles = user?.role_names || []

  return (
    actions.includes('R') &&
    (DOCUMENT_ROLES[type] || []).some((role) => roles.includes(role))
  )
}

function deliver(blob, filename, mode) {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.rel = 'noopener'

  if (mode === 'open') {
    link.target = '_blank'
  } else {
    link.download = filename
  }

  document.body.appendChild(link)
  link.click()
  link.remove()

  // Keep the object URL alive long enough for the new tab to load it.
  window.setTimeout(() => URL.revokeObjectURL(url), 60000)
}

export async function openDocument(type, id) {
  const { blob, filename } = await apiDownload(`/documents/${type}/${id}`)

  deliver(blob, filename, 'open')
}

export async function downloadDocument(type, id) {
  const { blob, filename } = await apiDownload(`/documents/${type}/${id}`)

  deliver(blob, filename, 'download')
}
