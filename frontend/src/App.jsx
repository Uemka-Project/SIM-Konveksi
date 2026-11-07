import { Navigate, Route, Routes } from 'react-router-dom'
import AppShell from './components/AppShell'
import ProtectedRoute from './components/ProtectedRoute'
import CustomersPage from './pages/CustomersPage'
import DashboardPage from './pages/DashboardPage'
import EmployeesPage from './pages/EmployeesPage'
import LoginPage from './pages/LoginPage'
import MaterialsPage from './pages/MaterialsPage'
import OrderDetailPage from './pages/OrderDetailPage'
import OrderFormPage from './pages/OrderFormPage'
import OrdersPage from './pages/OrdersPage'
import ProductDetailPage from './pages/ProductDetailPage'
import ProductsPage from './pages/ProductsPage'
import PurchaseDetailPage from './pages/PurchaseDetailPage'
import PurchaseFormPage from './pages/PurchaseFormPage'
import PurchasesPage from './pages/PurchasesPage'
import StockMovementsPage from './pages/StockMovementsPage'
import SuppliersPage from './pages/SuppliersPage'
import UsersPage from './pages/UsersPage'
import './App.css'

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />

      <Route
        element={
          <ProtectedRoute>
            <AppShell />
          </ProtectedRoute>
        }
      >
        <Route index element={<DashboardPage />} />
        <Route path="customers" element={<CustomersPage />} />
        <Route path="suppliers" element={<SuppliersPage />} />
        <Route path="employees" element={<EmployeesPage />} />
        <Route path="materials" element={<MaterialsPage />} />
        <Route path="products" element={<ProductsPage />} />
        <Route path="products/:productId" element={<ProductDetailPage />} />
        <Route path="orders" element={<OrdersPage />} />
        <Route path="orders/new" element={<OrderFormPage />} />
        <Route path="orders/:orderId" element={<OrderDetailPage />} />
        <Route path="orders/:orderId/edit" element={<OrderFormPage />} />
        <Route path="purchases" element={<PurchasesPage />} />
        <Route path="purchases/new" element={<PurchaseFormPage />} />
        <Route path="purchases/:purchaseId" element={<PurchaseDetailPage />} />
        <Route path="purchases/:purchaseId/edit" element={<PurchaseFormPage />} />
        <Route path="stock-movements" element={<StockMovementsPage />} />
        <Route path="users" element={<UsersPage />} />
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
