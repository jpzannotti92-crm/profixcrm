import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { XMarkIcon } from '@heroicons/react/24/outline'
import { usersApi } from '../../services/api'
import toast from 'react-hot-toast'
import type { User } from '../../types'

interface Desk {
  id: number
  name: string
  description?: string
  status: 'active' | 'inactive'
}

interface AssignDeskModalProps {
  isOpen: boolean
  onClose: () => void
  user: User
  desks: Desk[]
  onSuccess: () => void
}

export default function AssignDeskModal({ isOpen, onClose, user, desks, onSuccess }: AssignDeskModalProps) {
  const [selectedDeskId, setSelectedDeskId] = useState<string>('')
  const [isPrimary, setIsPrimary] = useState(true)

  const assignDeskMutation = useMutation({
    mutationFn: async (data: { user_id: number; desk_id: number; is_primary: boolean }) => {
      const response = await usersApi.assignDesk(data.user_id, data.desk_id, data.is_primary)
      return response
    },
    onSuccess: () => {
      toast.success('Mesa asignada correctamente')
      onSuccess()
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Error al asignar mesa')
    }
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedDeskId) {
      toast.error('Por favor selecciona una mesa')
      return
    }

    assignDeskMutation.mutate({
      user_id: user.id,
      desk_id: parseInt(selectedDeskId),
      is_primary: isPrimary
    })
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-secondary-800 rounded-lg p-6 w-full max-w-md mx-4">
        <div className="flex items-center justify-between mb-6">
          <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
            Asignar Mesa
          </h3>
          <button
            onClick={onClose}
            className="text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-300"
          >
            <XMarkIcon className="w-6 h-6" />
          </button>
        </div>

        <div className="mb-4">
          <p className="text-sm text-secondary-600 dark:text-secondary-400">
            Asignar mesa a: <span className="font-medium text-secondary-900 dark:text-white">
              {user.first_name} {user.last_name}
            </span>
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
              Mesa
            </label>
            <select
              value={selectedDeskId}
              onChange={(e) => setSelectedDeskId(e.target.value)}
              className="input"
              required
            >
              <option value="">Seleccionar mesa...</option>
              {desks.map((desk) => (
                <option key={desk.id} value={desk.id}>
                  {desk.name}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={isPrimary}
                onChange={(e) => setIsPrimary(e.target.checked)}
                className="form-checkbox h-4 w-4 text-primary-600"
              />
              <span className="ml-2 text-sm text-secondary-700 dark:text-secondary-300">
                Mesa principal
              </span>
            </label>
            <p className="text-xs text-secondary-500 dark:text-secondary-400 mt-1">
              La mesa principal será la asignación por defecto para este usuario
            </p>
          </div>

          <div className="flex justify-end space-x-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="btn-ghost"
              disabled={assignDeskMutation.isPending}
            >
              Cancelar
            </button>
            <button
              type="submit"
              className="btn-primary"
              disabled={assignDeskMutation.isPending}
            >
              {assignDeskMutation.isPending ? 'Asignando...' : 'Asignar Mesa'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
