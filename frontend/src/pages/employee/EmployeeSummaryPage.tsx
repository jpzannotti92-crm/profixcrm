import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { 
  UserIcon, 
  ChartBarIcon, 
  ClockIcon, 
  TrophyIcon,
  PhoneIcon,
  EnvelopeIcon,
  CalendarDaysIcon,
  CurrencyDollarIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon
} from '@heroicons/react/24/outline'
import { leadsApi, employeeApi } from '../../services/api'
import { useAuth } from '../../contexts/AuthContext'
import LoadingSpinner from '../../components/ui/LoadingSpinner'

interface EmployeeStats {
  totalLeads: number
  activeLeads: number
  convertedLeads: number
  conversionRate: number
  monthlyTarget: number
  monthlyProgress: number
  todayActivities: number
  weekActivities: number
}

interface RecentActivity {
  id: number
  type: string
  description: string
  timestamp: string
  leadName?: string
}

const EmployeeSummaryPage = () => {
  const { user } = useAuth()
  const [selectedPeriod, setSelectedPeriod] = useState('month')

  // Obtener estadísticas del empleado
  const { data: stats, isLoading: statsLoading } = useQuery<EmployeeStats>({
    queryKey: ['employee-stats', user?.id, selectedPeriod],
    queryFn: async () => {
      const response = await employeeApi.getStats(selectedPeriod)
      return response.data
    },
    enabled: !!user?.id
  })

  // Obtener actividades recientes
  const { data: activities, isLoading: activitiesLoading } = useQuery<RecentActivity[]>({
    queryKey: ['employee-activities', user?.id],
    queryFn: async () => {
      const response = await employeeApi.getActivities(10)
      return response.data
    },
    enabled: !!user?.id
  })

  // Obtener leads asignados (solo los propios)
  const { data: myLeads, isLoading: leadsLoading } = useQuery({
    queryKey: ['my-leads', user?.id],
    queryFn: () => leadsApi.getLeads({ 
      assigned_to: user?.id,
      limit: 10,
      status: 'active'
    }),
    enabled: !!user?.id
  })

  if (statsLoading || activitiesLoading || leadsLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="lg" />
      </div>
    )
  }

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'call':
        return <PhoneIcon className="w-4 h-4" />
      case 'email':
        return <EnvelopeIcon className="w-4 h-4" />
      case 'meeting':
        return <CalendarDaysIcon className="w-4 h-4" />
      default:
        return <ClockIcon className="w-4 h-4" />
    }
  }

  const getActivityColor = (type: string) => {
    switch (type) {
      case 'call':
        return 'text-blue-600 bg-blue-100'
      case 'email':
        return 'text-green-600 bg-green-100'
      case 'meeting':
        return 'text-purple-600 bg-purple-100'
      default:
        return 'text-gray-600 bg-gray-100'
    }
  }

  return (
    <div className="space-y-6 animate-fade-in">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-secondary-900 dark:text-white">
            Mi Resumen
          </h1>
          <p className="text-secondary-600 dark:text-secondary-400 mt-1">
            Bienvenido, {user?.first_name} {user?.last_name}
          </p>
        </div>
        
        <div className="flex space-x-3 mt-4 sm:mt-0">
          <select
            value={selectedPeriod}
            onChange={(e) => setSelectedPeriod(e.target.value)}
            className="input"
          >
            <option value="today">Hoy</option>
            <option value="week">Esta Semana</option>
            <option value="month">Este Mes</option>
            <option value="quarter">Este Trimestre</option>
          </select>
        </div>
      </div>

      {/* Estadísticas Principales */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="card">
          <div className="card-body">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <UserIcon className="w-8 h-8 text-blue-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-secondary-600 dark:text-secondary-400">
                  Total Leads
                </p>
                <p className="text-2xl font-bold text-secondary-900 dark:text-white">
                  {stats?.totalLeads || 0}
                </p>
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-body">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <ChartBarIcon className="w-8 h-8 text-green-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-secondary-600 dark:text-secondary-400">
                  Leads Activos
                </p>
                <p className="text-2xl font-bold text-secondary-900 dark:text-white">
                  {stats?.activeLeads || 0}
                </p>
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-body">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <TrophyIcon className="w-8 h-8 text-yellow-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-secondary-600 dark:text-secondary-400">
                  Conversiones
                </p>
                <p className="text-2xl font-bold text-secondary-900 dark:text-white">
                  {stats?.convertedLeads || 0}
                </p>
              </div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-body">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <CurrencyDollarIcon className="w-8 h-8 text-purple-600" />
              </div>
              <div className="ml-4">
                <p className="text-sm font-medium text-secondary-600 dark:text-secondary-400">
                  Tasa Conversión
                </p>
                <p className="text-2xl font-bold text-secondary-900 dark:text-white">
                  {stats?.conversionRate || 0}%
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Progreso Mensual */}
      <div className="card">
        <div className="card-header">
          <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
            Progreso del Mes
          </h3>
        </div>
        <div className="card-body">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm font-medium text-secondary-600 dark:text-secondary-400">
              Objetivo: {stats?.monthlyTarget || 0} leads
            </span>
            <span className="text-sm font-medium text-secondary-900 dark:text-white">
              {stats?.monthlyProgress || 0} / {stats?.monthlyTarget || 0}
            </span>
          </div>
          <div className="w-full bg-secondary-200 rounded-full h-2.5 dark:bg-secondary-700">
            <div 
              className="bg-primary-600 h-2.5 rounded-full transition-all duration-300"
              style={{ 
                width: `${Math.min(((stats?.monthlyProgress || 0) / (stats?.monthlyTarget || 1)) * 100, 100)}%` 
              }}
            ></div>
          </div>
          <div className="flex items-center mt-2">
            {((stats?.monthlyProgress || 0) / (stats?.monthlyTarget || 1)) >= 0.8 ? (
              <ArrowTrendingUpIcon className="w-4 h-4 text-green-600 mr-1" />
            ) : (
              <ArrowTrendingDownIcon className="w-4 h-4 text-yellow-600 mr-1" />
            )}
            <span className="text-sm text-secondary-600 dark:text-secondary-400">
              {Math.round(((stats?.monthlyProgress || 0) / (stats?.monthlyTarget || 1)) * 100)}% completado
            </span>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Actividades Recientes */}
        <div className="card">
          <div className="card-header">
            <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
              Actividades Recientes
            </h3>
          </div>
          <div className="card-body">
            <div className="space-y-3">
              {activities?.map((activity) => (
                <div key={activity.id} className="flex items-center space-x-3">
                  <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${getActivityColor(activity.type)}`}>
                    {getActivityIcon(activity.type)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-secondary-900 dark:text-white">
                      {activity.description}
                    </p>
                    <p className="text-sm text-secondary-500 dark:text-secondary-400">
                      {activity.leadName} • {new Date(activity.timestamp).toLocaleString()}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Mis Leads Activos */}
        <div className="card">
          <div className="card-header">
            <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
              Mis Leads Activos
            </h3>
          </div>
          <div className="card-body">
            <div className="space-y-3">
              {myLeads?.data?.slice(0, 5).map((lead: any) => (
                <div key={lead.id} className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-secondary-900 dark:text-white">
                      {lead.first_name} {lead.last_name}
                    </p>
                    <p className="text-sm text-secondary-500 dark:text-secondary-400">
                      {lead.email} • {lead.phone}
                    </p>
                  </div>
                  <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                    lead.status === 'new' ? 'bg-blue-100 text-blue-800' :
                    lead.status === 'contacted' ? 'bg-yellow-100 text-yellow-800' :
                    lead.status === 'qualified' ? 'bg-green-100 text-green-800' :
                    'bg-gray-100 text-gray-800'
                  }`}>
                    {lead.status}
                  </span>
                </div>
              ))}
              {(!myLeads?.data || myLeads.data.length === 0) && (
                <p className="text-sm text-secondary-500 dark:text-secondary-400 text-center py-4">
                  No tienes leads asignados actualmente
                </p>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default EmployeeSummaryPage
