import { useState } from 'react'
import { useAuth } from '../context/useAuth'
import {
  canAccessDocument,
  downloadDocument,
  openDocument,
} from '../lib/documents'
import { DownloadIcon, PrinterIcon } from './Icons'

/**
 * "Cetak" and "PDF" buttons for one FOD document. Renders nothing when the
 * current role is not allowed to read that document type.
 */
export default function DocumentButtons({
  type,
  id,
  label = 'Cetak',
  onError,
}) {
  const { user } = useAuth()
  const [busy, setBusy] = useState('')

  if (!id || !canAccessDocument(user, type)) {
    return null
  }

  async function run(mode) {
    setBusy(mode)

    try {
      if (mode === 'open') {
        await openDocument(type, id)
      } else {
        await downloadDocument(type, id)
      }
    } catch (caught) {
      onError?.(caught.message || 'Gagal membuat dokumen PDF. Silakan coba lagi.')
    } finally {
      setBusy('')
    }
  }

  return (
    <>
      <button
        type="button"
        className="button button--ghost"
        onClick={() => run('open')}
        disabled={Boolean(busy)}
      >
        <PrinterIcon size={16} />
        {busy === 'open' ? 'Menyiapkan...' : label}
      </button>
      <button
        type="button"
        className="button button--ghost"
        onClick={() => run('download')}
        disabled={Boolean(busy)}
        title="Unduh dokumen sebagai PDF"
      >
        <DownloadIcon size={16} />
        {busy === 'download' ? 'Mengunduh...' : 'PDF'}
      </button>
    </>
  )
}
