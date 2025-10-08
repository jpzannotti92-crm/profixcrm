import { useQuery } from '@tanstack/react-query'
import { 
  UsersIcon, 
  HandRaisedIcon, 
  ChartBarIcon,
  BuildingOfficeIcon,
  UserGroupIcon
} from '@heroicons/react/24/outline'

import { dashboardApi } from '../../services/api'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import StatsCard from '../../components/dashboard/StatsCard'
import ChartCard from '../../components/dashboard/ChartCard'
import { User } from '../../types'

// Dashboard specific types
interface DashboardStats {
  total_leads: number
  total_conversions: number
  conversion_rate: number
  total_users: number
  total_desks: number
  leads_by_status: Array<{
    status: string
    count: number
  }>
  leads_by_desk: Array<{
    desk_name: string
    leads_count: number
  }>
  top_users: Array<User & {
    leads_count: number
  }>
}

export default function DashboardPage() {
  const { data: stats, isLoading, error } = useQuery<DashboardStats>({
    queryKey: ['dashboard-stats'],
    queryFn: dashboardApi.getStats,
    refetchInterval: 30000, // Refrescar cada 30 segundos
  })

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="lg" />
      </div>
    )
  }

  if (error) {
    return (
      <div className="text-center py-12">
        <div className="text-danger-600 dark:text-danger-400 mb-4">
          <ChartBarIcon className="w-12 h-12 mx-auto mb-4" />
          <h3 className="text-lg font-medium">Error al cargar estadísticas</h3>
          <p className="text-sm text-secondary-500 dark:text-secondary-400 mt-2">
            No se pudieron cargar los datos del dashboard
          </p>
        </div>
      </div>
    )
  }

  const statsCards = [
    {
      title: 'Total Leads',
      value: stats?.total_leads || 0,
      change: 12.5, // Calculado dinámicamente en el futuro
      icon: UsersIcon,
      color: 'primary' as const,
      format: 'number' as const,
    },
    {
      title: 'Conversiones',
      value: stats?.total_conversions || 0,
      change: 8.2,
      icon: HandRaisedIcon,
      color: 'success' as const,
      format: 'number' as const,
    },
    {
      title: 'Tasa Conversión',
      value: stats?.conversion_rate || 0,
      change: -2.1,
      icon: ChartBarIcon,
      color: 'warning' as const,
      format: 'percentage' as const,
    },
    {
      title: 'Usuarios Activos',
      value: stats?.total_users || 0,
      change: 3.4,
      icon: UserGroupIcon,
      color: 'primary' as const,
      format: 'number' as const,
    },
    {
      title: 'Mesas Activas',
      value: stats?.total_desks || 0,
      change: 5.7,
      icon: BuildingOfficeIcon,
      color: 'secondary' as const,
      format: 'number' as const,
    },
  ]

  return (
    <div className="space-y-8 animate-fade-in">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-secondary-900 dark:text-white">
          Dashboard
        </h1>
        <p className="text-secondary-600 dark:text-secondary-400 mt-1">
          Resumen general de tu CRM de trading
        </p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {statsCards.map((stat, index) => (
          <StatsCard
            key={stat.title}
            {...stat}
            className="animate-slide-up"
            style={{ animationDelay: `${index * 100}ms` }}
          />
        ))}
      </div>

      {/* Charts Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <ChartCard
          title="Leads por Estado"
          subtitle="Distribución de leads por estado actual"
          type="doughnut"
          data={{
            labels: stats?.leads_by_status?.map(item => item.status) || ['Sin datos'],
            datasets: [
              {
                data: stats?.leads_by_status?.map(item => item.count) || [0],
                backgroundColor: [
                  '#3b82f6', // new
                  '#10b981', // contacted
                  '#f59e0b', // qualified
                  '#ef4444', // converted
                  '#6b7280', // lost
                ],
              },
            ],
          }}
        />

        <ChartCard
          title="Leads por Mesa"
          subtitle="Distribución de leads por mesa de trabajo"
          type="bar"
          data={{
            labels: stats?.leads_by_desk?.map(item => item.desk_name) || ['Sin datos'],
            datasets: [
              {
                label: 'Leads',
                data: stats?.leads_by_desk?.map(item => item.leads_count) || [0],
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: '#3b82f6',
                borderWidth: 1,
              },
            ],
          }}
        />
      </div>

      {/* Recent Activity */}
      <div className="card">
        <div className="card-header">
          <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
            Top Usuarios por Leads
          </h3>
        </div>
        <div className="card-body">
          <div className="space-y-4">
            {stats?.top_users && stats.top_users.length > 0 ? (
              stats.top_users.map((user: User & { leads_count: number }, index: number) => (
                <div key={index} className="flex items-center justify-between p-3 rounded-lg hover:bg-secondary-50 dark:hover:bg-secondary-800 transition-colors">
                  <div className="flex items-center space-x-3">
                    <div className="w-8 h-8 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center">
                      <span className="text-sm font-medium text-primary-600 dark:text-primary-400">
                        {user.first_name?.charAt(0)}{user.last_name?.charAt(0)}
                      </span>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-secondary-900 dark:text-white">
                        {user.first_name} {user.last_name}
                      </p>
                      <p className="text-xs text-secondary-500 dark:text-secondary-400">
                        @{user.username}
                      </p>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className="text-sm font-semibold text-secondary-900 dark:text-white">
                      {user.leads_count} leads
                    </p>
                  </div>
                </div>
              ))
            ) : (
              <div className="text-center py-8">
                <UsersIcon className="w-12 h-12 mx-auto text-secondary-400 mb-4" />
                <p className="text-secondary-500 dark:text-secondary-400">
                  No hay datos de usuarios disponibles
                </p>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
