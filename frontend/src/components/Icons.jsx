function IconBase({ children, size = 18, className = '', ...props }) {
  return (
    <svg
      className={className}
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
      {...props}
    >
      {children}
    </svg>
  )
}

export function MailIcon(props) {
  return (
    <IconBase {...props}>
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="m3 7 9 6 9-6" />
    </IconBase>
  )
}

export function LockIcon(props) {
  return (
    <IconBase {...props}>
      <rect x="4.5" y="10" width="15" height="10.5" rx="2" />
      <path d="M8 10V7a4 4 0 0 1 8 0v3" />
    </IconBase>
  )
}

export function EyeIcon(props) {
  return (
    <IconBase {...props}>
      <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
      <circle cx="12" cy="12" r="2.5" />
    </IconBase>
  )
}

export function EyeOffIcon(props) {
  return (
    <IconBase {...props}>
      <path d="m3 3 18 18" />
      <path d="M10.6 6.2A10.8 10.8 0 0 1 12 6c6 0 9.5 6 9.5 6a17.4 17.4 0 0 1-3.1 3.9" />
      <path d="M6.5 6.7C4 8.4 2.5 12 2.5 12s3.5 6 9.5 6a10.9 10.9 0 0 0 4.3-.9" />
      <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
    </IconBase>
  )
}

export function ArrowRightIcon(props) {
  return (
    <IconBase {...props}>
      <path d="M5 12h14" />
      <path d="m13 6 6 6-6 6" />
    </IconBase>
  )
}

export function AlertIcon(props) {
  return (
    <IconBase {...props}>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 8v4" />
      <path d="M12 16h.01" />
    </IconBase>
  )
}

export function CheckIcon(props) {
  return (
    <IconBase {...props}>
      <path d="m5 12 4 4L19 6" />
    </IconBase>
  )
}

export function ShieldIcon(props) {
  return (
    <IconBase {...props}>
      <path d="M12 3 4.5 6v5.5c0 4.7 3.2 7.9 7.5 9.5 4.3-1.6 7.5-4.8 7.5-9.5V6L12 3Z" />
      <path d="m9 12 2 2 4-4" />
    </IconBase>
  )
}

export function GarmentIcon(props) {
  return (
    <IconBase {...props}>
      <path d="M9 4.5 12 3l3 1.5L20 7l-2 4-1.5-.7V21h-9V10.3L6 11 4 7l5-2.5Z" />
      <path d="M9 4.5a3 3 0 0 0 6 0" />
    </IconBase>
  )
}

export function LogoutIcon(props) {
  return (
    <IconBase {...props}>
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
      <path d="m16 17 5-5-5-5" />
      <path d="M21 12H9" />
    </IconBase>
  )
}

export function DashboardIcon(props) {
  return (
    <IconBase {...props}>
      <rect x="3" y="3" width="7" height="9" rx="1.5" />
      <rect x="14" y="3" width="7" height="5" rx="1.5" />
      <rect x="14" y="12" width="7" height="9" rx="1.5" />
      <rect x="3" y="16" width="7" height="5" rx="1.5" />
    </IconBase>
  )
}

export function UsersIcon(props) {
  return (
    <IconBase {...props}>
      <circle cx="9" cy="8" r="3.2" />
      <path d="M3 20c0-3 2.7-5 6-5s6 2 6 5" />
      <path d="M16 5.2A3.2 3.2 0 0 1 16 11" />
      <path d="M18 15c2 .7 3 2.3 3 5" />
    </IconBase>
  )
}

export function CustomerIcon(props) {
  return (
    <IconBase {...props}>
      <circle cx="12" cy="8" r="3.5" />
      <path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6" />
    </IconBase>
  )
}

export function SupplierIcon(props) {
  return (
    <IconBase {...props}>
      <path d="M3 9.5 12 3l9 6.5V21H3V9.5Z" />
      <path d="M8 21v-6h8v6" />
      <path d="M8 9h8" />
    </IconBase>
  )
}

export function EmployeeIcon(props) {
  return (
    <IconBase {...props}>
      <rect x="3" y="7" width="18" height="13" rx="2" />
      <path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" />
      <path d="M3 12h18" />
    </IconBase>
  )
}

export function MaterialIcon(props) {
  return (
    <IconBase {...props}>
      <path d="M4 7.5 12 3l8 4.5-8 4.5-8-4.5Z" />
      <path d="M4 7.5V16l8 4.5 8-4.5V7.5" />
      <path d="M12 12v8.5" />
    </IconBase>
  )
}

export function ProductIcon(props) {
  return (
    <IconBase {...props}>
      <path d="M20.4 8.2 12 3.5 3.6 8.2" />
      <path d="M20.4 8.2v7.6L12 20.5l-8.4-4.7V8.2" />
      <path d="m3.6 8.2 8.4 4.7 8.4-4.7" />
      <path d="M12 12.9v7.6" />
    </IconBase>
  )
}

export function PlusIcon(props) {
  return (
    <IconBase {...props}>
      <path d="M12 5v14" />
      <path d="M5 12h14" />
    </IconBase>
  )
}

export function SearchIcon(props) {
  return (
    <IconBase {...props}>
      <circle cx="11" cy="11" r="6.5" />
      <path d="m16 16 4.5 4.5" />
    </IconBase>
  )
}

export function EditIcon(props) {
  return (
    <IconBase {...props}>
      <path d="M4 20h4l10-10-4-4L4 16v4Z" />
      <path d="m13.5 5.5 4 4" />
    </IconBase>
  )
}

export function TrashIcon(props) {
  return (
    <IconBase {...props}>
      <path d="M4 7h16" />
      <path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
      <path d="m6 7 1 13h10l1-13" />
      <path d="M10 11v5M14 11v5" />
    </IconBase>
  )
}

export function ArrowLeftIcon(props) {
  return (
    <IconBase {...props}>
      <path d="M19 12H5" />
      <path d="m11 18-6-6 6-6" />
    </IconBase>
  )
}

export function AlertTriangleIcon(props) {
  return (
    <IconBase {...props}>
      <path d="M12 3 2.8 20h18.4L12 3Z" />
      <path d="M12 9v5" />
      <path d="M12 17h.01" />
    </IconBase>
  )
}

