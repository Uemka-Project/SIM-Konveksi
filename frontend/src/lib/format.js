export function formatRupiah(value) {
  const number = Number(value)

  if (Number.isNaN(number)) {
    return 'Rp0'
  }

  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(number)
}

export function formatNumber(value, maximumFractionDigits = 2) {
  const number = Number(value)

  if (Number.isNaN(number)) {
    return '0'
  }

  return new Intl.NumberFormat('id-ID', {
    maximumFractionDigits,
  }).format(number)
}

export function formatDate(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(`${value}T00:00:00`)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

export function formatDateTime(value) {
  if (!value) {
    return '—'
  }

  const normalized = String(value).includes('T')
    ? String(value)
    : String(value).replace(' ', 'T')
  const date = new Date(normalized)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}
