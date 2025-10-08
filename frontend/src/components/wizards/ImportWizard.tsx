import { useState, useRef } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { 
  XMarkIcon,
  DocumentArrowUpIcon,
  TableCellsIcon,
  CheckIcon,
  ArrowRightIcon,
  ArrowLeftIcon,
  ExclamationTriangleIcon,
  DocumentTextIcon
} from '@heroicons/react/24/outline'
import { leadsApi, desksApi, usersApi, leadImportApi } from '../../services/api'
import toast from 'react-hot-toast'
import * as XLSX from 'xlsx'

interface ImportWizardProps {
  isOpen: boolean
  onClose: () => void
  onSuccess: () => void
}

interface ImportSettings {
  desk_id?: number
  assigned_to?: number
  source: string
  status: string
  skipDuplicates: boolean
}

const STEPS = [
  { id: 1, title: 'Subir Archivo', icon: DocumentArrowUpIcon },
  { id: 2, title: 'Mapear Columnas', icon: TableCellsIcon },
  { id: 3, title: 'Configuración', icon: DocumentTextIcon },
  { id: 4, title: 'Importar', icon: CheckIcon }
]

const REQUIRED_FIELDS = [
  { key: 'first_name', label: 'Nombre', required: true },
  { key: 'last_name', label: 'Apellido', required: true },
  { key: 'email', label: 'Email', required: true },
  { key: 'phone', label: 'Teléfono', required: true }
]

const OPTIONAL_FIELDS = [
  { key: 'company', label: 'Empresa', required: false },
  { key: 'position', label: 'Posición', required: false },
  { key: 'country', label: 'País', required: false },
  { key: 'city', label: 'Ciudad', required: false },
  { key: 'budget', label: 'Presupuesto', required: false },
  { key: 'notes', label: 'Notas', required: false }
]

export default function ImportWizard({ isOpen, onClose, onSuccess }: ImportWizardProps) {
  const [currentStep, setCurrentStep] = useState(1)
  const [file, setFile] = useState<File | null>(null)
  const [csvData, setCsvData] = useState<any[]>([])
  const [headers, setHeaders] = useState<string[]>([])
  const [columnMapping, setColumnMapping] = useState<{[key: string]: string}>({})
  const [importSettings, setImportSettings] = useState<ImportSettings>({
    source: 'Import',
    status: 'new',
    skipDuplicates: false
  })
  const [previewData, setPreviewData] = useState<any[]>([])
  const fileInputRef = useRef<HTMLInputElement>(null)

  // Obtener mesas disponibles
  const { data: desksData } = useQuery({
    queryKey: ['desks-for-import'],
    queryFn: () => desksApi.getDesks(),
    enabled: isOpen,
    staleTime: 5 * 60 * 1000
  })

  // Obtener usuarios disponibles
  const { data: usersData } = useQuery({
    queryKey: ['users-for-import'],
    queryFn: () => usersApi.getUsers({ status: 'active' }),
    enabled: isOpen,
    staleTime: 5 * 60 * 1000
  })

  // Mutación para importar leads
  const importLeadsMutation = useMutation({
    mutationFn: (importData: any) => leadImportApi.importFile(importData),
    onSuccess: (response) => {
      toast.dismiss('import-progress')
      
      const { imported = 0, errors = 0, duplicates = 0, total = 0, error_details = [] } = response
      
      // Mensaje principal de éxito con resumen detallado
      if (imported > 0) {
        toast.success(`¡Importación completada! ${imported} leads importados exitosamente`)
      } else {
        toast(`Importación completada sin nuevos leads`, {
          icon: 'ℹ️',
          style: {
            background: '#3b82f6',
            color: '#1e40af',
          },
          duration: 4000
        })
      }
      
      // Mostrar resumen detallado
      setTimeout(() => {
        toast(`📊 Resumen completo: ${imported} importados, ${errors} errores, ${duplicates} duplicados de ${total} procesados`, {
          duration: 10000,
          style: {
            background: imported > 0 ? '#10b981' : '#6b7280',
            color: imported > 0 ? '#065f46' : '#374151',
          }
        })
      }, 1000)
      
      // Información adicional si hay errores o duplicados
      if (errors > 0) {
        setTimeout(() => {
          toast(`⚠️ ${errors} leads tuvieron errores durante la importación`, {
            icon: '⚠️',
            style: {
              background: '#fbbf24',
              color: '#92400e',
            },
            duration: 8000
          })
        }, 2000)
        
        // Mostrar detalles de errores en consola
        if (error_details.length > 0) {
          console.log('Detalles de errores:', error_details)
        }
      }
      
      if (duplicates > 0) {
        setTimeout(() => {
          toast(`🔄 ${duplicates} leads duplicados fueron omitidos`, {
            icon: 'ℹ️',
            style: {
              background: '#3b82f6',
              color: '#1e40af',
            },
            duration: 6000
          })
        }, 3000)
      }
      
      onSuccess()
      handleClose()
    },
    onError: (error: any) => {
      toast.dismiss('import-progress')
      toast.error(error.response?.data?.message || 'Error al importar leads')
    }
  })

  const handleClose = () => {
    setCurrentStep(1)
    setFile(null)
    setCsvData([])
    setHeaders([])
    setColumnMapping({})
    setImportSettings({
      source: 'Import',
      status: 'new',
      skipDuplicates: false
    })
    setPreviewData([])
    onClose()
  }

  const handleFileUpload = (event: React.ChangeEvent<HTMLInputElement>) => {
    const uploadedFile = event.target.files?.[0]
    if (!uploadedFile) return

    const fileExtension = uploadedFile.name.split('.').pop()?.toLowerCase()
    if (!['csv', 'xlsx', 'xls'].includes(fileExtension || '')) {
      toast.error('Solo se permiten archivos CSV, XLS o XLSX')
      return
    }

    setFile(uploadedFile)
    parseFile(uploadedFile)
  }

  const parseFile = (file: File) => {
    const fileExtension = file.name.split('.').pop()?.toLowerCase()
    
    if (fileExtension === 'csv') {
      // Parsear CSV
      const reader = new FileReader()
      reader.onload = (e) => {
        const text = e.target?.result as string
        parseCsvText(text)
      }
      reader.onerror = () => {
        toast.error('Error al leer el archivo CSV')
      }
      reader.readAsText(file, 'UTF-8')
      
    } else if (fileExtension === 'xlsx' || fileExtension === 'xls') {
      // Parsear Excel
      const reader = new FileReader()
      reader.onload = (e) => {
        try {
          const arrayBuffer = new Uint8Array(e.target?.result as ArrayBuffer)
          const workbook = XLSX.read(arrayBuffer, { type: 'array' })
          
          // Tomar la primera hoja
          const sheetName = workbook.SheetNames[0]
          const worksheet = workbook.Sheets[sheetName]
          
          // Convertir a JSON
          const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 })
          
          if (jsonData.length === 0) {
            toast.error('El archivo Excel está vacío')
            return
          }
          
          // La primera fila son los headers
          const headers = (jsonData[0] as any[]).map(h => String(h || '').trim())
          
          // Las demás filas son los datos
          const processedData = jsonData.slice(1)
            .filter((row: any) => row && row.some((cell: any) => cell !== null && cell !== undefined && String(cell).trim() !== ''))
            .map((row: any) => {
              const rowData: any = {}
              headers.forEach((header, index) => {
                rowData[header] = String(row[index] || '').trim()
              })
              return rowData
            })
          
          processData(headers, processedData)
          
        } catch (error) {
          console.error('Error parsing Excel:', error)
          toast.error('Error al procesar el archivo Excel')
        }
      }
      reader.onerror = () => {
        toast.error('Error al leer el archivo Excel')
      }
      reader.readAsArrayBuffer(file)
    }
  }

  const parseCsvText = (text: string) => {
    // Parsear CSV - detectar separador automáticamente
    const lines = text.split('\n').filter(line => line.trim())
    if (lines.length === 0) {
      toast.error('El archivo CSV está vacío')
      return
    }
    
    // Detectar separador (coma, punto y coma, tabulación)
    const firstLine = lines[0]
    let separator = ','
    
    const separators = [',', ';', '\t', '|']
    let maxColumns = 0
    
    for (const sep of separators) {
      const columns = firstLine.split(sep)
      if (columns.length > maxColumns) {
        maxColumns = columns.length
        separator = sep
      }
    }
    
    // Parsear con el separador detectado
    const headers = lines[0].split(separator).map(h => h.trim().replace(/['"]/g, ''))
    
    const csvData = lines.slice(1)
      .filter(line => line.trim())
      .map(line => {
        const values = line.split(separator).map(v => v.trim().replace(/['"]/g, ''))
        const row: any = {}
        headers.forEach((header, index) => {
          row[header] = values[index] || ''
        })
        return row
      })
    
    processData(headers, csvData)
  }

  const processData = (headers: string[], data: any[]) => {
    if (headers.length === 0 || data.length === 0) {
      toast.error('No se pudieron detectar columnas o datos en el archivo')
      return
    }

    setHeaders(headers)
    setCsvData(data)
    setPreviewData(data.slice(0, 5)) // Mostrar solo las primeras 5 filas
    
    // Auto-mapear columnas comunes
    const autoMapping: {[key: string]: string} = {}
    REQUIRED_FIELDS.concat(OPTIONAL_FIELDS).forEach(field => {
      const matchingHeader = headers.find(header => {
        const headerLower = header.toLowerCase()
        const fieldLower = field.key.toLowerCase()
        const labelLower = field.label.toLowerCase()
        
        return headerLower.includes(fieldLower) ||
               headerLower.includes(labelLower) ||
               fieldLower.includes(headerLower) ||
               labelLower.includes(headerLower) ||
               // Mapeos específicos en español
               (fieldLower === 'first_name' && (headerLower.includes('nombre') || headerLower.includes('name'))) ||
               (fieldLower === 'last_name' && (headerLower.includes('apellido') || headerLower.includes('surname'))) ||
               (fieldLower === 'email' && (headerLower.includes('correo') || headerLower.includes('mail'))) ||
               (fieldLower === 'phone' && (headerLower.includes('telefono') || headerLower.includes('tel') || headerLower.includes('phone'))) ||
               (fieldLower === 'company' && (headerLower.includes('empresa') || headerLower.includes('company'))) ||
               (fieldLower === 'position' && (headerLower.includes('cargo') || headerLower.includes('puesto') || headerLower.includes('position')))
      })
      if (matchingHeader) {
        autoMapping[field.key] = matchingHeader
      }
    })
    setColumnMapping(autoMapping)
    
    toast.success(`Archivo procesado: ${headers.length} columnas, ${data.length} filas`)
  }

  const handleNext = () => {
    if (validateCurrentStep()) {
      setCurrentStep(prev => Math.min(prev + 1, STEPS.length))
    }
  }

  const handlePrevious = () => {
    setCurrentStep(prev => Math.max(prev - 1, 1))
  }

  const validateCurrentStep = (): boolean => {
    switch (currentStep) {
      case 1:
        if (!file) {
          toast.error('Debe seleccionar un archivo')
          return false
        }
        if (csvData.length === 0) {
          toast.error('El archivo está vacío o no se pudo procesar')
          return false
        }
        return true
      case 2:
        // Verificar que los campos requeridos estén mapeados
        const missingRequired = REQUIRED_FIELDS.filter(field => !columnMapping[field.key])
        if (missingRequired.length > 0) {
          toast.error(`Debe mapear los campos requeridos: ${missingRequired.map(f => f.label).join(', ')}`)
          return false
        }
        return true
      case 3:
        return true
      default:
        return true
    }
  }

  const handleImport = () => {
    if (!validateCurrentStep()) return

    const mappedData = csvData.map(row => {
      const mappedRow: any = {}
      Object.keys(columnMapping).forEach(fieldKey => {
        const headerKey = columnMapping[fieldKey]
        if (headerKey && row[headerKey]) {
          mappedRow[fieldKey] = row[headerKey]
        }
      })
      
      // Agregar configuraciones globales
      mappedRow.desk_id = importSettings.desk_id || null
      mappedRow.assigned_to = importSettings.assigned_to || null
      mappedRow.source = importSettings.source
      mappedRow.status = importSettings.status
      
      return mappedRow
    })

    // Mostrar progreso para grandes volúmenes
    if (mappedData.length > 100) {
      toast.loading(`Procesando ${mappedData.length} leads...`, {
        duration: 4000,
        id: 'import-progress'
      })
    }

    // Invertir el mapping para que las claves sean las columnas del archivo
    // y los valores sean los campos del sistema
    const invertedMapping: {[key: string]: string} = {}
    Object.keys(columnMapping).forEach(fieldKey => {
      const headerKey = columnMapping[fieldKey]
      if (headerKey) {
        invertedMapping[headerKey] = fieldKey
      }
    })

    importLeadsMutation.mutate({
      data: mappedData,
      mapping: invertedMapping,
      options: {
        skip_first_row: false, // Ya procesamos todo, no hay que saltar filas
        duplicate_action: importSettings.skipDuplicates ? 'skip' : 'create'
      }
    })
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-secondary-800 rounded-lg w-full max-w-5xl mx-4 max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-secondary-200 dark:border-secondary-700">
          <div>
            <h2 className="text-xl font-semibold text-secondary-900 dark:text-white">
              Asistente de Importación
            </h2>
            <p className="text-sm text-secondary-600 dark:text-secondary-400 mt-1">
              Paso {currentStep} de {STEPS.length}: {STEPS[currentStep - 1].title}
            </p>
          </div>
          <button
            onClick={handleClose}
            className="text-secondary-400 hover:text-secondary-600 dark:hover:text-secondary-300"
          >
            <XMarkIcon className="w-6 h-6" />
          </button>
        </div>

        {/* Progress Steps */}
        <div className="px-6 py-4 border-b border-secondary-200 dark:border-secondary-700">
          <div className="flex items-center justify-between">
            {STEPS.map((step, index) => {
              const Icon = step.icon
              const isActive = currentStep === step.id
              const isCompleted = currentStep > step.id
              
              return (
                <div key={step.id} className="flex items-center">
                  <div className={`
                    flex items-center justify-center w-10 h-10 rounded-full border-2 transition-colors
                    ${isActive 
                      ? 'border-primary-600 bg-primary-600 text-white' 
                      : isCompleted 
                        ? 'border-success-600 bg-success-600 text-white'
                        : 'border-secondary-300 text-secondary-400'
                    }
                  `}>
                    {isCompleted ? (
                      <CheckIcon className="w-5 h-5" />
                    ) : (
                      <Icon className="w-5 h-5" />
                    )}
                  </div>
                  <div className="ml-3 hidden sm:block">
                    <p className={`text-sm font-medium ${
                      isActive ? 'text-primary-600' : isCompleted ? 'text-success-600' : 'text-secondary-500'
                    }`}>
                      {step.title}
                    </p>
                  </div>
                  {index < STEPS.length - 1 && (
                    <div className={`
                      w-12 h-0.5 mx-4 transition-colors
                      ${isCompleted ? 'bg-success-600' : 'bg-secondary-300'}
                    `} />
                  )}
                </div>
              )
            })}
          </div>
        </div>

        {/* Content */}
        <div className="p-6 overflow-y-auto max-h-96">
          {/* Step 1: Subir Archivo */}
          {currentStep === 1 && (
            <div className="space-y-6">
              <div className="text-center mb-6">
                <DocumentArrowUpIcon className="w-12 h-12 text-primary-600 mx-auto mb-2" />
                <h3 className="text-lg font-medium text-secondary-900 dark:text-white">
                  Subir Archivo
                </h3>
                <p className="text-sm text-secondary-600 dark:text-secondary-400">
                  Selecciona un archivo Excel o CSV con los datos de leads
                </p>
              </div>

              <div className="border-2 border-dashed border-secondary-300 dark:border-secondary-600 rounded-lg p-8">
                <div className="text-center">
                  <DocumentArrowUpIcon className="w-16 h-16 text-secondary-400 mx-auto mb-4" />
                  <div className="space-y-2">
                    <p className="text-lg font-medium text-secondary-900 dark:text-white">
                      Arrastra tu archivo aquí
                    </p>
                    <p className="text-sm text-secondary-600 dark:text-secondary-400">
                      o haz clic para seleccionar
                    </p>
                  </div>
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept=".csv,.xlsx,.xls"
                    onChange={handleFileUpload}
                    className="hidden"
                  />
                  <button
                    onClick={() => fileInputRef.current?.click()}
                    className="btn-primary mt-4"
                  >
                    Seleccionar Archivo
                  </button>
                </div>
              </div>

              {file && (
                <div className="bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-800 rounded-lg p-4">
                  <div className="flex items-center">
                    <CheckIcon className="w-5 h-5 text-success-600 mr-2" />
                    <div>
                      <p className="font-medium text-success-800 dark:text-success-200">
                        Archivo cargado: {file.name}
                      </p>
                      <p className="text-sm text-success-600 dark:text-success-300">
                        {csvData.length} filas detectadas
                      </p>
                    </div>
                  </div>
                </div>
              )}

              <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <h4 className="font-medium text-blue-800 dark:text-blue-200 mb-2">
                  Formato requerido:
                </h4>
                <ul className="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                  <li>• <strong>Campos obligatorios:</strong> Nombre, Apellido, Email, Teléfono</li>
                  <li>• <strong>Campos opcionales:</strong> Empresa, Posición, País, Ciudad, Presupuesto, Notas</li>
                  <li>• <strong>Formatos soportados:</strong> Excel (.xlsx, .xls), CSV</li>
                  <li>• <strong>Excel:</strong> Se lee la primera hoja automáticamente</li>
                  <li>• <strong>CSV:</strong> Separadores detectados automáticamente (,;|\t)</li>
                  <li>• <strong>Codificación:</strong> UTF-8 recomendado</li>
                </ul>
              </div>
            </div>
          )}

          {/* Step 2: Mapear Columnas */}
          {currentStep === 2 && (
            <div className="space-y-6">
              <div className="text-center mb-6">
                <TableCellsIcon className="w-12 h-12 text-primary-600 mx-auto mb-2" />
                <h3 className="text-lg font-medium text-secondary-900 dark:text-white">
                  Mapear Columnas
                </h3>
                <p className="text-sm text-secondary-600 dark:text-secondary-400">
                  Relaciona las columnas de tu archivo con los campos del sistema
                </p>
              </div>

              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                  <h4 className="font-medium text-secondary-900 dark:text-white mb-4">
                    Campos Requeridos
                  </h4>
                  <div className="space-y-3">
                    {REQUIRED_FIELDS.map(field => (
                      <div key={field.key}>
                        <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-1">
                          {field.label} *
                        </label>
                        <select
                          value={columnMapping[field.key] || ''}
                          onChange={(e) => setColumnMapping({
                            ...columnMapping,
                            [field.key]: e.target.value
                          })}
                          className="input"
                        >
                          <option value="">Seleccionar columna...</option>
                          {headers.map(header => (
                            <option key={header} value={header}>{header}</option>
                          ))}
                        </select>
                      </div>
                    ))}
                  </div>
                </div>

                <div>
                  <h4 className="font-medium text-secondary-900 dark:text-white mb-4">
                    Campos Opcionales
                  </h4>
                  <div className="space-y-3">
                    {OPTIONAL_FIELDS.map(field => (
                      <div key={field.key}>
                        <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-1">
                          {field.label}
                        </label>
                        <select
                          value={columnMapping[field.key] || ''}
                          onChange={(e) => setColumnMapping({
                            ...columnMapping,
                            [field.key]: e.target.value
                          })}
                          className="input"
                        >
                          <option value="">No mapear</option>
                          {headers.map(header => (
                            <option key={header} value={header}>{header}</option>
                          ))}
                        </select>
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              {previewData.length > 0 && (
                <div>
                  <h4 className="font-medium text-secondary-900 dark:text-white mb-4">
                    Vista Previa (primeras 5 filas)
                  </h4>
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-secondary-200 dark:divide-secondary-700">
                      <thead className="bg-secondary-50 dark:bg-secondary-800">
                        <tr>
                          {headers.map(header => (
                            <th key={header} className="px-3 py-2 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                              {header}
                            </th>
                          ))}
                        </tr>
                      </thead>
                      <tbody className="bg-white dark:bg-secondary-900 divide-y divide-secondary-200 dark:divide-secondary-700">
                        {previewData.map((row, index) => (
                          <tr key={index}>
                            {headers.map(header => (
                              <td key={header} className="px-3 py-2 whitespace-nowrap text-sm text-secondary-900 dark:text-white">
                                {row[header]}
                              </td>
                            ))}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Step 3: Configuración */}
          {currentStep === 3 && (
            <div className="space-y-6">
              <div className="text-center mb-6">
                <DocumentTextIcon className="w-12 h-12 text-primary-600 mx-auto mb-2" />
                <h3 className="text-lg font-medium text-secondary-900 dark:text-white">
                  Configuración de Importación
                </h3>
                <p className="text-sm text-secondary-600 dark:text-secondary-400">
                  Configura las opciones de importación
                </p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Mesa por Defecto
                  </label>
                  <select
                    value={importSettings.desk_id || ''}
                    onChange={(e) => setImportSettings({
                      ...importSettings,
                      desk_id: e.target.value ? parseInt(e.target.value) : undefined
                    })}
                    className="input"
                  >
                    <option value="">Sin mesa asignada</option>
                    {desksData?.data?.map((desk: any) => (
                      <option key={desk.id} value={desk.id}>
                        {desk.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Usuario por Defecto
                  </label>
                  <select
                    value={importSettings.assigned_to || ''}
                    onChange={(e) => setImportSettings({
                      ...importSettings,
                      assigned_to: e.target.value ? parseInt(e.target.value) : undefined
                    })}
                    className="input"
                  >
                    <option value="">Sin asignar</option>
                    {usersData?.data?.map((user: any) => (
                      <option key={user.id} value={user.id}>
                        {user.first_name} {user.last_name}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Fuente
                  </label>
                  <input
                    type="text"
                    value={importSettings.source}
                    onChange={(e) => setImportSettings({
                      ...importSettings,
                      source: e.target.value
                    })}
                    className="input"
                    placeholder="ej: Import, CSV, Excel"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                    Estado por Defecto
                  </label>
                  <select
                    value={importSettings.status}
                    onChange={(e) => setImportSettings({
                      ...importSettings,
                      status: e.target.value
                    })}
                    className="input"
                  >
                    <option value="new">Nuevo</option>
                    <option value="contacted">Contactado</option>
                    <option value="qualified">Calificado</option>
                  </select>
                </div>
              </div>

              <div className="flex items-center">
                <input
                  type="checkbox"
                  id="skipDuplicates"
                  checked={importSettings.skipDuplicates}
                  onChange={(e) => setImportSettings({
                    ...importSettings,
                    skipDuplicates: e.target.checked
                  })}
                  className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
                />
                <label htmlFor="skipDuplicates" className="ml-2 text-sm text-secondary-700 dark:text-secondary-300">
                  Omitir leads duplicados (basado en email)
                </label>
              </div>
            </div>
          )}

          {/* Step 4: Importar */}
          {currentStep === 4 && (
            <div className="space-y-6">
              <div className="text-center mb-6">
                <CheckIcon className="w-12 h-12 text-success-600 mx-auto mb-2" />
                <h3 className="text-lg font-medium text-secondary-900 dark:text-white">
                  Listo para Importar
                </h3>
                <p className="text-sm text-secondary-600 dark:text-secondary-400">
                  Revisa el resumen antes de proceder con la importación
                </p>
              </div>

              <div className="bg-secondary-50 dark:bg-secondary-900 rounded-lg p-6 space-y-4">
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <span className="text-secondary-600 dark:text-secondary-400">Archivo:</span>
                    <span className="ml-2 font-medium">{file?.name}</span>
                  </div>
                  <div>
                    <span className="text-secondary-600 dark:text-secondary-400">Total de leads:</span>
                    <span className="ml-2 font-medium">{csvData.length}</span>
                  </div>
                  <div>
                    <span className="text-secondary-600 dark:text-secondary-400">Mesa por defecto:</span>
                    <span className="ml-2 font-medium">
                      {desksData?.data?.find((d: any) => d.id === importSettings.desk_id)?.name || 'No asignada'}
                    </span>
                  </div>
                  <div>
                    <span className="text-secondary-600 dark:text-secondary-400">Usuario por defecto:</span>
                    <span className="ml-2 font-medium">
                      {usersData?.data?.find((u: any) => u.id === importSettings.assigned_to)?.first_name || 'No asignado'}
                    </span>
                  </div>
                  <div>
                    <span className="text-secondary-600 dark:text-secondary-400">Fuente:</span>
                    <span className="ml-2 font-medium">{importSettings.source}</span>
                  </div>
                  <div>
                    <span className="text-secondary-600 dark:text-secondary-400">Estado:</span>
                    <span className="ml-2 font-medium capitalize">{importSettings.status}</span>
                  </div>
                </div>

                <div className="border-t border-secondary-200 dark:border-secondary-700 pt-4">
                  <h4 className="font-medium text-secondary-900 dark:text-white mb-2">
                    Campos Mapeados:
                  </h4>
                  <div className="grid grid-cols-2 gap-2 text-sm">
                    {Object.entries(columnMapping).map(([field, column]) => (
                      <div key={field}>
                        <span className="text-secondary-600 dark:text-secondary-400">
                          {REQUIRED_FIELDS.concat(OPTIONAL_FIELDS).find(f => f.key === field)?.label}:
                        </span>
                        <span className="ml-2 font-medium">{column}</span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              {importSettings.skipDuplicates && (
                <div className="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                  <div className="flex items-center">
                    <ExclamationTriangleIcon className="w-5 h-5 text-yellow-600 mr-2" />
                    <p className="text-sm text-yellow-800 dark:text-yellow-200">
                      Los leads con emails duplicados serán omitidos automáticamente
                    </p>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between p-6 border-t border-secondary-200 dark:border-secondary-700">
          <button
            onClick={handlePrevious}
            disabled={currentStep === 1}
            className="btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ArrowLeftIcon className="w-4 h-4 mr-2" />
            Anterior
          </button>

          <div className="flex space-x-3">
            <button
              onClick={handleClose}
              className="btn-secondary"
            >
              Cancelar
            </button>
            
            {currentStep < STEPS.length ? (
              <button
                onClick={handleNext}
                className="btn-primary"
              >
                Siguiente
                <ArrowRightIcon className="w-4 h-4 ml-2" />
              </button>
            ) : (
              <button
                onClick={handleImport}
                disabled={importLeadsMutation.isPending}
                className="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {importLeadsMutation.isPending ? 'Importando...' : `Importar ${csvData.length} Leads`}
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
