import React, { useState, useMemo, useCallback } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { 
  PlusIcon, 
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  PencilIcon,
  TrashIcon,
  CurrencyDollarIcon,
  ChartBarIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon
} from '@heroicons/react/24/outline'
import { Dialog, Transition } from '@headlessui/react'
import { Fragment } from 'react'
import toast from 'react-hot-toast'

import { tradingAccountsApi } from '../../services/api'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import { cn } from '../../utils/cn'

// Types
interface TradingAccount {
  id: number
  account_number: string
  lead_id: number
  client_name: string
  client_email: string
  account_type: 'demo' | 'micro' | 'standard' | 'real' | 'vip'
  platform: string
  balance: number
  equity: number
  margin: number
  free_margin: number
  margin_level: number
  leverage: string
  currency: string
  status: 'active' | 'suspended' | 'closed'
  server: string
  password: string
  investor_password: string
  created_at: string
  last_login?: string
  trades_count: number
  profit_loss: number
  commission: number
  swap: number
}

interface TradingAccountFilters {
  search: string
  account_type: string
  status: string
  lead_id: string
}

interface TradingAccountFormData {
  lead_id: string | number
  client_name: string
  client_email: string
  account_type: 'demo' | 'micro' | 'standard' | 'real' | 'vip'
  platform: string
  leverage: string
  currency: string
  status: 'active' | 'suspended' | 'closed'
}

// Constants
const ACCOUNT_TYPE_CONFIG = {
  demo: { label: 'Demo', color: 'bg-blue-100 text-blue-800', icon: '🎯' },
  micro: { label: 'Micro', color: 'bg-green-100 text-green-800', icon: '💰' },
  standard: { label: 'Standard', color: 'bg-purple-100 text-purple-800', icon: '⭐' },
  real: { label: 'Real', color: 'bg-orange-100 text-orange-800', icon: '🔥' },
  vip: { label: 'VIP', color: 'bg-yellow-100 text-yellow-800', icon: '👑' }
} as const

const STATUS_CONFIG = {
  active: { label: 'Activa', color: 'bg-green-100 text-green-800', icon: CheckCircleIcon },
  suspended: { label: 'Suspendida', color: 'bg-yellow-100 text-yellow-800', icon: ExclamationTriangleIcon },
  closed: { label: 'Cerrada', color: 'bg-red-100 text-red-800', icon: XCircleIcon }
} as const

const LEVERAGE_OPTIONS = ['1:50', '1:100', '1:200', '1:300', '1:400', '1:500']
const CURRENCY_OPTIONS = ['USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'CHF']
const PLATFORM_OPTIONS = ['MetaTrader 4', 'MetaTrader 5', 'cTrader', 'TradingView']

// Utility Functions
const formatCurrency = (amount: number, currency: string = 'USD'): string => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount)
}

const formatDate = (dateString: string): string => {
  return new Intl.DateTimeFormat('es-ES', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(new Date(dateString))
}

const getMarginLevelColor = (level: number): string => {
  if (level >= 200) return 'text-green-600'
  if (level >= 100) return 'text-yellow-600'
  return 'text-red-600'
}

// Components
const AccountTypeBadge: React.FC<{ type: keyof typeof ACCOUNT_TYPE_CONFIG }> = ({ type }) => {
  const config = ACCOUNT_TYPE_CONFIG[type]
  return (
    <span className={cn('inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', config.color)}>
      <span className="mr-1">{config.icon}</span>
      {config.label}
    </span>
  )
}

const StatusBadge: React.FC<{ status: keyof typeof STATUS_CONFIG }> = ({ status }) => {
  const config = STATUS_CONFIG[status]
  const Icon = config.icon
  return (
    <span className={cn('inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', config.color)}>
      <Icon className="w-3 h-3 mr-1" />
      {config.label}
    </span>
  )
}

const AccountStats: React.FC<{ accounts: TradingAccount[] }> = ({ accounts }) => {
  const stats = useMemo(() => {
    const totalAccounts = accounts.length
    const activeAccounts = accounts.filter(acc => acc.status === 'active').length
    const totalBalance = accounts.reduce((sum, acc) => sum + acc.balance, 0)
    const totalEquity = accounts.reduce((sum, acc) => sum + acc.equity, 0)
    const totalProfitLoss = accounts.reduce((sum, acc) => sum + acc.profit_loss, 0)

    return {
      totalAccounts,
      activeAccounts,
      totalBalance,
      totalEquity,
      totalProfitLoss,
      activePercentage: totalAccounts > 0 ? (activeAccounts / totalAccounts) * 100 : 0
    }
  }, [accounts])

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center">
          <div className="flex-shrink-0">
            <ChartBarIcon className="h-8 w-8 text-blue-600" />
          </div>
          <div className="ml-4">
            <p className="text-sm font-medium text-gray-500">Total Cuentas</p>
            <p className="text-2xl font-semibold text-gray-900">{stats.totalAccounts}</p>
          </div>
        </div>
      </div>

      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center">
          <div className="flex-shrink-0">
            <CheckCircleIcon className="h-8 w-8 text-green-600" />
          </div>
          <div className="ml-4">
            <p className="text-sm font-medium text-gray-500">Activas</p>
            <p className="text-2xl font-semibold text-gray-900">{stats.activeAccounts}</p>
            <p className="text-xs text-gray-500">{stats.activePercentage.toFixed(1)}%</p>
          </div>
        </div>
      </div>

      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center">
          <div className="flex-shrink-0">
            <CurrencyDollarIcon className="h-8 w-8 text-purple-600" />
          </div>
          <div className="ml-4">
            <p className="text-sm font-medium text-gray-500">Balance Total</p>
            <p className="text-2xl font-semibold text-gray-900">{formatCurrency(stats.totalBalance)}</p>
          </div>
        </div>
      </div>

      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center">
          <div className="flex-shrink-0">
            <ChartBarIcon className="h-8 w-8 text-indigo-600" />
          </div>
          <div className="ml-4">
            <p className="text-sm font-medium text-gray-500">Equity Total</p>
            <p className="text-2xl font-semibold text-gray-900">{formatCurrency(stats.totalEquity)}</p>
          </div>
        </div>
      </div>

      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center">
          <div className="flex-shrink-0">
            {stats.totalProfitLoss >= 0 ? (
              <ArrowUpIcon className="h-8 w-8 text-green-600" />
            ) : (
              <ArrowDownIcon className="h-8 w-8 text-red-600" />
            )}
          </div>
          <div className="ml-4">
            <p className="text-sm font-medium text-gray-500">P&L Total</p>
            <p className={cn(
              'text-2xl font-semibold',
              stats.totalProfitLoss >= 0 ? 'text-green-600' : 'text-red-600'
            )}>
              {formatCurrency(stats.totalProfitLoss)}
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}

const AccountFilters: React.FC<{
  filters: TradingAccountFilters
  onFiltersChange: (filters: TradingAccountFilters) => void
}> = ({ filters, onFiltersChange }) => {
  const handleFilterChange = useCallback((key: keyof TradingAccountFilters, value: string) => {
    onFiltersChange({ ...filters, [key]: value })
  }, [filters, onFiltersChange])

  return (
    <div className="bg-white rounded-lg shadow p-6 mb-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-medium text-gray-900 flex items-center">
          <FunnelIcon className="h-5 w-5 mr-2" />
          Filtros
        </h3>
      </div>
      
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Buscar
          </label>
          <div className="relative">
            <MagnifyingGlassIcon className="h-5 w-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
            <input
              type="text"
              placeholder="Número de cuenta, cliente..."
              value={filters.search}
              onChange={(e) => handleFilterChange('search', e.target.value)}
              className="pl-10 w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Tipo de Cuenta
          </label>
          <select
            value={filters.account_type}
            onChange={(e) => handleFilterChange('account_type', e.target.value)}
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
          >
            <option value="">Todos los tipos</option>
            {Object.entries(ACCOUNT_TYPE_CONFIG).map(([key, config]) => (
              <option key={key} value={key}>{config.label}</option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Estado
          </label>
          <select
            value={filters.status}
            onChange={(e) => handleFilterChange('status', e.target.value)}
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
          >
            <option value="">Todos los estados</option>
            {Object.entries(STATUS_CONFIG).map(([key, config]) => (
              <option key={key} value={key}>{config.label}</option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            ID Lead
          </label>
          <input
            type="text"
            placeholder="ID del lead"
            value={filters.lead_id}
            onChange={(e) => handleFilterChange('lead_id', e.target.value)}
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
          />
        </div>
      </div>
    </div>
  )
}

const AccountTable: React.FC<{
  accounts: TradingAccount[]
  onView: (account: TradingAccount) => void
  onEdit: (account: TradingAccount) => void
  onDelete: (account: TradingAccount) => void
}> = ({ accounts, onView, onEdit, onDelete }) => {
  if (accounts.length === 0) {
    return (
      <div className="bg-white rounded-lg shadow">
        <div className="text-center py-12">
          <ChartBarIcon className="mx-auto h-12 w-12 text-gray-400" />
          <h3 className="mt-2 text-sm font-medium text-gray-900">No hay cuentas</h3>
          <p className="mt-1 text-sm text-gray-500">
            Comienza creando una nueva cuenta de trading.
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className="bg-white rounded-lg shadow overflow-hidden">
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Cuenta
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Cliente
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Tipo
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Balance
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Equity
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                P&L
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Estado
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Último Login
              </th>
              <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-gray-200">
            {accounts.map((account) => (
              <tr key={account.id} className="hover:bg-gray-50">
                <td className="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div className="text-sm font-medium text-gray-900">
                      {account.account_number}
                    </div>
                    <div className="text-sm text-gray-500">
                      {account.platform} • {account.leverage}
                    </div>
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div className="text-sm font-medium text-gray-900">
                      {account.client_name}
                    </div>
                    <div className="text-sm text-gray-500">
                      {account.client_email}
                    </div>
                  </div>
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <AccountTypeBadge type={account.account_type} />
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {formatCurrency(account.balance, account.currency)}
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {formatCurrency(account.equity, account.currency)}
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <span className={cn(
                    'text-sm font-medium',
                    account.profit_loss >= 0 ? 'text-green-600' : 'text-red-600'
                  )}>
                    {formatCurrency(account.profit_loss, account.currency)}
                  </span>
                </td>
                <td className="px-6 py-4 whitespace-nowrap">
                  <StatusBadge status={account.status} />
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {account.last_login ? (
                    <div className="flex items-center">
                      <ClockIcon className="h-4 w-4 mr-1" />
                      {formatDate(account.last_login)}
                    </div>
                  ) : (
                    'Nunca'
                  )}
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div className="flex items-center justify-end space-x-2">
                    <button
                      onClick={() => onView(account)}
                      className="text-blue-600 hover:text-blue-900 p-1 rounded-full hover:bg-blue-100"
                      title="Ver detalles"
                    >
                      <EyeIcon className="h-4 w-4" />
                    </button>
                    <button
                      onClick={() => onEdit(account)}
                      className="text-indigo-600 hover:text-indigo-900 p-1 rounded-full hover:bg-indigo-100"
                      title="Editar"
                    >
                      <PencilIcon className="h-4 w-4" />
                    </button>
                    <button
                      onClick={() => onDelete(account)}
                      className="text-red-600 hover:text-red-900 p-1 rounded-full hover:bg-red-100"
                      title="Eliminar"
                    >
                      <TrashIcon className="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

const AccountModal: React.FC<{
  isOpen: boolean
  onClose: () => void
  account?: TradingAccount | null
  onSuccess: () => void
}> = ({ isOpen, onClose, account, onSuccess }) => {
  const [formData, setFormData] = useState<TradingAccountFormData>({
    lead_id: '',
    client_name: '',
    client_email: '',
    account_type: 'demo',
    platform: 'MetaTrader 4',
    leverage: '1:100',
    currency: 'USD',
    status: 'active'
  })

  const [errors, setErrors] = useState<Record<string, string>>({})
  const queryClient = useQueryClient()

  // Reset form when modal opens/closes or account changes
  React.useEffect(() => {
    if (isOpen) {
      if (account) {
        setFormData({
          lead_id: account.lead_id,
          client_name: account.client_name,
          client_email: account.client_email,
          account_type: account.account_type,
          platform: account.platform,
          leverage: account.leverage,
          currency: account.currency,
          status: account.status
        })
      } else {
        setFormData({
          lead_id: '',
          client_name: '',
          client_email: '',
          account_type: 'demo',
          platform: 'MetaTrader 4',
          leverage: '1:100',
          currency: 'USD',
          status: 'active'
        })
      }
      setErrors({})
    }
  }, [isOpen, account])

  const createMutation = useMutation({
    mutationFn: tradingAccountsApi.createTradingAccount,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['trading-accounts'] })
      toast.success('Cuenta creada exitosamente')
      onSuccess()
      onClose()
    },
    onError: (error: any) => {
      toast.error(error.message || 'Error al crear la cuenta')
      if (error.errors) {
        setErrors(error.errors)
      }
    }
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: TradingAccountFormData }) =>
      tradingAccountsApi.updateTradingAccount(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['trading-accounts'] })
      toast.success('Cuenta actualizada exitosamente')
      onSuccess()
      onClose()
    },
    onError: (error: any) => {
      toast.error(error.message || 'Error al actualizar la cuenta')
      if (error.errors) {
        setErrors(error.errors)
      }
    }
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setErrors({})

    if (account) {
      updateMutation.mutate({ id: account.id, data: formData })
    } else {
      createMutation.mutate(formData)
    }
  }

  const handleChange = (field: keyof TradingAccountFormData, value: string | number) => {
    setFormData(prev => ({ ...prev, [field]: value }))
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }))
    }
  }

  const isLoading = createMutation.isPending || updateMutation.isPending

  return (
    <Transition appear show={isOpen} as={Fragment}>
      <Dialog as="div" className="relative z-50" onClose={onClose}>
        <Transition.Child
          as={Fragment}
          enter="ease-out duration-300"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in duration-200"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div className="fixed inset-0 bg-black bg-opacity-25" />
        </Transition.Child>

        <div className="fixed inset-0 overflow-y-auto">
          <div className="flex min-h-full items-center justify-center p-4 text-center">
            <Transition.Child
              as={Fragment}
              enter="ease-out duration-300"
              enterFrom="opacity-0 scale-95"
              enterTo="opacity-100 scale-100"
              leave="ease-in duration-200"
              leaveFrom="opacity-100 scale-100"
              leaveTo="opacity-0 scale-95"
            >
              <Dialog.Panel className="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
                <Dialog.Title as="h3" className="text-lg font-medium leading-6 text-gray-900 mb-4">
                  {account ? 'Editar Cuenta de Trading' : 'Nueva Cuenta de Trading'}
                </Dialog.Title>

                <form onSubmit={handleSubmit} className="space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        ID Lead *
                      </label>
                      <input
                        type="number"
                        value={formData.lead_id}
                        onChange={(e) => handleChange('lead_id', e.target.value)}
                        className={cn(
                          'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500',
                          errors.lead_id && 'border-red-300 focus:border-red-500 focus:ring-red-500'
                        )}
                        required
                      />
                      {errors.lead_id && (
                        <p className="mt-1 text-sm text-red-600">{errors.lead_id}</p>
                      )}
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Tipo de Cuenta *
                      </label>
                      <select
                        value={formData.account_type}
                        onChange={(e) => handleChange('account_type', e.target.value)}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        required
                      >
                        {Object.entries(ACCOUNT_TYPE_CONFIG).map(([key, config]) => (
                          <option key={key} value={key}>{config.label}</option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Nombre del Cliente *
                      </label>
                      <input
                        type="text"
                        value={formData.client_name}
                        onChange={(e) => handleChange('client_name', e.target.value)}
                        className={cn(
                          'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500',
                          errors.client_name && 'border-red-300 focus:border-red-500 focus:ring-red-500'
                        )}
                        required
                      />
                      {errors.client_name && (
                        <p className="mt-1 text-sm text-red-600">{errors.client_name}</p>
                      )}
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Email del Cliente *
                      </label>
                      <input
                        type="email"
                        value={formData.client_email}
                        onChange={(e) => handleChange('client_email', e.target.value)}
                        className={cn(
                          'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500',
                          errors.client_email && 'border-red-300 focus:border-red-500 focus:ring-red-500'
                        )}
                        required
                      />
                      {errors.client_email && (
                        <p className="mt-1 text-sm text-red-600">{errors.client_email}</p>
                      )}
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Plataforma *
                      </label>
                      <select
                        value={formData.platform}
                        onChange={(e) => handleChange('platform', e.target.value)}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        required
                      >
                        {PLATFORM_OPTIONS.map(platform => (
                          <option key={platform} value={platform}>{platform}</option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Apalancamiento *
                      </label>
                      <select
                        value={formData.leverage}
                        onChange={(e) => handleChange('leverage', e.target.value)}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        required
                      >
                        {LEVERAGE_OPTIONS.map(leverage => (
                          <option key={leverage} value={leverage}>{leverage}</option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Moneda *
                      </label>
                      <select
                        value={formData.currency}
                        onChange={(e) => handleChange('currency', e.target.value)}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        required
                      >
                        {CURRENCY_OPTIONS.map(currency => (
                          <option key={currency} value={currency}>{currency}</option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Estado *
                      </label>
                      <select
                        value={formData.status}
                        onChange={(e) => handleChange('status', e.target.value)}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        required
                      >
                        {Object.entries(STATUS_CONFIG).map(([key, config]) => (
                          <option key={key} value={key}>{config.label}</option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="flex justify-end space-x-3 pt-4">
                    <button
                      type="button"
                      onClick={onClose}
                      className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                      disabled={isLoading}
                    >
                      Cancelar
                    </button>
                    <button
                      type="submit"
                      className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                      disabled={isLoading}
                    >
                      {isLoading ? (
                        <div className="flex items-center">
                          <LoadingSpinner size="sm" className="mr-2" />
                          {account ? 'Actualizando...' : 'Creando...'}
                        </div>
                      ) : (
                        account ? 'Actualizar' : 'Crear'
                      )}
                    </button>
                  </div>
                </form>
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition>
  )
}

const AccountDetailModal: React.FC<{
  isOpen: boolean
  account: TradingAccount | null
  onClose: () => void
}> = ({ isOpen, account, onClose }) => {
  if (!account) return null

  return (
    <Transition appear show={isOpen} as={Fragment}>
      <Dialog as="div" className="relative z-50" onClose={onClose}>
        <Transition.Child
          as={Fragment}
          enter="ease-out duration-300"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in duration-200"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div className="fixed inset-0 bg-black bg-opacity-25" />
        </Transition.Child>

        <div className="fixed inset-0 overflow-y-auto">
          <div className="flex min-h-full items-center justify-center p-4 text-center">
            <Transition.Child
              as={Fragment}
              enter="ease-out duration-300"
              enterFrom="opacity-0 scale-95"
              enterTo="opacity-100 scale-100"
              leave="ease-in duration-200"
              leaveFrom="opacity-100 scale-100"
              leaveTo="opacity-0 scale-95"
            >
              <Dialog.Panel className="w-full max-w-4xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
                <Dialog.Title as="h3" className="text-lg font-medium leading-6 text-gray-900 mb-6">
                  Detalles de la Cuenta: {account.account_number}
                </Dialog.Title>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                  {/* Información General */}
                  <div className="bg-gray-50 rounded-lg p-4">
                    <h4 className="text-md font-medium text-gray-900 mb-4">Información General</h4>
                    <div className="space-y-3">
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Número de Cuenta:</span>
                        <span className="text-sm font-medium text-gray-900">{account.account_number}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Tipo:</span>
                        <AccountTypeBadge type={account.account_type} />
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Estado:</span>
                        <StatusBadge status={account.status} />
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Plataforma:</span>
                        <span className="text-sm font-medium text-gray-900">{account.platform}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Servidor:</span>
                        <span className="text-sm font-medium text-gray-900">{account.server}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Apalancamiento:</span>
                        <span className="text-sm font-medium text-gray-900">{account.leverage}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Moneda:</span>
                        <span className="text-sm font-medium text-gray-900">{account.currency}</span>
                      </div>
                    </div>
                  </div>

                  {/* Información del Cliente */}
                  <div className="bg-gray-50 rounded-lg p-4">
                    <h4 className="text-md font-medium text-gray-900 mb-4">Información del Cliente</h4>
                    <div className="space-y-3">
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Nombre:</span>
                        <span className="text-sm font-medium text-gray-900">{account.client_name}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Email:</span>
                        <span className="text-sm font-medium text-gray-900">{account.client_email}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">ID Lead:</span>
                        <span className="text-sm font-medium text-gray-900">{account.lead_id}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Fecha de Creación:</span>
                        <span className="text-sm font-medium text-gray-900">{formatDate(account.created_at)}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Último Login:</span>
                        <span className="text-sm font-medium text-gray-900">
                          {account.last_login ? formatDate(account.last_login) : 'Nunca'}
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Información Financiera */}
                  <div className="bg-gray-50 rounded-lg p-4">
                    <h4 className="text-md font-medium text-gray-900 mb-4">Información Financiera</h4>
                    <div className="space-y-3">
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Balance:</span>
                        <span className="text-sm font-medium text-gray-900">
                          {formatCurrency(account.balance, account.currency)}
                        </span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Equity:</span>
                        <span className="text-sm font-medium text-gray-900">
                          {formatCurrency(account.equity, account.currency)}
                        </span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Margen:</span>
                        <span className="text-sm font-medium text-gray-900">
                          {formatCurrency(account.margin, account.currency)}
                        </span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Margen Libre:</span>
                        <span className="text-sm font-medium text-gray-900">
                          {formatCurrency(account.free_margin, account.currency)}
                        </span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Nivel de Margen:</span>
                        <span className={cn('text-sm font-medium', getMarginLevelColor(account.margin_level))}>
                          {account.margin_level.toFixed(2)}%
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Estadísticas de Trading */}
                  <div className="bg-gray-50 rounded-lg p-4">
                    <h4 className="text-md font-medium text-gray-900 mb-4">Estadísticas de Trading</h4>
                    <div className="space-y-3">
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Número de Trades:</span>
                        <span className="text-sm font-medium text-gray-900">{account.trades_count}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">P&L Total:</span>
                        <span className={cn(
                          'text-sm font-medium',
                          account.profit_loss >= 0 ? 'text-green-600' : 'text-red-600'
                        )}>
                          {formatCurrency(account.profit_loss, account.currency)}
                        </span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Comisiones:</span>
                        <span className="text-sm font-medium text-gray-900">
                          {formatCurrency(account.commission, account.currency)}
                        </span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">Swap:</span>
                        <span className="text-sm font-medium text-gray-900">
                          {formatCurrency(account.swap, account.currency)}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="flex justify-end pt-6">
                  <button
                    type="button"
                    onClick={onClose}
                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                  >
                    Cerrar
                  </button>
                </div>
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition>
  )
}

// Main Component
export default function TradingAccountsPage() {
  const [filters, setFilters] = useState<TradingAccountFilters>({
    search: '',
    account_type: '',
    status: '',
    lead_id: '',
  })
  const [page, setPage] = useState(1)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [showEditModal, setShowEditModal] = useState(false)
  const [showDetailModal, setShowDetailModal] = useState(false)
  const [selectedAccount, setSelectedAccount] = useState<TradingAccount | null>(null)
  const limit = 20

  const queryClient = useQueryClient()

  // Queries
  const { data, isLoading, error } = useQuery({
    queryKey: ['trading-accounts', filters, page],
    queryFn: () => tradingAccountsApi.getTradingAccounts({ ...filters, page, limit }),
    placeholderData: (previousData) => previousData,
  })

  // Mutations
  const deleteMutation = useMutation({
    mutationFn: tradingAccountsApi.deleteTradingAccount,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['trading-accounts'] })
      toast.success('Cuenta eliminada exitosamente')
    },
    onError: (error: any) => {
      toast.error(error.message || 'Error al eliminar la cuenta')
    }
  })

  // Event Handlers
  const handleView = useCallback((account: TradingAccount) => {
    setSelectedAccount(account)
    setShowDetailModal(true)
  }, [])

  const handleEdit = useCallback((account: TradingAccount) => {
    setSelectedAccount(account)
    setShowEditModal(true)
  }, [])

  const handleDelete = useCallback((account: TradingAccount) => {
    if (window.confirm(`¿Estás seguro de que quieres eliminar la cuenta ${account.account_number}?`)) {
      deleteMutation.mutate(account.id)
    }
  }, [deleteMutation])

  const handleFiltersChange = useCallback((newFilters: TradingAccountFilters) => {
    setFilters(newFilters)
    setPage(1)
  }, [])

  const handleModalSuccess = useCallback(() => {
    setSelectedAccount(null)
  }, [])

  // Memoized values
  const accounts = useMemo(() => data?.data || [], [data])
  const totalPages = useMemo(() => Math.ceil((data?.total || 0) / limit), [data?.total, limit])

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <ExclamationTriangleIcon className="mx-auto h-12 w-12 text-red-500" />
          <h3 className="mt-2 text-sm font-medium text-gray-900">Error al cargar las cuentas</h3>
          <p className="mt-1 text-sm text-gray-500">
            {error instanceof Error ? error.message : 'Ha ocurrido un error inesperado'}
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-3xl font-bold text-gray-900">Cuentas de Trading</h1>
              <p className="mt-2 text-gray-600">
                Gestiona las cuentas de trading de tus clientes
              </p>
            </div>
            <button
              onClick={() => setShowCreateModal(true)}
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              <PlusIcon className="h-5 w-5 mr-2" />
              Nueva Cuenta
            </button>
          </div>
        </div>

        {/* Stats */}
        {!isLoading && <AccountStats accounts={accounts} />}

        {/* Filters */}
        <AccountFilters filters={filters} onFiltersChange={handleFiltersChange} />

        {/* Content */}
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <LoadingSpinner size="lg" />
          </div>
        ) : (
          <>
            <AccountTable
              accounts={accounts}
              onView={handleView}
              onEdit={handleEdit}
              onDelete={handleDelete}
            />

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="mt-6 flex items-center justify-between">
                <div className="text-sm text-gray-700">
                  Mostrando {((page - 1) * limit) + 1} a {Math.min(page * limit, data?.total || 0)} de {data?.total || 0} resultados
                </div>
                <div className="flex items-center space-x-2">
                  <button
                    onClick={() => setPage(page - 1)}
                    disabled={page <= 1}
                    className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Anterior
                  </button>
                  <span className="px-3 py-2 text-sm font-medium text-gray-700">
                    Página {page} de {totalPages}
                  </span>
                  <button
                    onClick={() => setPage(page + 1)}
                    disabled={page >= totalPages}
                    className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Siguiente
                  </button>
                </div>
              </div>
            )}
          </>
        )}

        {/* Modals */}
        <AccountModal
          isOpen={showCreateModal}
          onClose={() => setShowCreateModal(false)}
          onSuccess={handleModalSuccess}
        />

        <AccountModal
          isOpen={showEditModal}
          onClose={() => setShowEditModal(false)}
          account={selectedAccount}
          onSuccess={handleModalSuccess}
        />

        <AccountDetailModal
          isOpen={showDetailModal}
          account={selectedAccount}
          onClose={() => setShowDetailModal(false)}
        />
      </div>
    </div>
  )
}
