import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { XMarkIcon } from '@heroicons/react/24/outline'
import { usersApi } from '../../services/api'
import toast from 'react-hot-toast'
import type { User } from '../../types'

interface Role {
  id: number
  name: string
  display_name: string
  status: 'active' | 'inactive'
}

interface AssignRoleModalProps {
  isOpen: boolean
  onClose: () => void
  user: User
  roles: Role[]
  onSuccess: () => void
}

export default function AssignRoleModal({ isOpen, onClose, user, roles, onSuccess }: AssignRoleModalProps) {
  const [selectedRoleId, setSelectedRoleId] = useState<string>('')

  const assignRoleMutation = useMutation({
    mutationFn: async (data: { user_id: number; role_id: number }) => {
      const response = await usersApi.assignRole(data.user_id, data.role_id)
      return response
    },
    onSuccess: () => {
      toast.success('Rol asignado correctamente')
      onSuccess()
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Error al asignar rol')
    }
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedRoleId) {
      toast.error('Por favor selecciona un rol')
      return
    }

    assignRoleMutation.mutate({
      user_id: user.id,
      role_id: parseInt(selectedRoleId)
    })
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-secondary-800 rounded-lg p-6 w-full max-w-md mx-4">
        <div className="flex items-center justify-between mb-6">
          <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
            Asignar Rol
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
            Asignar rol a: <span className="font-medium text-secondary-900 dark:text-white">
              {user.first_name} {user.last_name}
            </span>
          </p>
          {user.role && (
            <p className="text-xs text-secondary-500 dark:text-secondary-400 mt-1">
              Rol actual: <span className="font-medium">{user.role}</span>
            </p>
          )}
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
              Nuevo Rol
            </label>
            <select
              value={selectedRoleId}
              onChange={(e) => setSelectedRoleId(e.target.value)}
              className="input"
              required
            >
              <option value="">Seleccionar rol...</option>
              {roles.map((role) => (
                <option key={role.id} value={role.id}>
                  {role.display_name}
                </option>
              ))}
            </select>
          </div>

          <div className="flex justify-end space-x-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="btn-ghost"
              disabled={assignRoleMutation.isPending}
            >
              Cancelar
            </button>
            <button
              type="submit"
              className="btn-primary"
              disabled={assignRoleMutation.isPending}
            >
              {assignRoleMutation.isPending ? 'Asignando...' : 'Asignar Rol'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
