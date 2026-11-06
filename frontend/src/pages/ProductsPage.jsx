import { Link } from 'react-router-dom'
import CrudPage from '../components/CrudPage'
import { ArrowRightIcon } from '../components/Icons'

export default function ProductsPage() {
  return (
    <CrudPage
      title="Produk & Varian"
      description="Kelola produk beserta varian ukuran, warna, dan BOM-nya."
      endpoint="/products"
      queryKey="products"
      module="products"
      itemKey="product_id"
      searchPlaceholder="Cari nama atau deskripsi produk..."
      emptyMessage="Belum ada produk."
      columns={[
        { key: 'product_name', label: 'Produk' },
        { key: 'description', label: 'Deskripsi' },
        { key: 'variants_count', label: 'Varian' },
      ]}
      fields={[
        { name: 'product_name', label: 'Nama produk', required: true },
        { name: 'description', label: 'Deskripsi', type: 'textarea' },
      ]}
      rowActions={(item) => (
        <Link
          className="button button--ghost button--small"
          to={`/products/${item.product_id}`}
        >
          Varian
          <ArrowRightIcon size={15} />
        </Link>
      )}
    />
  )
}
