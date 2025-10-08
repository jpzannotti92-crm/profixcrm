import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/react/24/outline'
import { cn } from '../../utils/cn'

interface StatsCardProps {
  title: string
  value: number
  change: number
  icon: React.ComponentType<{ className?: string }>
  color: 'primary' | 'success' | 'warning' | 'danger' | 'secondary'
  format: 'number' | 'currency' | 'percentage'
  className?: string
  style?: React.CSSProperties
}

const colorClasses = {
  primary: 'from-primary-500 to-primary-600',
  success: 'from-success-500 to-success-600',
  warning: 'from-warning-500 to-warning-600',
  danger: 'from-danger-500 to-danger-600',
  secondary: 'from-secondary-500 to-secondary-600',
}

export default function StatsCard({
  title,
  value,
  change,
  icon: Icon,
  color,
  format,
  className,
  style,
}: StatsCardProps) {
  const formatValue = (val: number) => {
    switch (format) {
      case 'currency':
        return new Intl.NumberFormat('es-ES', {
          style: 'currency',
          currency: 'USD',
          minimumFractionDigits: 0,
          maximumFractionDigits: 0,
        }).format(val)
      case 'percentage':
        return `${val.toFixed(1)}%`
      default:
        return new Intl.NumberFormat('es-ES').format(val)
    }
  }

  const isPositive = change >= 0

  return (
    <div
      className={cn(
        'card hover:shadow-medium transition-all duration-300 group cursor-pointer',
        className
      )}
      style={style}
    >
      <div className="card-body">
        <div className="flex items-center justify-between">
          <div className="flex-1">
            <p className="text-sm font-medium text-secondary-600 dark:text-secondary-400 mb-1">
              {title}
            </p>
            <p className="text-2xl font-bold text-secondary-900 dark:text-white mb-2">
              {formatValue(value)}
            </p>
            
            {/* Indicador de cambio */}
            <div className="flex items-center space-x-1">
              <div
                className={cn(
                  'flex items-center space-x-1 px-2 py-1 rounded-full text-xs font-medium',
                  isPositive
                    ? 'bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-200'
                    : 'bg-danger-100 text-danger-700 dark:bg-danger-900 dark:text-danger-200'
                )}
              >
                {isPositive ? (
                  <ArrowUpIcon className="w-3 h-3" />
                ) : (
                  <ArrowDownIcon className="w-3 h-3" />
                )}
                <span>{Math.abs(change).toFixed(1)}%</span>
              </div>
              <span className="text-xs text-secondary-500 dark:text-secondary-400">
                vs mes anterior
              </span>
            </div>
          </div>

          {/* Icono con gradiente */}
          <div
            className={cn(
              'w-12 h-12 rounded-xl bg-gradient-to-br flex items-center justify-center group-hover:scale-110 transition-transform duration-300',
              colorClasses[color]
            )}
          >
            <Icon className="w-6 h-6 text-white" />
          </div>
        </div>

        {/* Barra de progreso */}
        <div className="mt-4">
          <div className="w-full bg-secondary-200 dark:bg-secondary-700 rounded-full h-1.5">
            <div
              className={cn(
                'h-1.5 rounded-full bg-gradient-to-r transition-all duration-1000 ease-out',
                colorClasses[color]
              )}
              style={{
                width: `${Math.min(Math.max((value / (value + Math.abs(value * 0.3))) * 100, 10), 100)}%`,
              }}
            />
          </div>
        </div>
      </div>
    </div>
  )
}
