import CrudPage from '../components/CrudPage'

export default function SuppliersPage() {
  return (
    <CrudPage
      title="Pemasok"
      description="Pemasok bahan baku untuk kebutuhan produksi."
      endpoint="/suppliers"
      queryKey="suppliers"
      module="suppliers"
      itemKey="supplier_id"
      searchPlaceholder="Cari nama, telepon, atau email..."
      emptyMessage="Belum ada pemasok."
      columns={[
        { key: 'name', label: 'Nama' },
        { key: 'phone', label: 'Telepon' },
        { key: 'email', label: 'Email' },
        { key: 'address', label: 'Alamat' },
        { key: 'purchases_count', label: 'Pembelian' },
      ]}
      fields={[
        { name: 'name', label: 'Nama pemasok', required: true },
        { name: 'phone', label: 'Telepon' },
        { name: 'email', label: 'Email', type: 'email' },
        { name: 'address', label: 'Alamat', type: 'textarea' },
      ]}
    />
  )
}
