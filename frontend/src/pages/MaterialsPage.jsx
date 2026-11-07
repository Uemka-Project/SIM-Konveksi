import { Link } from 'react-router-dom'
import CrudPage from '../components/CrudPage'
import { formatNumber } from '../lib/format'

export default function MaterialsPage() {
  return (
    <CrudPage
      title="Bahan Baku"
      description="Stok bahan diperbarui lewat buku besar dari penerimaan pembelian dan serah bahan produksi."
      endpoint="/materials"
      queryKey="materials"
      module="materials"
      itemKey="material_id"
      searchPlaceholder="Cari nama bahan atau satuan..."
      emptyMessage="Belum ada bahan baku."
      columns={[
        { key: 'material_name', label: 'Bahan' },
        { key: 'unit_name', label: 'Satuan' },
        {
          key: 'current_stock',
          label: 'Stok Saat Ini',
          render: (item) => formatNumber(item.current_stock),
        },
        {
          key: 'minimum_stock',
          label: 'Stok Minimum',
          render: (item) => formatNumber(item.minimum_stock),
        },
        {
          key: 'is_low_stock',
          label: 'Status',
          render: (item) =>
            item.is_low_stock ? (
              <span className="badge badge--danger">Stok menipis</span>
            ) : (
              <span className="badge badge--success">Aman</span>
            ),
        },
        {
          key: 'ledger',
          label: 'Riwayat',
          render: (item) => (
            <Link
              className="text-link"
              to={`/stock-movements?material_id=${item.material_id}`}
            >
              Kartu stok
            </Link>
          ),
        },
      ]}
      fields={[
        { name: 'material_name', label: 'Nama bahan', required: true },
        { name: 'unit_name', label: 'Satuan', required: true, placeholder: 'meter, roll, kg' },
        {
          name: 'minimum_stock',
          label: 'Stok minimum',
          type: 'number',
          min: 0,
          step: '0.01',
          required: true,
        },
      ]}
    />
  )
}
