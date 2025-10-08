import { useEffect, useRef } from 'react'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'
import { Line, Bar, Doughnut } from 'react-chartjs-2'
import { useTheme } from '../../contexts/ThemeContext'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

interface ChartCardProps {
  title: string
  subtitle?: string
  type: 'line' | 'bar' | 'doughnut'
  data: any
  height?: number
}

export default function ChartCard({
  title,
  subtitle,
  type,
  data,
  height = 300,
}: ChartCardProps) {
  const { theme } = useTheme()
  const chartRef = useRef<any>(null)

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom' as const,
        labels: {
          color: theme === 'dark' ? '#cbd5e1' : '#475569',
          usePointStyle: true,
          padding: 20,
        },
      },
      tooltip: {
        backgroundColor: theme === 'dark' ? '#1e293b' : '#ffffff',
        titleColor: theme === 'dark' ? '#f8fafc' : '#0f172a',
        bodyColor: theme === 'dark' ? '#cbd5e1' : '#475569',
        borderColor: theme === 'dark' ? '#475569' : '#e2e8f0',
        borderWidth: 1,
        cornerRadius: 8,
        displayColors: true,
      },
    },
    scales: type !== 'doughnut' ? {
      x: {
        grid: {
          color: theme === 'dark' ? '#334155' : '#f1f5f9',
          borderColor: theme === 'dark' ? '#475569' : '#e2e8f0',
        },
        ticks: {
          color: theme === 'dark' ? '#94a3b8' : '#64748b',
        },
      },
      y: {
        grid: {
          color: theme === 'dark' ? '#334155' : '#f1f5f9',
          borderColor: theme === 'dark' ? '#475569' : '#e2e8f0',
        },
        ticks: {
          color: theme === 'dark' ? '#94a3b8' : '#64748b',
        },
      },
    } : undefined,
  }

  // Actualizar colores del gráfico cuando cambie el tema
  useEffect(() => {
    if (chartRef.current) {
      chartRef.current.update()
    }
  }, [theme])

  const renderChart = () => {
    const commonProps = {
      ref: chartRef,
      data,
      options: chartOptions,
      height,
    }

    switch (type) {
      case 'line':
        return <Line {...commonProps} />
      case 'bar':
        return <Bar {...commonProps} />
      case 'doughnut':
        return <Doughnut {...commonProps} />
      default:
        return <Line {...commonProps} />
    }
  }

  return (
    <div className="card">
      <div className="card-header">
        <div>
          <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
            {title}
          </h3>
          {subtitle && (
            <p className="text-sm text-secondary-600 dark:text-secondary-400 mt-1">
              {subtitle}
            </p>
          )}
        </div>
      </div>
      <div className="card-body">
        <div style={{ height: `${height}px` }}>
          {renderChart()}
        </div>
      </div>
    </div>
  )
}
