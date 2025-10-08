import React, { useState } from 'react'

interface User {
  id: number
  first_name: string
  last_name: string
}

interface AssignLeadModalProps {
  leadId: number
  users: User[]
  onClose: () => void
  onAssign: (payload: { userId: number; status: string }) => void
}

const AssignLeadModal: React.FC<AssignLeadModalProps> = ({
  leadId,
  users,
  onClose,
  onAssign
}) => {
  const [selectedUser, setSelectedUser] = useState<number | null>(null)
  const [newStatus, setNewStatus] = useState<string>('')

  const handleAssign = () => {
    if (!selectedUser) return
    onAssign({ userId: selectedUser, status: newStatus || 'contacted' })
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-secondary-800 rounded-lg p-6 w-full max-w-md mx-4">
        <h3 className="text-lg font-semibold text-secondary-900 dark:text-white mb-4">
          Asignar Lead #{leadId}
        </h3>

        <div className="space-y-4">
          {/* Selección de un solo usuario */}
          <div>
            <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
              Seleccionar Empleado
            </label>
            <div className="grid grid-cols-1 gap-2 max-h-40 overflow-y-auto">
              {users.map((user) => (
                <label key={user.id} className="flex items-center space-x-2 p-2 rounded hover:bg-secondary-50 dark:hover:bg-secondary-700">
                  <input
                    type="radio"
                    name="assign-user"
                    checked={selectedUser === user.id}
                    onChange={() => setSelectedUser(user.id)}
                    className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
                  />
                  <span className="text-sm text-secondary-900 dark:text-white">
                    {user.first_name} {user.last_name}
                  </span>
                </label>
              ))}
            </div>
          </div>

          {/* Nuevo estado opcional */}
          <div>
            <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
              Nuevo Estado (Opcional)
            </label>
            <select
              value={newStatus}
              onChange={(e) => setNewStatus(e.target.value)}
              className="input"
            >
              <option value="">Mantener estado actual</option>
              <option value="contacted">Contactado</option>
              <option value="qualified">Calificado</option>
              <option value="converted">Convertido</option>
            </select>
          </div>
        </div>

        <div className="flex justify-end space-x-3 mt-6">
          <button onClick={onClose} className="btn-secondary">
            Cancelar
          </button>
          <button
            onClick={handleAssign}
            disabled={!selectedUser}
            className="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Asignar Lead
          </button>
        </div>
      </div>
    </div>
  )
}

export default AssignLeadModal