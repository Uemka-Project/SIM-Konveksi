import CrudPage from '../components/CrudPage'

export default function CustomersPage() {
  return (
    <CrudPage
      title="Pelanggan"
      description="Data pelanggan yang memesan produk konveksi."
      endpoint="/customers"
      queryKey="customers"
      module="customers"
      itemKey="customer_id"
      searchPlaceholder="Cari nama, telepon, atau email..."
      emptyMessage="Belum ada pelanggan."
      columns={[
        { key: 'name', label: 'Nama' },
        { key: 'phone', label: 'Telepon' },
        { key: 'email', label: 'Email' },
        { key: 'address', label: 'Alamat' },
        { key: 'orders_count', label: 'Pesanan' },
      ]}
      fields={[
        { name: 'name', label: 'Nama pelanggan', required: true },
        { name: 'phone', label: 'Telepon' },
        { name: 'email', label: 'Email', type: 'email' },
        { name: 'address', label: 'Alamat', type: 'textarea' },
      ]}
    />
  )
}
