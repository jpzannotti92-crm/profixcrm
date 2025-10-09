import { useState, useRef } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import * as XLSX from 'xlsx'
import { 
  CloudArrowUpIcon,
  DocumentArrowUpIcon,
  MagnifyingGlassIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XCircleIcon,
  ArrowRightIcon,
  ArrowLeftIcon,
  EyeIcon,
  ClockIcon,
  DocumentTextIcon
} from '@heroicons/react/24/outline'

import { leadImportApi } from '../../services/api'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import { cn } from '../../utils/cn'
import toast from 'react-hot-toast'

interface SystemField {
  label: string
  type: string
  required: boolean
  description: string
  options?: string[]
  default?: string
}

interface DetectedColumn {
  header: string
  sample: string
  suggested_field: string
}

interface FileAnalysis {
  filename: string
  file_type: string
  total_rows: number
  detected_columns: Record<string, DetectedColumn>
  sample_data: string[][]
  estimated_import_time: number
  file_size: number
  analysis_timestamp: string
}

interface ImportRecord {
  id: number
  filename: string
  total_rows: number
  imported_rows: number
  failed_rows: number
  duplicate_rows?: number
  updated_rows?: number
  status: 'processing' | 'completed' | 'failed'
  created_at: string
  created_by: string
  mapping: Record<string, string>
  errors?: Array<{
    row: number
    column: string
    field: string
    value: string
    error: string
  }>
  warnings?: Array<{
    row: number
    column: string
    field: string
    value: string
    warning: string
  }>
  processing_time?: string
}

type ImportStep = 'upload' | 'analyze' | 'mapping' | 'preview' | 'import' | 'results'

export default function LeadImportPage() {
  const [currentStep, setCurrentStep] = useState<ImportStep>('upload')
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [fileAnalysis, setFileAnalysis] = useState<FileAnalysis | null>(null)
  const [columnMapping, setColumnMapping] = useState<Record<string, string>>({})
  const [importSettings, setImportSettings] = useState({
    skip_first_row: true,
    duplicate_action: 'skip' as 'skip' | 'update' | 'create'
  })
  const [importResult, setImportResult] = useState<ImportRecord | null>(null)
  const [showHistoryModal, setShowHistoryModal] = useState(false)
  
  const fileInputRef = useRef<HTMLInputElement>(null)
  const queryClient = useQueryClient()

  // Obtener campos del sistema
  const { data: systemFields } = useQuery<{ data: Record<string, SystemField> }>({
    queryKey: ['system-fields'],
    queryFn: () => leadImportApi.getSystemFields(),
    staleTime: 10 * 60 * 1000, // 10 minutos
  })

  // Obtener historial de importaciones
  const { data: importHistory } = useQuery<{ data: ImportRecord[] }>({
    queryKey: ['import-history'],
    queryFn: () => leadImportApi.getImportHistory(),
    refetchInterval: 5000, // Actualizar cada 5 segundos
  })

  // Mutación para analizar archivo
  const analyzeFileMutation = useMutation<{ data: FileAnalysis }, Error, File>({
    mutationFn: (file: File) => leadImportApi.analyzeFile(file),
    onSuccess: (data) => {
      setFileAnalysis(data.data)
      
      // Auto-mapear columnas sugeridas
      const autoMapping: Record<string, string> = {}
      Object.entries(data.data.detected_columns).forEach(([column, info]) => {
        if (info.suggested_field) {
          autoMapping[column] = info.suggested_field
        }
      })
      setColumnMapping(autoMapping)
      
      setCurrentStep('mapping')
      toast.success('Archivo analizado correctamente')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Error al analizar el archivo')
    }
  })

  // Mutación para importar leads
  const importLeadsMutation = useMutation<{ data: ImportRecord }, Error, any>({
    mutationFn: (data: any) => leadImportApi.importFile(data),
    onSuccess: (data) => {
      setImportResult(data.data)
      setCurrentStep('results')
      queryClient.invalidateQueries({ queryKey: ['import-history'] })
      toast.success('Importación completada')
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Error en la importación')
    }
  })

  const handleFileSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    if (file) {
      // Validar tipo de archivo
      const allowedTypes = [
        'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      ]
      
      if (!allowedTypes.includes(file.type)) {
        toast.error('Tipo de archivo no soportado. Use CSV, XLS o XLSX')
        return
      }

      // Validar tamaño (máximo 10MB)
      if (file.size > 10 * 1024 * 1024) {
        toast.error('El archivo es demasiado grande. Máximo 10MB')
        return
      }

      setSelectedFile(file)
      setCurrentStep('analyze')
    }
  }

  const handleAnalyzeFile = () => {
    if (selectedFile) {
      analyzeFileMutation.mutate(selectedFile)
    }
  }

  const handleColumnMapping = (column: string, field: string) => {
    setColumnMapping(prev => ({
      ...prev,
      [column]: field
    }))
  }

  const handleRemoveMapping = (column: string) => {
    setColumnMapping(prev => {
      const newMapping = { ...prev }
      delete newMapping[column]
      return newMapping
    })
  }

  const validateMapping = () => {
    if (!systemFields?.data) return false
    
    const requiredFields = Object.entries(systemFields.data)
      .filter(([_, field]) => field.required)
      .map(([key, _]) => key)
    
    const mappedFields = Object.values(columnMapping)
    
    return requiredFields.every(field => mappedFields.includes(field))
  }

  const handleStartImport = async () => {
    if (!fileAnalysis || !validateMapping() || !selectedFile) {
      toast.error('Complete el mapeo de campos requeridos')
      return
    }

    try {
      let processedData: Record<string, string>[] = []
      
      // Obtener la extensión del archivo
      const fileExtension = selectedFile.name.split('.').pop()?.toLowerCase()
      
      if (fileExtension === 'xlsx' || fileExtension === 'xls') {
        // Procesar archivo Excel
        const arrayBuffer = await selectedFile.arrayBuffer()
        const workbook = XLSX.read(arrayBuffer, { type: 'array' })
        const firstSheetName = workbook.SheetNames[0]
        const worksheet = workbook.Sheets[firstSheetName]
        
        // Convertir a JSON con header en la primera fila
        const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 }) as any[][]
        
        // Saltar la primera fila si está configurado
        const startRow = importSettings.skip_first_row ? 1 : 0
        const dataRows = jsonData.slice(startRow)
        
        // Convertir cada fila a objeto con claves de columna (A, B, C, etc.)
        processedData = dataRows.map((row: any[]) => {
          const rowData: Record<string, string> = {}
          row.forEach((value, colIndex) => {
            const columnKey = String.fromCharCode(65 + colIndex) // A=65, B=66, etc.
            rowData[columnKey] = value ? String(value).trim() : ''
          })
          return rowData
        })
      } else {
        // Procesar archivo CSV (método original)
        const fileContent = await selectedFile.text()
        const lines = fileContent.split('\n').filter(line => line.trim())
        
        const startRow = importSettings.skip_first_row ? 1 : 0
        processedData = lines.slice(startRow).map((line) => {
          // Separar por comas o punto y coma
          const values = line.split(/[,;]/).map(value => value.trim().replace(/^["']|["']$/g, ''))
          const rowData: Record<string, string> = {}
          
          // Convertir array a objeto usando índices de columnas (A, B, C, etc.)
          values.forEach((value, colIndex) => {
            const columnKey = String.fromCharCode(65 + colIndex) // A=65, B=66, etc.
            rowData[columnKey] = value
          })
          return rowData
        })
      }

      const importData = {
        data: processedData,
        mapping: columnMapping,
        options: {
          skip_first_row: importSettings.skip_first_row,
          duplicate_action: importSettings.duplicate_action,
          filename: fileAnalysis.filename
        }
      }

      importLeadsMutation.mutate(importData)
    } catch (error) {
      console.error('Error procesando el archivo:', error)
      toast.error('Error al procesar el archivo')
    }
  }

  const resetImport = () => {
    setCurrentStep('upload')
    setSelectedFile(null)
    setFileAnalysis(null)
    setColumnMapping({})
    setImportResult(null)
    if (fileInputRef.current) {
      fileInputRef.current.value = ''
    }
  }

  const getStepStatus = (step: ImportStep) => {
    const steps: ImportStep[] = ['upload', 'analyze', 'mapping', 'preview', 'import', 'results']
    const currentIndex = steps.indexOf(currentStep)
    const stepIndex = steps.indexOf(step)
    
    if (stepIndex < currentIndex) return 'completed'
    if (stepIndex === currentIndex) return 'current'
    return 'pending'
  }

  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
  }



  return (
    <div className="space-y-6 animate-fade-in">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-secondary-900 dark:text-white">
            Importar Leads
          </h1>
          <p className="text-secondary-600 dark:text-secondary-400 mt-1">
            Importa leads desde archivos CSV o Excel con mapeo dinámico de columnas
          </p>
        </div>
        
        <div className="flex space-x-3 mt-4 sm:mt-0">
          <button 
            onClick={() => setShowHistoryModal(true)}
            className="btn-secondary"
          >
            <ClockIcon className="w-4 h-4 mr-2" />
            Historial
          </button>
          {currentStep !== 'upload' && (
            <button 
              onClick={resetImport}
              className="btn-ghost"
            >
              Nueva Importación
            </button>
          )}
        </div>
      </div>

      {/* Stepper */}
      <div className="card">
        <div className="card-body">
          <div className="flex items-center justify-between">
            {[
              { key: 'upload', label: 'Subir Archivo', icon: CloudArrowUpIcon },
              { key: 'analyze', label: 'Analizar', icon: MagnifyingGlassIcon },
              { key: 'mapping', label: 'Mapear Campos', icon: DocumentTextIcon },
              { key: 'import', label: 'Importar', icon: DocumentArrowUpIcon },
              { key: 'results', label: 'Resultados', icon: CheckCircleIcon }
            ].map((step, index) => {
              const status = getStepStatus(step.key as ImportStep)
              const IconComponent = step.icon
              
              return (
                <div key={step.key} className="flex items-center">
                  <div className={cn(
                    'flex items-center justify-center w-10 h-10 rounded-full border-2 transition-colors',
                    status === 'completed' ? 'bg-success-100 border-success-500 text-success-700 dark:bg-success-900 dark:text-success-300' :
                    status === 'current' ? 'bg-primary-100 border-primary-500 text-primary-700 dark:bg-primary-900 dark:text-primary-300' :
                    'bg-secondary-100 border-secondary-300 text-secondary-500 dark:bg-secondary-800 dark:border-secondary-600'
                  )}>
                    <IconComponent className="w-5 h-5" />
                  </div>
                  <div className="ml-3">
                    <div className={cn(
                      'text-sm font-medium',
                      status === 'completed' ? 'text-success-700 dark:text-success-300' :
                      status === 'current' ? 'text-primary-700 dark:text-primary-300' :
                      'text-secondary-500 dark:text-secondary-400'
                    )}>
                      {step.label}
                    </div>
                  </div>
                  {index < 4 && (
                    <ArrowRightIcon className="w-5 h-5 text-secondary-400 mx-4" />
                  )}
                </div>
              )
            })}
          </div>
        </div>
      </div>

      {/* Contenido por paso */}
      {currentStep === 'upload' && (
        <div className="card">
          <div className="card-body text-center py-12">
            <div className="mx-auto w-24 h-24 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center mb-6">
              <CloudArrowUpIcon className="w-12 h-12 text-primary-600 dark:text-primary-400" />
            </div>
            
            <h3 className="text-lg font-semibold text-secondary-900 dark:text-white mb-2">
              Selecciona un archivo para importar
            </h3>
            <p className="text-secondary-600 dark:text-secondary-400 mb-8">
              Soportamos archivos CSV, XLS y XLSX hasta 10MB
            </p>
            
            <input
              ref={fileInputRef}
              type="file"
              accept=".csv,.xls,.xlsx"
              onChange={handleFileSelect}
              className="hidden"
            />
            
            <button
              onClick={() => fileInputRef.current?.click()}
              className="btn-primary"
            >
              <DocumentArrowUpIcon className="w-5 h-5 mr-2" />
              Seleccionar Archivo
            </button>
            
            <div className="mt-8 text-sm text-secondary-500 dark:text-secondary-400">
              <p>Formatos soportados: CSV, XLS, XLSX</p>
              <p>Tamaño máximo: 10MB</p>
            </div>
          </div>
        </div>
      )}

      {currentStep === 'analyze' && (
        <div className="card">
          <div className="card-body text-center py-12">
            <div className="mx-auto w-24 h-24 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mb-6">
              <MagnifyingGlassIcon className="w-12 h-12 text-blue-600 dark:text-blue-400" />
            </div>
            
            <h3 className="text-lg font-semibold text-secondary-900 dark:text-white mb-2">
              Archivo seleccionado: {selectedFile?.name}
            </h3>
            <p className="text-secondary-600 dark:text-secondary-400 mb-2">
              Tamaño: {selectedFile ? formatFileSize(selectedFile.size) : ''}
            </p>
            <p className="text-secondary-600 dark:text-secondary-400 mb-8">
              Haz clic en "Analizar" para detectar las columnas automáticamente
            </p>
            
            <button
              onClick={handleAnalyzeFile}
              disabled={analyzeFileMutation.isPending}
              className="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {analyzeFileMutation.isPending ? (
                <>
                  <LoadingSpinner size="sm" className="mr-2" />
                  Analizando...
                </>
              ) : (
                <>
                  <MagnifyingGlassIcon className="w-5 h-5 mr-2" />
                  Analizar Archivo
                </>
              )}
            </button>
          </div>
        </div>
      )}

      {currentStep === 'mapping' && fileAnalysis && systemFields?.data && (
        <div className="space-y-6">
          {/* Información del archivo */}
          <div className="card">
            <div className="card-body">
              <h3 className="text-lg font-semibold text-secondary-900 dark:text-white mb-4">
                Información del Archivo
              </h3>
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="text-center">
                  <div className="text-2xl font-bold text-primary-600 dark:text-primary-400">
                    {fileAnalysis.total_rows}
                  </div>
                  <div className="text-sm text-secondary-600 dark:text-secondary-400">
                    Filas totales
                  </div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    {Object.keys(fileAnalysis.detected_columns).length}
                  </div>
                  <div className="text-sm text-secondary-600 dark:text-secondary-400">
                    Columnas detectadas
                  </div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-green-600 dark:text-green-400">
                    {formatFileSize(fileAnalysis.file_size)}
                  </div>
                  <div className="text-sm text-secondary-600 dark:text-secondary-400">
                    Tamaño del archivo
                  </div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                    ~{fileAnalysis.estimated_import_time}s
                  </div>
                  <div className="text-sm text-secondary-600 dark:text-secondary-400">
                    Tiempo estimado
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Mapeo de columnas */}
          <div className="card">
            <div className="card-body">
              <h3 className="text-lg font-semibold text-secondary-900 dark:text-white mb-4">
                Mapear Columnas del Archivo
              </h3>
              <p className="text-secondary-600 dark:text-secondary-400 mb-6">
                Asocia cada columna de tu archivo con los campos del sistema. Los campos marcados con * son obligatorios.
              </p>
              
              <div className="space-y-4">
                {Object.entries(fileAnalysis.detected_columns).map(([column, info]) => (
                  <div key={column} className="border border-secondary-200 dark:border-secondary-700 rounded-lg p-4">
                    <div className="flex items-center justify-between mb-3">
                      <div className="flex items-center space-x-3">
                        <div className="w-8 h-8 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center text-sm font-medium text-primary-600 dark:text-primary-400">
                          {column}
                        </div>
                        <div>
                          <div className="font-medium text-secondary-900 dark:text-white">
                            {info.header}
                          </div>
                          <div className="text-sm text-secondary-500 dark:text-secondary-400">
                            Ejemplo: "{info.sample}"
                          </div>
                        </div>
                      </div>
                      
                      {columnMapping[column] && (
                        <button
                          onClick={() => handleRemoveMapping(column)}
                          className="text-danger-600 hover:text-danger-900 dark:text-danger-400"
                          title="Quitar mapeo"
                        >
                          <XCircleIcon className="w-5 h-5" />
                        </button>
                      )}
                    </div>
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                          Mapear a campo del sistema
                        </label>
                        <select
                          value={columnMapping[column] || ''}
                          onChange={(e) => handleColumnMapping(column, e.target.value)}
                          className="input"
                        >
                          <option value="">-- No mapear --</option>
                          {Object.entries(systemFields.data).map(([fieldKey, field]) => (
                            <option key={fieldKey} value={fieldKey}>
                              {field.label} {field.required ? '*' : ''}
                            </option>
                          ))}
                        </select>
                      </div>
                      
                      {columnMapping[column] && systemFields.data[columnMapping[column]] && (
                        <div>
                          <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                            Información del campo
                          </label>
                          <div className="p-3 bg-secondary-50 dark:bg-secondary-700 rounded-lg text-sm">
                            <div className="font-medium text-secondary-900 dark:text-white mb-1">
                              {systemFields.data[columnMapping[column]].label}
                              {systemFields.data[columnMapping[column]].required && (
                                <span className="text-danger-600 dark:text-danger-400 ml-1">*</span>
                              )}
                            </div>
                            <div className="text-secondary-600 dark:text-secondary-400">
                              {systemFields.data[columnMapping[column]].description}
                            </div>
                            {systemFields.data[columnMapping[column]].options && (
                              <div className="mt-2">
                                <div className="text-xs font-medium text-secondary-700 dark:text-secondary-300">
                                  Valores válidos:
                                </div>
                                <div className="text-xs text-secondary-600 dark:text-secondary-400">
                                  {systemFields.data[columnMapping[column]].options?.join(', ')}
                                </div>
                              </div>
                            )}
                          </div>
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
              
              {/* Configuraciones de importación */}
              <div className="mt-8 p-4 bg-secondary-50 dark:bg-secondary-800 rounded-lg">
                <h4 className="font-medium text-secondary-900 dark:text-white mb-4">
                  Configuraciones de Importación
                </h4>
                
                <div className="space-y-4">
                  <div className="flex items-center">
                    <input
                      type="checkbox"
                      id="skip_first_row"
                      checked={importSettings.skip_first_row}
                      onChange={(e) => setImportSettings(prev => ({ ...prev, skip_first_row: e.target.checked }))}
                      className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
                    />
                    <label htmlFor="skip_first_row" className="ml-2 text-sm text-secondary-700 dark:text-secondary-300">
                      Omitir primera fila (encabezados)
                    </label>
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                      Acción para duplicados (mismo email)
                    </label>
                    <select
                      value={importSettings.duplicate_action}
                      onChange={(e) => setImportSettings(prev => ({ ...prev, duplicate_action: e.target.value as any }))}
                      className="input max-w-xs"
                    >
                      <option value="skip">Omitir duplicados</option>
                      <option value="update">Actualizar existentes</option>
                      <option value="create">Crear como nuevos</option>
                    </select>
                  </div>
                </div>
              </div>
              
              {/* Validación de mapeo */}
              <div className="mt-6">
                {!validateMapping() && (
                  <div className="p-4 bg-warning-50 dark:bg-warning-900 border border-warning-200 dark:border-warning-700 rounded-lg">
                    <div className="flex items-start">
                      <ExclamationTriangleIcon className="w-5 h-5 text-warning-600 dark:text-warning-400 mt-0.5 mr-3" />
                      <div>
                        <div className="font-medium text-warning-800 dark:text-warning-200">
                          Campos requeridos sin mapear
                        </div>
                        <div className="text-sm text-warning-700 dark:text-warning-300 mt-1">
                          Debes mapear todos los campos marcados con * antes de continuar.
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </div>
              
              <div className="flex justify-between mt-8">
                <button
                  onClick={() => setCurrentStep('analyze')}
                  className="btn-secondary"
                >
                  <ArrowLeftIcon className="w-4 h-4 mr-2" />
                  Volver
                </button>
                
                <button
                  onClick={() => setCurrentStep('preview')}
                  disabled={!validateMapping()}
                  className="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Vista Previa
                  <ArrowRightIcon className="w-4 h-4 ml-2" />
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {currentStep === 'preview' && fileAnalysis && (
        <div className="card">
          <div className="card-body">
            <h3 className="text-lg font-semibold text-secondary-900 dark:text-white mb-4">
              Vista Previa de la Importación
            </h3>
            
            {/* Resumen */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div className="p-4 bg-blue-50 dark:bg-blue-900 rounded-lg">
                <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                  {fileAnalysis.total_rows - (importSettings.skip_first_row ? 1 : 0)}
                </div>
                <div className="text-sm text-blue-700 dark:text-blue-300">
                  Filas a importar
                </div>
              </div>
              <div className="p-4 bg-green-50 dark:bg-green-900 rounded-lg">
                <div className="text-2xl font-bold text-green-600 dark:text-green-400">
                  {Object.keys(columnMapping).length}
                </div>
                <div className="text-sm text-green-700 dark:text-green-300">
                  Campos mapeados
                </div>
              </div>
              <div className="p-4 bg-purple-50 dark:bg-purple-900 rounded-lg">
                <div className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                  ~{fileAnalysis.estimated_import_time}s
                </div>
                <div className="text-sm text-purple-700 dark:text-purple-300">
                  Tiempo estimado
                </div>
              </div>
            </div>
            
            {/* Mapeo final */}
            <div className="mb-6">
              <h4 className="font-medium text-secondary-900 dark:text-white mb-3">
                Mapeo de Campos
              </h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {Object.entries(columnMapping).map(([column, field]) => (
                  <div key={column} className="flex items-center justify-between p-3 bg-secondary-50 dark:bg-secondary-700 rounded-lg">
                    <div className="flex items-center space-x-3">
                      <div className="w-6 h-6 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center text-xs font-medium text-primary-600 dark:text-primary-400">
                        {column}
                      </div>
                      <span className="text-sm text-secondary-600 dark:text-secondary-400">
                        {fileAnalysis.detected_columns[column]?.header}
                      </span>
                    </div>
                    <ArrowRightIcon className="w-4 h-4 text-secondary-400" />
                    <span className="text-sm font-medium text-secondary-900 dark:text-white">
                      {systemFields?.data?.[field]?.label}
                    </span>
                  </div>
                ))}
              </div>
            </div>
            
            {/* Muestra de datos */}
            <div className="mb-6">
              <h4 className="font-medium text-secondary-900 dark:text-white mb-3">
                Muestra de Datos (primeras 3 filas)
              </h4>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-secondary-50 dark:bg-secondary-800">
                    <tr>
                      {Object.entries(columnMapping).map(([column, field]) => (
                        <th key={column} className="px-4 py-2 text-left font-medium text-secondary-900 dark:text-white">
                          {systemFields?.data?.[field]?.label}
                          <div className="text-xs text-secondary-500 dark:text-secondary-400 font-normal">
                            Columna {column}
                          </div>
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-secondary-200 dark:divide-secondary-700">
                    {fileAnalysis.sample_data.slice(0, 3).map((row, rowIndex) => (
                      <tr key={rowIndex}>
                        {Object.keys(columnMapping).map((column) => {
                          const columnIndex = column.charCodeAt(0) - 65 // A=0, B=1, etc.
                          return (
                            <td key={column} className="px-4 py-2 text-secondary-900 dark:text-white">
                              {row[columnIndex] || '-'}
                            </td>
                          )
                        })}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
            
            <div className="flex justify-between">
              <button
                onClick={() => setCurrentStep('mapping')}
                className="btn-secondary"
              >
                <ArrowLeftIcon className="w-4 h-4 mr-2" />
                Volver al Mapeo
              </button>
              
              <button
                onClick={handleStartImport}
                disabled={importLeadsMutation.isPending}
                className="btn-success disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {importLeadsMutation.isPending ? (
                  <>
                    <LoadingSpinner size="sm" className="mr-2" />
                    Importando...
                  </>
                ) : (
                  <>
                    <DocumentArrowUpIcon className="w-5 h-5 mr-2" />
                    Iniciar Importación
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {currentStep === 'results' && importResult && (
        <div className="space-y-6">
          {/* Resumen de resultados */}
          <div className="card">
            <div className="card-body">
              <div className="flex items-center justify-between mb-6">
                <h3 className="text-lg font-semibold text-secondary-900 dark:text-white">
                  Resultados de la Importación
                </h3>
                <div className={cn(
                  'badge text-lg px-4 py-2',
                  importResult.status === 'completed' ? 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200' :
                  importResult.status === 'failed' ? 'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200' :
                  'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200'
                )}>
                  {importResult.status === 'completed' ? 'Completada' :
                   importResult.status === 'failed' ? 'Fallida' : 'Procesando'}
                </div>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <div className="text-center p-4 bg-blue-50 dark:bg-blue-900 rounded-lg">
                  <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    {importResult.total_rows}
                  </div>
                  <div className="text-sm text-blue-700 dark:text-blue-300">
                    Total procesadas
                  </div>
                </div>
                <div className="text-center p-4 bg-success-50 dark:bg-success-900 rounded-lg">
                  <div className="text-2xl font-bold text-success-600 dark:text-success-400">
                    {importResult.imported_rows}
                  </div>
                  <div className="text-sm text-success-700 dark:text-success-300">
                    Importadas
                  </div>
                </div>
                <div className="text-center p-4 bg-danger-50 dark:bg-danger-900 rounded-lg">
                  <div className="text-2xl font-bold text-danger-600 dark:text-danger-400">
                    {importResult.failed_rows}
                  </div>
                  <div className="text-sm text-danger-700 dark:text-danger-300">
                    Fallidas
                  </div>
                </div>
                <div className="text-center p-4 bg-warning-50 dark:bg-warning-900 rounded-lg">
                  <div className="text-2xl font-bold text-warning-600 dark:text-warning-400">
                    {importResult.duplicate_rows || 0}
                  </div>
                  <div className="text-sm text-warning-700 dark:text-warning-300">
                    Duplicadas
                  </div>
                </div>
                <div className="text-center p-4 bg-purple-50 dark:bg-purple-900 rounded-lg">
                  <div className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                    {importResult.processing_time || '00:00:00'}
                  </div>
                  <div className="text-sm text-purple-700 dark:text-purple-300">
                    Tiempo total
                  </div>
                </div>
              </div>
              
              {/* Errores */}
              {importResult.errors && importResult.errors.length > 0 && (
                <div className="mb-6">
                  <h4 className="font-medium text-danger-900 dark:text-danger-100 mb-3 flex items-center">
                    <XCircleIcon className="w-5 h-5 mr-2" />
                    Errores Encontrados ({importResult.errors.length})
                  </h4>
                  <div className="space-y-2 max-h-60 overflow-y-auto">
                    {importResult.errors.map((error, index) => (
                      <div key={index} className="p-3 bg-danger-50 dark:bg-danger-900 border border-danger-200 dark:border-danger-700 rounded-lg text-sm">
                        <div className="font-medium text-danger-800 dark:text-danger-200">
                          Fila {error.row}, Columna {error.column} ({error.field})
                        </div>
                        <div className="text-danger-700 dark:text-danger-300">
                          Valor: "{error.value}" - {error.error}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
              
              {/* Advertencias */}
              {importResult.warnings && importResult.warnings.length > 0 && (
                <div className="mb-6">
                  <h4 className="font-medium text-warning-900 dark:text-warning-100 mb-3 flex items-center">
                    <ExclamationTriangleIcon className="w-5 h-5 mr-2" />
                    Advertencias ({importResult.warnings.length})
                  </h4>
                  <div className="space-y-2 max-h-40 overflow-y-auto">
                    {importResult.warnings.map((warning, index) => (
                      <div key={index} className="p-3 bg-warning-50 dark:bg-warning-900 border border-warning-200 dark:border-warning-700 rounded-lg text-sm">
                        <div className="font-medium text-warning-800 dark:text-warning-200">
                          Fila {warning.row}, Columna {warning.column} ({warning.field})
                        </div>
                        <div className="text-warning-700 dark:text-warning-300">
                          Valor: "{warning.value}" - {warning.warning}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
              
              <div className="flex justify-between">
                <button
                  onClick={resetImport}
                  className="btn-primary"
                >
                  Nueva Importación
                </button>
                
                <button
                  onClick={() => window.location.href = '/leads'}
                  className="btn-secondary"
                >
                  Ver Leads Importados
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal de historial */}
      {showHistoryModal && (
        <ImportHistoryModal
          isOpen={showHistoryModal}
          onClose={() => setShowHistoryModal(false)}
          importHistory={importHistory?.data || []}
          onViewDetails={() => {
            setShowHistoryModal(false)
          }}
        />
      )}
    </div>
  )
}

interface ImportHistoryModalProps {
  isOpen: boolean
  onClose: () => void
  importHistory: ImportRecord[]
  onViewDetails: (record: ImportRecord) => void
}

// Componente Modal de historial
function ImportHistoryModal({ isOpen, onClose, importHistory, onViewDetails }: ImportHistoryModalProps) {
  if (!isOpen) return null

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('es-ES', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  // Legacy function - now works with dynamic states
  const getStatusBadge = (status: string) => {
    // Default color mapping for import statuses
    const getStatusColor = (stateName: string) => {
      const lowerStatus = stateName.toLowerCase()
      if (lowerStatus.includes('completada') || lowerStatus.includes('completed')) {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
      }
      if (lowerStatus.includes('procesando') || lowerStatus.includes('processing')) {
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
      }
      if (lowerStatus.includes('fallida') || lowerStatus.includes('failed')) {
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
      }
      // Default color for unknown states
      return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
    }

    return (
      <span className={cn('badge', getStatusColor(status))}>
        {status}
      </span>
    )
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-secondary-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[80vh] overflow-y-auto">
        <div className="flex justify-between items-center mb-6">
          <h3 className="text-xl font-semibold text-secondary-900 dark:text-white">
            Historial de Importaciones
          </h3>
          <button onClick={onClose} className="text-secondary-400 hover:text-secondary-600">
            <XCircleIcon className="w-6 h-6" />
          </button>
        </div>

        <div className="space-y-4">
          {importHistory.map((record) => (
            <div key={record.id} className="border border-secondary-200 dark:border-secondary-700 rounded-lg p-4">
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center space-x-3">
                  <DocumentTextIcon className="w-5 h-5 text-secondary-400" />
                  <div>
                    <div className="font-medium text-secondary-900 dark:text-white">
                      {record.filename}
                    </div>
                    <div className="text-sm text-secondary-500 dark:text-secondary-400">
                      {formatDate(record.created_at)} por {record.created_by}
                    </div>
                  </div>
                </div>
                <div className="flex items-center space-x-3">
                  {getStatusBadge(record.status)}
                  <button
                    onClick={() => onViewDetails(record)}
                    className="text-primary-600 hover:text-primary-900 dark:text-primary-400"
                    title="Ver detalles"
                  >
                    <EyeIcon className="w-5 h-5" />
                  </button>
                </div>
              </div>
              
              <div className="grid grid-cols-4 gap-4 text-sm">
                <div className="text-center">
                  <div className="font-medium text-secondary-900 dark:text-white">
                    {record.total_rows}
                  </div>
                  <div className="text-secondary-500 dark:text-secondary-400">
                    Total
                  </div>
                </div>
                <div className="text-center">
                  <div className="font-medium text-success-600 dark:text-success-400">
                    {record.imported_rows}
                  </div>
                  <div className="text-secondary-500 dark:text-secondary-400">
                    Importadas
                  </div>
                </div>
                <div className="text-center">
                  <div className="font-medium text-danger-600 dark:text-danger-400">
                    {record.failed_rows}
                  </div>
                  <div className="text-secondary-500 dark:text-secondary-400">
                    Fallidas
                  </div>
                </div>
                <div className="text-center">
                  <div className="font-medium text-secondary-900 dark:text-white">
                    {((record.imported_rows / record.total_rows) * 100).toFixed(1)}%
                  </div>
                  <div className="text-secondary-500 dark:text-secondary-400">
                    Éxito
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>

        {importHistory.length === 0 && (
          <div className="text-center py-8">
            <DocumentTextIcon className="w-12 h-12 text-secondary-400 mx-auto mb-4" />
            <p className="text-secondary-500 dark:text-secondary-400">
              No hay importaciones previas
            </p>
          </div>
        )}
      </div>
    </div>
  )
}
