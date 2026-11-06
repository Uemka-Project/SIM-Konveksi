import CrudPage from '../components/CrudPage'

export default function EmployeesPage() {
  return (
    <CrudPage
      title="Karyawan"
      description="Karyawan yang terlibat dalam proses produksi."
      endpoint="/employees"
      queryKey="employees"
      module="employees"
      itemKey="employee_id"
      searchPlaceholder="Cari nama, telepon, atau jabatan..."
      emptyMessage="Belum ada karyawan."
      columns={[
        { key: 'name', label: 'Nama' },
        { key: 'position', label: 'Jabatan' },
        { key: 'phone', label: 'Telepon' },
        {
          key: 'active_status',
          label: 'Status',
          render: (item) => (
            <span
              className={
                item.active_status ? 'badge badge--success' : 'badge badge--muted'
              }
            >
              {item.active_status ? 'Aktif' : 'Nonaktif'}
            </span>
          ),
        },
        {
          key: 'user',
          label: 'Akun',
          render: (item) => item.user?.username || '—',
        },
      ]}
      fields={[
        { name: 'name', label: 'Nama karyawan', required: true },
        { name: 'phone', label: 'Telepon' },
        { name: 'position', label: 'Jabatan', required: true },
        {
          name: 'active_status',
          label: 'Karyawan aktif',
          type: 'checkbox',
          defaultValue: true,
        },
      ]}
      toFormValues={(item) => ({
        name: item.name,
        phone: item.phone || '',
        position: item.position,
        active_status: Boolean(item.active_status),
      })}
    />
  )
}
