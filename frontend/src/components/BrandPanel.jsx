import {
  CheckIcon,
  GarmentIcon,
} from './Icons'

const productionItems = [
  { label: 'Kemeja Seragam', value: 72 },
  { label: 'Jaket Komunitas', value: 48 },
  { label: 'Celana Kerja', value: 91 },
]

export default function BrandPanel() {
  return (
    <aside className="brand-panel" aria-label="Tentang aplikasi">
      <div className="brand-glow brand-glow--one" aria-hidden="true" />
      <div className="brand-glow brand-glow--two" aria-hidden="true" />

      <div className="brand-top">
        <div className="brand-logo">
          <GarmentIcon size={24} />
        </div>
        <div>
          <span className="brand-name">SIM Konveksi</span>
          <span className="brand-sub">Manajemen Produksi</span>
        </div>
      </div>

      <div className="brand-copy">
        <span className="eyebrow">Produksi lebih terukur</span>
        <h1>Dari bahan mentah sampai pesanan siap kirim.</h1>
        <p>
          Pantau alur produksi, kebutuhan bahan, dan progres pesanan konveksi
          dalam satu sistem yang rapi dan terpusat.
        </p>

        <ul className="feature-list">
          <li>
            <span className="feature-check">
              <CheckIcon size={14} />
            </span>
            Alur produksi dan pesanan terpusat
          </li>
          <li>
            <span className="feature-check">
              <CheckIcon size={14} />
            </span>
            Stok bahan yang selalu terbarui
          </li>
          <li>
            <span className="feature-check">
              <CheckIcon size={14} />
            </span>
            Laporan progres siap dipantau
          </li>
        </ul>
      </div>

      <div className="brand-preview" aria-hidden="true">
        <div className="preview-header">
          <div>
            <span className="preview-label">Progres Produksi</span>
            <strong>Ringkasan minggu ini</strong>
          </div>
          <span className="preview-live">
            <span className="live-dot" />
            Live
          </span>
        </div>

        {productionItems.map((item) => (
          <div className="preview-item" key={item.label}>
            <div className="preview-meta">
              <span>{item.label}</span>
              <span>{item.value}%</span>
            </div>
            <div className="preview-bar">
              <span style={{ width: `${item.value}%` }} />
            </div>
          </div>
        ))}
      </div>

      <footer className="brand-footer">
        <span>© 2026 SIM Produksi Konveksi</span>
        <span className="brand-status">
          <span className="status-dot" />
          Sistem siap
        </span>
      </footer>
    </aside>
  )
}
