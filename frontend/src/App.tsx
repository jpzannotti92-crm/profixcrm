import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuth } from './contexts/AuthContext'
import { useTheme } from './contexts/ThemeContext'

// Layouts
import AuthLayout from './layouts/AuthLayout'
import DashboardLayout from './layouts/DashboardLayout'

// Pages
import LoginPage from './pages/auth/LoginPage'
import RegistrationPage from './pages/auth/RegistrationPage'
import DashboardPage from './pages/dashboard/DashboardPage'
import LeadsPage from './pages/leads/LeadsPage'
import LeadDetailPage from './pages/leads/LeadDetailPage'
import LeadImportPage from './pages/leads/LeadImportPage'
import LeadWizardPage from './pages/leads/LeadWizardPage'
import EmployeeSummaryPage from './pages/employee/EmployeeSummaryPage'
import UsersPage from './pages/users/UsersPage'
import RolesPage from './pages/roles/RolesPage'
import DesksPage from './pages/desks/DesksPage'
import TradingAccountsPage from './pages/trading/TradingAccountsPage'

// Components
import LoadingSpinner from './components/ui/LoadingSpinner'
import StatesPage from './pages/states/StatesPage'

function App() {
  const { user, isLoading, isAuthenticated } = useAuth()
  const { theme } = useTheme()

  // Aplicar tema al documento
  if (typeof document !== 'undefined') {
    document.documentElement.classList.toggle('dark', theme === 'dark')
  }

  console.log('App render - user:', user, 'isLoading:', isLoading, 'isAuthenticated:', isAuthenticated)

  if (isLoading) {
    console.log('Mostrando loading spinner...')
    return (
      <div className="min-h-screen flex items-center justify-center bg-secondary-50 dark:bg-secondary-900">
        <LoadingSpinner size="lg" />
      </div>
    )
  }

  console.log('Renderizando rutas principales...')
  return (
    <div className="min-h-screen bg-secondary-50 dark:bg-secondary-900">
      <Routes>
        {/* Rutas de autenticación */}
        <Route path="/auth" element={<AuthLayout />}>
          <Route path="login" element={<LoginPage />} />
          <Route path="register" element={<RegistrationPage />} />
          <Route index element={<Navigate to="/auth/login" replace />} />
        </Route>

        {/* Rutas protegidas */}
        {user ? (
          <>
            {/* Rutas del dashboard */}
            <Route path="/" element={<DashboardLayout />}>
              <Route index element={<Navigate to="/dashboard" replace />} />
              <Route path="dashboard" element={<DashboardPage />} />
              
              {/* Employee Summary */}
              <Route path="employee-summary" element={<EmployeeSummaryPage />} />
              
              {/* Leads */}
              <Route path="leads" element={<LeadsPage />} />
              <Route path="leads/:id" element={<LeadDetailPage />} />
              <Route path="leads/import" element={<LeadImportPage />} />
              <Route path="leads/wizard" element={<LeadWizardPage />} />
              
              {/* Usuarios y Roles */}
              <Route path="users" element={<UsersPage />} />
              <Route path="roles" element={<RolesPage />} />
              
              {/* Mesas */}
              <Route path="desks" element={<DesksPage />} />
              
              {/* Estados */}
              <Route path="/states" element={<StatesPage />} />
              
              {/* Trading */}
              <Route path="trading" element={<TradingAccountsPage />} />
            </Route>
          </>
        ) : (
          <Route path="*" element={<Navigate to="/auth/login" replace />} />
        )}

        {/* Ruta por defecto */}
        <Route path="*" element={<Navigate to={user ? "/dashboard" : "/auth/login"} replace />} />
      </Routes>
    </div>
  )
}

export default App
