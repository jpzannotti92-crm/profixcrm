import React, { useState } from 'react'

interface User {
  id: number
  first_name: string
  last_name: string
}

interface Assignment {
  userId: number
  leadIds: number[]
  status: string
}

interface BulkAssignModalProps {
  selectedLeads: number[]
  users: User[]
  onClose: () => void
  onAssign: (assignments: Assignment[]) => void
}

const BulkAssignModal: React.FC<BulkAssignModalProps> = ({ 
  selectedLeads, 
  users, 
  onClose, 
  onAssign 
}) => {
  const [selectedUsers, setSelectedUsers] = useState<number[]>([])
  const [newStatus, setNewStatus] = useState<string>('')

  const handleAssign = () => {
    if (selectedUsers.length === 0) return

    // Calcular distribución automática
    const leadsPerUser = Math.floor(selectedLeads.length / selectedUsers.length)
    const remainingLeads = selectedLeads.length % selectedUsers.length

    const assignments: Assignment[] = []
    let leadIndex = 0

    selectedUsers.forEach((userId, userIndex) => {
      const leadsForThisUser = leadsPerUser + (userIndex < remainingLeads ? 1 : 0)
      const userLeads = selectedLeads.slice(leadIndex, leadIndex + leadsForThisUser)
      
      assignments.push({
        userId,
        leadIds: userLeads,
        status: newStatus || 'contacted'
      })
      
      leadIndex += leadsForThisUser
    })

    onAssign(assignments)
  }

  const handleUserToggle = (userId: number) => {
    setSelectedUsers(prev => 
      prev.includes(userId) 
        ? prev.filter(id => id !== userId)
        : [...prev, userId]
    )
  }

  const getDistribution = () => {
    if (selectedUsers.length === 0) return []
    
    const leadsPerUser = Math.floor(selectedLeads.length / selectedUsers.length)
    const remainingLeads = selectedLeads.length % selectedUsers.length
    
    return selectedUsers.map((userId, index) => ({
      userId,
      user: users.find(u => u.id === userId),
      leadsCount: leadsPerUser + (index < remainingLeads ? 1 : 0)
    }))
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-secondary-800 rounded-lg p-6 w-full max-w-2xl mx-4">
        <h3 className="text-lg font-semibold text-secondary-900 dark:text-white mb-4">
          Asignar {selectedLeads.length} Leads Masivamente
        </h3>
        
        <div className="space-y-4">
          {/* Selección de usuarios */}
          <div>
            <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
              Seleccionar Empleados
            </label>
            <div className="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto">
              {users.map((user) => (
                <label key={user.id} className="flex items-center space-x-2 p-2 rounded hover:bg-secondary-50 dark:hover:bg-secondary-700">
                  <input
                    type="checkbox"
                    checked={selectedUsers.includes(user.id)}
                    onChange={() => handleUserToggle(user.id)}
                    className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
                  />
                  <span className="text-sm text-secondary-900 dark:text-white">
                    {user.first_name} {user.last_name}
                  </span>
                </label>
              ))}
            </div>
          </div>

          {/* Nuevo estado */}
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

          {/* Vista previa de distribución */}
          {selectedUsers.length > 0 && (
            <div>
              <h4 className="text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                Distribución Automática:
              </h4>
              <div className="bg-secondary-50 dark:bg-secondary-700 rounded p-3 space-y-2">
                {getDistribution().map(({ user, leadsCount }) => (
                  <div key={user?.id} className="flex justify-between text-sm">
                    <span className="text-secondary-900 dark:text-white">
                      {user?.first_name} {user?.last_name}
                    </span>
                    <span className="text-primary-600 dark:text-primary-400 font-medium">
                      {leadsCount} leads
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        <div className="flex justify-end space-x-3 mt-6">
          <button onClick={onClose} className="btn-secondary">
            Cancelar
          </button>
          <button 
            onClick={handleAssign}
            disabled={selectedUsers.length === 0}
            className="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Asignar Leads
          </button>
        </div>
      </div>
    </div>
  )
}

export default BulkAssignModal
