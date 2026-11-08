import { Navigate, Route, Routes } from 'react-router-dom'
import AppShell from './components/AppShell'
import ProtectedRoute from './components/ProtectedRoute'
import { useAuth } from './context/useAuth'
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
import ProductionOrderDetailPage from './pages/ProductionOrderDetailPage'
import ProductionOrdersPage from './pages/ProductionOrdersPage'
import PurchaseDetailPage from './pages/PurchaseDetailPage'
import PurchaseFormPage from './pages/PurchaseFormPage'
import PurchasesPage from './pages/PurchasesPage'
import ReportsPage from './pages/ReportsPage'
import StockMovementsPage from './pages/StockMovementsPage'
import SuppliersPage from './pages/SuppliersPage'
import UsersPage from './pages/UsersPage'
import './App.css'

/**
 * Front-end guard that mirrors App\Support\AccessMatrix. The API stays the
 * source of truth; this only keeps users out of screens they cannot read.
 */
function ModuleRoute({ module, children }) {
  const { can } = useAuth()

  if (!can(module, 'R')) {
    return (
      <div className="banner banner--error" role="alert">
        Anda tidak memiliki akses ke halaman ini.
      </div>
    )
  }

  return children
}

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
        <Route
          index
          element={
            <ModuleRoute module="materials">
              <DashboardPage />
            </ModuleRoute>
          }
        />
        <Route
          path="customers"
          element={
            <ModuleRoute module="customers">
              <CustomersPage />
            </ModuleRoute>
          }
        />
        <Route
          path="suppliers"
          element={
            <ModuleRoute module="suppliers">
              <SuppliersPage />
            </ModuleRoute>
          }
        />
        <Route
          path="employees"
          element={
            <ModuleRoute module="employees">
              <EmployeesPage />
            </ModuleRoute>
          }
        />
        <Route
          path="materials"
          element={
            <ModuleRoute module="materials">
              <MaterialsPage />
            </ModuleRoute>
          }
        />
        <Route
          path="products"
          element={
            <ModuleRoute module="products">
              <ProductsPage />
            </ModuleRoute>
          }
        />
        <Route
          path="products/:productId"
          element={
            <ModuleRoute module="products">
              <ProductDetailPage />
            </ModuleRoute>
          }
        />
        <Route
          path="orders"
          element={
            <ModuleRoute module="orders">
              <OrdersPage />
            </ModuleRoute>
          }
        />
        <Route
          path="orders/new"
          element={
            <ModuleRoute module="orders">
              <OrderFormPage />
            </ModuleRoute>
          }
        />
        <Route
          path="orders/:orderId"
          element={
            <ModuleRoute module="orders">
              <OrderDetailPage />
            </ModuleRoute>
          }
        />
        <Route
          path="orders/:orderId/edit"
          element={
            <ModuleRoute module="orders">
              <OrderFormPage />
            </ModuleRoute>
          }
        />
        <Route
          path="production-orders"
          element={
            <ModuleRoute module="production">
              <ProductionOrdersPage />
            </ModuleRoute>
          }
        />
        <Route
          path="production-orders/:productionOrderId"
          element={
            <ModuleRoute module="production">
              <ProductionOrderDetailPage />
            </ModuleRoute>
          }
        />
        <Route
          path="purchases"
          element={
            <ModuleRoute module="purchases">
              <PurchasesPage />
            </ModuleRoute>
          }
        />
        <Route
          path="purchases/new"
          element={
            <ModuleRoute module="purchases">
              <PurchaseFormPage />
            </ModuleRoute>
          }
        />
        <Route
          path="purchases/:purchaseId"
          element={
            <ModuleRoute module="purchases">
              <PurchaseDetailPage />
            </ModuleRoute>
          }
        />
        <Route
          path="purchases/:purchaseId/edit"
          element={
            <ModuleRoute module="purchases">
              <PurchaseFormPage />
            </ModuleRoute>
          }
        />
        <Route
          path="stock-movements"
          element={
            <ModuleRoute module="stock">
              <StockMovementsPage />
            </ModuleRoute>
          }
        />
        <Route
          path="reports"
          element={
            <ModuleRoute module="reports">
              <ReportsPage />
            </ModuleRoute>
          }
        />
        <Route
          path="users"
          element={
            <ModuleRoute module="users">
              <UsersPage />
            </ModuleRoute>
          }
        />
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
