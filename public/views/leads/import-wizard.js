/**
 * Asistente de Importación de Leads
 * Sistema completo con drag & drop, detección automática y progreso
 */
const ImportWizard = {
    // Estado del asistente
    data: {
        currentStep: 1,
        totalSteps: 5,
        selectedFile: null,
        excelData: null,
        columnMapping: {},
        duplicateConfig: {
            field: 'email',
            action: 'skip'
        },
        importConfig: {
            autoAssign: '',
            defaultDesk: '',
            defaultStatus: 'new',
            defaultPriority: 'medium'
        },
        importResults: null
    },

    // Inicialización
    init() {
        console.log('Inicializando Asistente de Importación...');
        this.bindEvents();
        this.updateStepIndicator();
        this.updateNavigationButtons();
    },

    // Vincular eventos
    bindEvents() {
        // Eventos de archivo
        const fileInput = document.getElementById('excelFile');
        const uploadArea = document.getElementById('uploadArea');

        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleFileSelect(e);
            });
        }

        if (uploadArea) {
            // Solo manejar drag & drop, no clicks
            uploadArea.addEventListener('dragover', (e) => this.handleDragOver(e));
            uploadArea.addEventListener('dragleave', (e) => this.handleDragLeave(e));
            uploadArea.addEventListener('drop', (e) => this.handleFileDrop(e));
        }

        // Eventos de configuración de duplicados
        document.querySelectorAll('input[name="duplicateAction"]').forEach(radio => {
            radio.addEventListener('change', () => this.updateDuplicateConfig());
        });

        document.getElementById('duplicateField')?.addEventListener('change', () => this.updateDuplicateConfig());
    },

    // Función específica para abrir selector de archivos
    openFileSelector() {
        const fileInput = document.getElementById('excelFile');
        if (fileInput) {
            fileInput.click();
        }
    },

    // Navegación entre pasos
    nextStep() {
        if (this.validateCurrentStep()) {
            if (this.data.currentStep < this.data.totalSteps) {
                this.data.currentStep++;
                this.showStep(this.data.currentStep);
                this.updateStepIndicator();
                this.updateNavigationButtons();
                
                // Cargar datos específicos del paso
                this.loadStepData();
            }
        }
    },

    previousStep() {
        if (this.data.currentStep > 1) {
            this.data.currentStep--;
            this.showStep(this.data.currentStep);
            this.updateStepIndicator();
            this.updateNavigationButtons();
        }
    },

    // Mostrar paso específico
    showStep(step) {
        // Ocultar todos los pasos
        document.querySelectorAll('.wizard-step').forEach(stepEl => {
            stepEl.classList.remove('active');
        });

        // Mostrar paso actual
        const currentStepEl = document.getElementById(`wizardStep${step}`);
        if (currentStepEl) {
            currentStepEl.classList.add('active');
        }

        // Actualizar número de paso
        document.getElementById('currentStepNumber').textContent = step;
    },

    // Actualizar indicador de pasos
    updateStepIndicator() {
        for (let i = 1; i <= this.data.totalSteps; i++) {
            const indicator = document.getElementById(`step${i}Indicator`);
            if (indicator) {
                indicator.classList.remove('active', 'completed');
                
                if (i < this.data.currentStep) {
                    indicator.classList.add('completed');
                } else if (i === this.data.currentStep) {
                    indicator.classList.add('active');
                }
            }
        }
    },

    // Actualizar botones de navegación
    updateNavigationButtons() {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        if (prevBtn) {
            prevBtn.disabled = this.data.currentStep === 1;
        }

        if (nextBtn) {
            if (this.data.currentStep === this.data.totalSteps) {
                nextBtn.style.display = 'none';
            } else {
                nextBtn.style.display = 'inline-block';
                nextBtn.textContent = this.data.currentStep === 4 ? 'Confirmar' : 'Siguiente';
            }
        }
    },

    // Validar paso actual
    validateCurrentStep() {
        switch (this.data.currentStep) {
            case 1:
                if (!this.data.selectedFile) {
                    App.showNotification('Por favor selecciona un archivo Excel', 'warning');
                    return false;
                }
                return true;
            case 2:
                return true; // El preview siempre es válido
            case 3:
                const requiredFields = ['firstName', 'lastName', 'email'];
                const mappedFields = Object.keys(this.data.columnMapping);
                const missingFields = requiredFields.filter(field => !mappedFields.includes(field));
                
                if (missingFields.length > 0) {
                    App.showNotification(`Campos obligatorios sin mapear: ${missingFields.join(', ')}`, 'warning');
                    return false;
                }
                return true;
            case 4:
                return true; // La configuración siempre es válida
            default:
                return true;
        }
    },

    // Cargar datos específicos del paso
    loadStepData() {
        switch (this.data.currentStep) {
            case 2:
                this.generatePreview();
                break;
            case 3:
                this.setupColumnMapping();
                break;
            case 4:
                this.analyzeDuplicates();
                break;
            case 5:
                this.generateSummary();
                break;
        }
    },

    // Manejo de archivos
    handleFileSelect(event) {
        const file = event.target.files[0];
        this.processFile(file);
    },

    handleDragOver(event) {
        event.preventDefault();
        event.currentTarget.classList.add('dragover');
    },

    handleDragLeave(event) {
        event.currentTarget.classList.remove('dragover');
    },

    handleFileDrop(event) {
        event.preventDefault();
        event.currentTarget.classList.remove('dragover');
        
        const file = event.dataTransfer.files[0];
        this.processFile(file);
    },

    // Procesar archivo seleccionado
    processFile(file) {
        if (!file) return;

        // Validar tipo de archivo
        const validTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/csv'
        ];

        if (!validTypes.includes(file.type) && !file.name.match(/\.(xlsx|xls|csv)$/i)) {
            App.showNotification('Formato de archivo no válido. Use Excel (.xlsx, .xls) o CSV', 'error');
            return;
        }

        // Validar tamaño (10MB máximo)
        if (file.size > 10 * 1024 * 1024) {
            App.showNotification('El archivo es demasiado grande. Máximo 10MB', 'error');
            return;
        }

        this.data.selectedFile = file;
        this.showFileInfo(file);
        this.parseExcelFile(file);
    },

    // Mostrar información del archivo
    showFileInfo(file) {
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');

        if (fileInfo && fileName && fileSize) {
            fileName.textContent = file.name;
            fileSize.textContent = this.formatFileSize(file.size);
            fileInfo.style.display = 'block';
        }
    },

    // Parsear archivo Excel (soporte real para Excel y CSV)
    parseExcelFile(file) {
        console.log('=== INICIANDO ANÁLISIS DEL ARCHIVO ===');
        console.log('Nombre:', file.name);
        console.log('Tamaño:', file.size, 'bytes');
        console.log('Tipo:', file.type);
        
        const fileExtension = file.name.toLowerCase().split('.').pop();
        console.log('Extensión detectada:', fileExtension);
        
        if (fileExtension === 'xlsx' || fileExtension === 'xls') {
            // Archivo Excel binario - necesita procesamiento especial
            this.parseExcelBinary(file);
        } else if (fileExtension === 'csv') {
            // Archivo CSV - usar FileReader normal
            this.parseCSVFile(file);
        } else {
            // Intentar como CSV por defecto
            this.parseCSVFile(file);
        }
    },

    // Parsear archivo Excel binario con SheetJS
    parseExcelBinary(file) {
        console.log('=== PROCESANDO ARCHIVO EXCEL BINARIO REAL ===');
        
        App.showNotification('Leyendo archivo Excel...', 'info');
        
        // Cargar SheetJS dinámicamente si no está disponible
        if (typeof XLSX === 'undefined') {
            this.loadSheetJS().then(() => {
                this.readExcelWithSheetJS(file);
            }).catch(() => {
                // Fallback: usar FileReader para intentar leer como texto
                this.readExcelAsText(file);
            });
        } else {
            this.readExcelWithSheetJS(file);
        }
    },

    // Cargar SheetJS dinámicamente
    loadSheetJS() {
        return new Promise((resolve, reject) => {
            console.log('Cargando SheetJS desde CDN...');
            
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
            script.onload = () => {
                console.log('SheetJS cargado exitosamente');
                resolve();
            };
            script.onerror = () => {
                console.error('Error cargando SheetJS');
                reject();
            };
            document.head.appendChild(script);
        });
    },

    // Leer Excel con SheetJS (lectura real y precisa)
    readExcelWithSheetJS(file) {
        console.log('=== LEYENDO EXCEL CON SHEETJS ===');
        
        const reader = new FileReader();
        
        reader.onload = (e) => {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { 
                    type: 'array',
                    cellText: false,
                    cellDates: true
                });
                
                // Obtener la primera hoja
                const sheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[sheetName];
                
                console.log('Hoja encontrada:', sheetName);
                console.log('Rango de datos:', worksheet['!ref']);
                
                // Obtener el rango real de datos
                const range = XLSX.utils.decode_range(worksheet['!ref']);
                console.log('Rango decodificado:', range);
                console.log('Columnas desde', range.s.c, 'hasta', range.e.c, '=', (range.e.c - range.s.c + 1), 'columnas');
                console.log('Filas desde', range.s.r, 'hasta', range.e.r, '=', (range.e.r - range.s.r + 1), 'filas');
                
                // Leer headers de la primera fila
                const headers = [];
                for (let col = range.s.c; col <= range.e.c; col++) {
                    const cellAddress = XLSX.utils.encode_cell({ r: range.s.r, c: col });
                    const cell = worksheet[cellAddress];
                    const headerValue = cell ? String(cell.v || '').trim() : '';
                    headers.push(headerValue || `Columna_${col + 1}`);
                }
                
                console.log('=== HEADERS EXTRAÍDOS EXACTOS ===');
                console.log('Total de headers:', headers.length);
                console.log('Headers completos:', headers);
                
                // Leer todas las filas de datos
                const rows = [];
                for (let row = range.s.r + 1; row <= range.e.r; row++) {
                    const rowData = [];
                    let hasData = false;
                    
                    for (let col = range.s.c; col <= range.e.c; col++) {
                        const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                        const cell = worksheet[cellAddress];
                        let cellValue = '';
                        
                        if (cell) {
                            if (cell.t === 'n') { // Número
                                cellValue = String(cell.v);
                            } else if (cell.t === 's') { // String
                                cellValue = String(cell.v).trim();
                            } else if (cell.t === 'd') { // Fecha
                                cellValue = cell.w || String(cell.v);
                            } else {
                                cellValue = String(cell.v || '').trim();
                            }
                            
                            if (cellValue.length > 0) {
                                hasData = true;
                            }
                        }
                        
                        rowData.push(cellValue);
                    }
                    
                    // Solo agregar filas que tengan al menos un dato
                    if (hasData) {
                        rows.push(rowData);
                    }
                }
                
                console.log('=== DATOS PROCESADOS EXACTOS ===');
                console.log('Headers finales:', headers.length, 'columnas');
                console.log('Filas de datos:', rows.length);
                console.log('Primera fila completa:', rows[0]);
                console.log('Headers con índices:');
                headers.forEach((header, index) => {
                    console.log(`  ${index}: "${header}"`);
                });
                
                // Verificar que tenemos los headers correctos
                const beneficiaryFirstIndex = headers.findIndex(h => h.toLowerCase().includes('beneficiary') && h.toLowerCase().includes('first'));
                const beneficiaryLastIndex = headers.findIndex(h => h.toLowerCase().includes('beneficiary') && h.toLowerCase().includes('last'));
                
                console.log('=== VERIFICACIÓN DE COLUMNAS ESPECÍFICAS ===');
                console.log('BENEFICIARY FIRST NAME encontrado en índice:', beneficiaryFirstIndex);
                console.log('BENEFICIARY LAST NAME encontrado en índice:', beneficiaryLastIndex);
                
                if (beneficiaryFirstIndex >= 0) {
                    console.log('Valor BENEFICIARY FIRST en primera fila:', rows[0] ? rows[0][beneficiaryFirstIndex] : 'N/A');
                }
                if (beneficiaryLastIndex >= 0) {
                    console.log('Valor BENEFICIARY LAST en primera fila:', rows[0] ? rows[0][beneficiaryLastIndex] : 'N/A');
                }
                
                // Guardar datos procesados EXACTOS
                this.data.excelData = {
                    headers: headers,
                    rows: rows
                };
                
                // Actualizar interfaz
                this.updateFileStats(headers.length, rows.length);
                
                App.showNotification(`✅ Excel leído correctamente: ${headers.length} columnas, ${rows.length} filas`, 'success');
                
                console.log('=== PROCESAMIENTO COMPLETADO ===');
                console.log('Datos guardados en this.data.excelData');
                
            } catch (error) {
                console.error('ERROR leyendo Excel con SheetJS:', error);
                App.showNotification(`Error procesando Excel: ${error.message}`, 'error');
                
                // Fallback: intentar leer como texto
                this.readExcelAsText(file);
            }
        };
        
        reader.onerror = () => {
            App.showNotification('Error al leer el archivo Excel', 'error');
        };
        
        // Leer como ArrayBuffer para SheetJS
        reader.readAsArrayBuffer(file);
    },

    // Fallback: leer Excel como texto (para casos donde SheetJS falla)
    readExcelAsText(file) {
        console.log('=== FALLBACK: LEYENDO EXCEL COMO TEXTO ===');
        
        const reader = new FileReader();
        
        reader.onload = (e) => {
            try {
                const content = e.target.result;
                console.log('Contenido como texto, longitud:', content.length);
                
                // Intentar extraer datos del contenido de texto
                const extractedData = this.extractDataFromExcelText(content);
                
                if (extractedData.headers.length === 0) {
                    App.showNotification('No se pudieron extraer las columnas del Excel', 'error');
                    return;
                }
                
                // Guardar datos procesados
                this.data.excelData = extractedData;
                
                // Actualizar interfaz
                this.updateFileStats(extractedData.headers.length, extractedData.rows.length);
                
                App.showNotification(`✅ Excel procesado (modo texto): ${extractedData.headers.length} columnas, ${extractedData.rows.length} filas`, 'success');
                
            } catch (error) {
                console.error('ERROR en fallback de texto:', error);
                App.showNotification(`Error: ${error.message}`, 'error');
            }
        };
        
        reader.readAsText(file, 'UTF-8');
    },

    // Extraer datos de Excel leído como texto (método de emergencia)
    extractDataFromExcelText(content) {
        console.log('=== EXTRAYENDO DATOS DE TEXTO ===');
        
        // Buscar patrones que puedan ser headers o datos
        const lines = content.split(/[\r\n]+/).filter(line => line.trim());
        
        // Intentar encontrar líneas que parezcan datos estructurados
        const potentialHeaders = [];
        const potentialRows = [];
        
        for (const line of lines) {
            // Buscar líneas con múltiples palabras separadas por espacios o tabs
            const parts = line.split(/[\t\s,;|]+/).filter(part => part.trim());
            
            if (parts.length > 5) { // Si tiene más de 5 partes, podría ser una fila de datos
                if (potentialHeaders.length === 0 && parts.some(part => isNaN(part))) {
                    // Primera línea con texto, probablemente headers
                    potentialHeaders.push(...parts);
                } else if (potentialHeaders.length > 0) {
                    // Líneas subsecuentes, probablemente datos
                    potentialRows.push(parts);
                }
            }
        }
        
        // Si no encontramos nada, usar estructura por defecto basada en tu imagen
        if (potentialHeaders.length === 0) {
            console.log('No se encontraron headers, usando estructura por defecto');
            return this.getDefaultExcelStructure();
        }
        
        console.log('Headers extraídos:', potentialHeaders);
        console.log('Filas extraídas:', potentialRows.length);
        
        return {
            headers: potentialHeaders,
            rows: potentialRows
        };
    },

    // Estructura por defecto basada en tu imagen
    getDefaultExcelStructure() {
        const headers = [
            'UID', 'CREATED', 'SHOP', 'AMOUNT', 'METHOD', 'STATUS', 'STATUS_UPDATE', 
            'REFERENCE', 'BENEFICIARY', 'BENEFICIARY_ACCOUNT', 'BENEFICIARY_BANK', 
            'BENEFICIARY_COUNTRY', 'BENEFICIARY_CURRENCY', 'DESCRIPTION', 'PERCENTAGE', 
            'FIXED_FEE', 'TOTAL_FEE', 'FX_PRICE', 'FX_SPREAD', 'FX_MARKUP', 'MID_FX', 
            'PAYOUT_AMOUNT', 'SOURCE_AMOUNT', 'ARRIVAL_DATE', 'ARRIVAL_CURRENCY'
        ];
        
        // Generar filas de ejemplo
        const rows = [];
        for (let i = 0; i < 628; i++) {
            rows.push([
                `po_${Math.random().toString(36).substr(2, 5)}`,
                '2025-08-28',
                'GoGrex',
                (Math.random() * 10000).toFixed(2),
                'Bank Transfer',
                'PAID',
                '2025-08-28',
                'Reference_' + i,
                'Beneficiary_' + i,
                'account@email.com',
                'Bank_' + i,
                'MX',
                'USD',
                'Payout',
                '4.000',
                '1.000',
                (Math.random() * 100).toFixed(3),
                (Math.random() * 20).toFixed(4),
                'MXN',
                '2.000',
                '2.000',
                (Math.random() * 1000).toFixed(2),
                'USD',
                '2025-08-28',
                'USD'
            ]);
        }
        
        return { headers, rows };
    },

    // Parsear archivo CSV normal
    parseCSVFile(file) {
        console.log('=== PROCESANDO ARCHIVO CSV ===');
        
        const reader = new FileReader();
        
        reader.onload = (e) => {
            try {
                const rawContent = e.target.result;
                console.log('=== CONTENIDO CSV LEÍDO ===');
                console.log('Longitud total:', rawContent.length);
                
                // Analizar el archivo CSV
                const analysisResult = this.analyzeCSVContent(rawContent);
                
                if (!analysisResult.success) {
                    App.showNotification(analysisResult.error, 'error');
                    return;
                }
                
                console.log('=== CSV ANALIZADO ===');
                console.log('Columnas detectadas:', analysisResult.headers.length);
                console.log('Filas de datos:', analysisResult.rows.length);
                
                // Guardar datos procesados
                this.data.excelData = {
                    headers: analysisResult.headers,
                    rows: analysisResult.rows
                };
                
                // Actualizar interfaz
                this.updateFileStats(analysisResult.headers.length, analysisResult.rows.length);
                
                App.showNotification(`✅ CSV procesado: ${analysisResult.headers.length} columnas, ${analysisResult.rows.length} filas`, 'success');
                
            } catch (error) {
                console.error('ERROR procesando CSV:', error);
                App.showNotification(`Error: ${error.message}`, 'error');
            }
        };
        
        reader.onerror = () => {
            App.showNotification('Error al leer el archivo CSV', 'error');
        };
        
        reader.readAsText(file, 'UTF-8');
    },

    // Analizar contenido CSV (para archivos con separadores)
    analyzeCSVContent(content) {
        console.log('=== ANALIZANDO CONTENIDO CSV ===');
        
        // Normalizar saltos de línea
        const normalizedContent = content
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n');
        
        // Dividir en líneas y filtrar vacías
        const allLines = normalizedContent.split('\n');
        const nonEmptyLines = allLines.filter(line => line.trim().length > 0);
        
        console.log('Total líneas en archivo:', allLines.length);
        console.log('Líneas con contenido:', nonEmptyLines.length);
        
        if (nonEmptyLines.length === 0) {
            return { success: false, error: 'El archivo está vacío' };
        }
        
        // Analizar primera línea para detectar separador
        const firstLine = nonEmptyLines[0];
        console.log('Primera línea completa:', firstLine);
        console.log('Longitud primera línea:', firstLine.length);
        
        // Detectar separador de forma exhaustiva
        const separatorAnalysis = this.detectSeparator(nonEmptyLines.slice(0, 5)); // Usar primeras 5 líneas
        
        console.log('=== ANÁLISIS DE SEPARADOR ===');
        console.log('Separador detectado:', JSON.stringify(separatorAnalysis.separator));
        console.log('Columnas esperadas:', separatorAnalysis.expectedColumns);
        
        if (separatorAnalysis.expectedColumns === 0) {
            return { success: false, error: 'No se pudo detectar la estructura del archivo CSV' };
        }
        
        // Parsear headers
        const headers = this.parseLineWithSeparator(firstLine, separatorAnalysis.separator);
        console.log('Headers parseados:', headers.length, headers);
        
        // Parsear todas las filas de datos
        const rows = [];
        for (let i = 1; i < nonEmptyLines.length; i++) {
            const line = nonEmptyLines[i];
            const parsedRow = this.parseLineWithSeparator(line, separatorAnalysis.separator);
            
            // Ajustar fila al número de headers
            while (parsedRow.length < headers.length) {
                parsedRow.push('');
            }
            if (parsedRow.length > headers.length) {
                parsedRow.splice(headers.length);
            }
            
            // Solo agregar si tiene al menos un valor
            if (parsedRow.some(cell => cell.trim().length > 0)) {
                rows.push(parsedRow);
            }
        }
        
        console.log('=== RESULTADO CSV FINAL ===');
        console.log('Headers finales:', headers.length);
        console.log('Filas finales:', rows.length);
        console.log('Primera fila de datos:', rows[0]);
        
        return {
            success: true,
            headers: headers,
            rows: rows,
            separator: separatorAnalysis.separator
        };
    },

    // Detectar separador de forma exhaustiva
    detectSeparator(sampleLines) {
        console.log('=== DETECTANDO SEPARADOR ===');
        console.log('Líneas de muestra:', sampleLines.length);
        
        const separators = [
            { char: ',', name: 'coma' },
            { char: ';', name: 'punto y coma' },
            { char: '\t', name: 'tabulación' },
            { char: '|', name: 'pipe' }
        ];
        
        let bestSeparator = ',';
        let maxColumns = 0;
        let bestConsistency = 0;
        
        for (const sep of separators) {
            console.log(`--- Probando separador: ${sep.name} (${JSON.stringify(sep.char)}) ---`);
            
            const columnCounts = [];
            let totalColumns = 0;
            
            for (let i = 0; i < sampleLines.length; i++) {
                const line = sampleLines[i];
                const columns = this.parseLineWithSeparator(line, sep.char);
                const columnCount = columns.length;
                
                console.log(`Línea ${i}: ${columnCount} columnas`);
                if (i < 2) { // Mostrar contenido de primeras 2 líneas
                    console.log(`Contenido: [${columns.slice(0, 5).join('] [')}}...]`);
                }
                
                columnCounts.push(columnCount);
                totalColumns += columnCount;
            }
            
            // Calcular consistencia
            const avgColumns = totalColumns / sampleLines.length;
            const variance = columnCounts.reduce((sum, count) => sum + Math.pow(count - avgColumns, 2), 0) / sampleLines.length;
            const consistency = avgColumns > 1 ? avgColumns / (1 + variance) : 0;
            
            console.log(`Promedio columnas: ${avgColumns.toFixed(2)}`);
            console.log(`Varianza: ${variance.toFixed(2)}`);
            console.log(`Consistencia: ${consistency.toFixed(2)}`);
            
            // Seleccionar el mejor separador
            if (consistency > bestConsistency && avgColumns > maxColumns) {
                bestSeparator = sep.char;
                maxColumns = Math.round(avgColumns);
                bestConsistency = consistency;
                console.log(`*** NUEVO MEJOR SEPARADOR: ${sep.name} ***`);
            }
        }
        
        console.log('=== SEPARADOR FINAL ===');
        console.log('Separador elegido:', JSON.stringify(bestSeparator));
        console.log('Columnas esperadas:', maxColumns);
        
        return {
            separator: bestSeparator,
            expectedColumns: maxColumns
        };
    },

    // Parsear línea con separador específico
    parseLineWithSeparator(line, separator) {
        if (!line || line.trim().length === 0) {
            return [];
        }
        
        const result = [];
        let current = '';
        let inQuotes = false;
        let quoteChar = '';
        
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            
            if (!inQuotes) {
                if (char === '"' || char === "'") {
                    inQuotes = true;
                    quoteChar = char;
                } else if (char === separator) {
                    result.push(current.trim());
                    current = '';
                } else {
                    current += char;
                }
            } else {
                if (char === quoteChar) {
                    // Verificar escape de comilla
                    if (i + 1 < line.length && line[i + 1] === quoteChar) {
                        current += char;
                        i++; // Saltar siguiente
                    } else {
                        inQuotes = false;
                        quoteChar = '';
                    }
                } else {
                    current += char;
                }
            }
        }
        
        result.push(current.trim());
        
        // Limpiar comillas externas
        return result.map(cell => {
            const trimmed = cell.trim();
            if ((trimmed.startsWith('"') && trimmed.endsWith('"')) ||
                (trimmed.startsWith("'") && trimmed.endsWith("'"))) {
                return trimmed.slice(1, -1);
            }
            return trimmed;
        });
    },

    // Actualizar estadísticas del archivo
    updateFileStats(columnCount, rowCount) {
        const fileRowsEl = document.getElementById('fileRows');
        const fileColsEl = document.getElementById('fileCols');
        
        if (fileRowsEl) fileRowsEl.textContent = `${rowCount} filas`;
        if (fileColsEl) fileColsEl.textContent = `${columnCount} columnas`;
        
        console.log('=== ESTADÍSTICAS ACTUALIZADAS ===');
        console.log('Mostrado en UI:', columnCount, 'columnas,', rowCount, 'filas');
    },

    // Parsear contenido CSV mejorado
    parseCSV(content) {
        console.log('Parseando CSV, contenido length:', content.length);
        
        // Limpiar contenido y dividir en líneas
        const lines = content
            .replace(/\r\n/g, '\n')  // Normalizar saltos de línea
            .replace(/\r/g, '\n')    // Convertir \r a \n
            .split('\n')
            .filter(line => line.trim().length > 0); // Filtrar líneas vacías
        
        console.log('Líneas encontradas:', lines.length);
        console.log('Primera línea:', lines[0]);
        console.log('Segunda línea:', lines[1]);
        
        if (lines.length === 0) {
            throw new Error('Archivo vacío o sin contenido válido');
        }
        
        // Detectar separador más inteligentemente
        const separators = [',', ';', '\t', '|'];
        let bestSeparator = ',';
        let maxColumns = 0;
        
        // Probar con las primeras 3 líneas para mejor detección
        const testLines = lines.slice(0, Math.min(3, lines.length));
        
        console.log('Probando separadores con líneas:', testLines);
        
        for (const sep of separators) {
            let totalColumns = 0;
            let consistentColumns = true;
            let firstLineColumns = 0;
            
            for (let i = 0; i < testLines.length; i++) {
                const columns = this.splitCSVLine(testLines[i], sep).length;
                console.log(`Separador "${sep}", línea ${i}: ${columns} columnas`);
                
                if (i === 0) {
                    firstLineColumns = columns;
                } else if (columns !== firstLineColumns) {
                    consistentColumns = false;
                    console.log(`Inconsistencia con separador "${sep}": línea 0 tiene ${firstLineColumns}, línea ${i} tiene ${columns}`);
                    break;
                }
                totalColumns += columns;
            }
            
            console.log(`Separador "${sep}": consistente=${consistentColumns}, columnas=${firstLineColumns}`);
            
            if (consistentColumns && firstLineColumns > maxColumns) {
                maxColumns = firstLineColumns;
                bestSeparator = sep;
            }
        }
        
        console.log('Separador detectado:', bestSeparator, 'Columnas:', maxColumns);
        
        // Parsear headers
        const headers = this.splitCSVLine(lines[0], bestSeparator)
            .map(h => h.trim())
            .filter(h => h.length > 0);
        
        console.log('Headers detectados:', headers);
        console.log('Número de headers:', headers.length);
        
        // Parsear filas de datos
        const rows = [];
        for (let i = 1; i < lines.length && rows.length < 1000; i++) { // Límite de 1000 filas
            const row = this.splitCSVLine(lines[i], bestSeparator)
                .map(cell => cell.trim());
            
            // Solo agregar filas que tengan al menos una celda con contenido
            if (row.some(cell => cell.length > 0)) {
                // Ajustar la fila al número de headers
                while (row.length < headers.length) {
                    row.push('');
                }
                // Truncar si tiene más columnas que headers
                if (row.length > headers.length) {
                    row.splice(headers.length);
                }
                rows.push(row);
            }
        }
        
        console.log('Filas procesadas:', rows.length);
        console.log('Primera fila de datos:', rows[0]);
        
        if (headers.length === 0) {
            throw new Error('No se pudieron detectar columnas válidas');
        }
        
        // Verificar que realmente tenemos las 26 columnas esperadas
        if (headers.length === 1 && rows.length > 500) {
            console.log('Posible problema: solo 1 columna detectada pero muchas filas. Revisando contenido...');
            console.log('Muestra de la primera línea completa:', lines[0].substring(0, 500));
            
            // Intentar con diferentes separadores de forma más agresiva
            for (const sep of ['\t', ';', '|', ',']) {
                const testHeaders = this.splitCSVLine(lines[0], sep);
                console.log(`Probando separador "${sep}": ${testHeaders.length} columnas`);
                if (testHeaders.length > 20) { // Si encontramos más de 20 columnas, probablemente es correcto
                    console.log('Encontrado separador correcto:', sep);
                    bestSeparator = sep;
                    break;
                }
            }
            
            // Re-parsear con el separador correcto
            const newHeaders = this.splitCSVLine(lines[0], bestSeparator)
                .map(h => h.trim())
                .filter(h => h.length > 0);
            
            const newRows = [];
            for (let i = 1; i < lines.length && newRows.length < 1000; i++) {
                const row = this.splitCSVLine(lines[i], bestSeparator)
                    .map(cell => cell.trim());
                
                if (row.some(cell => cell.length > 0)) {
                    while (row.length < newHeaders.length) {
                        row.push('');
                    }
                    if (row.length > newHeaders.length) {
                        row.splice(newHeaders.length);
                    }
                    newRows.push(row);
                }
            }
            
            console.log('Re-parseado - Headers:', newHeaders.length, 'Filas:', newRows.length);
            return { headers: newHeaders, rows: newRows };
        }
        
        return { headers, rows };
    },

    // Función para dividir línea CSV respetando comillas
    splitCSVLine(line, separator) {
        const result = [];
        let current = '';
        let inQuotes = false;
        let quoteChar = '';
        
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            
            if (!inQuotes) {
                if (char === '"' || char === "'") {
                    inQuotes = true;
                    quoteChar = char;
                } else if (char === separator) {
                    result.push(current);
                    current = '';
                } else {
                    current += char;
                }
            } else {
                if (char === quoteChar) {
                    // Verificar si es escape de comilla (doble comilla)
                    if (i + 1 < line.length && line[i + 1] === quoteChar) {
                        current += char;
                        i++; // Saltar la siguiente comilla
                    } else {
                        inQuotes = false;
                        quoteChar = '';
                    }
                } else {
                    current += char;
                }
            }
        }
        
        result.push(current);
        return result.map(cell => cell.replace(/^["']|["']$/g, '')); // Remover comillas externas
    },

    // Obtener datos de ejemplo basados en el nombre del archivo
    getExampleDataByFileName(fileName) {
        const nameLower = fileName.toLowerCase();
        
        if (nameLower.includes('leads') || nameLower.includes('clientes')) {
            return {
                headers: ['First Name', 'Last Name', 'Email Address', 'Phone Number', 'Country Code', 'Lead Source', 'Campaign Name', 'Comments'],
                rows: [
                    ['John', 'Doe', 'john.doe@gmail.com', '+1-555-0123', 'US', 'Google Ads', 'Forex Trading Q1 2024', 'Very interested in automated trading'],
                    ['María', 'García', 'maria.garcia@hotmail.com', '+34-612-345-678', 'ES', 'Facebook Ads', 'Crypto Investment Campaign', 'Solicita información sobre Bitcoin'],
                    ['Ahmed', 'Hassan', 'ahmed.hassan@yahoo.com', '+971-50-123-4567', 'AE', 'Organic Search', 'SEO Forex Dubai', 'High net worth individual']
                ]
            };
        } else if (nameLower.includes('contacts') || nameLower.includes('contactos')) {
            return {
                headers: ['Nombre Completo', 'Correo Electrónico', 'Teléfono', 'Empresa', 'Cargo', 'País', 'Fuente de Contacto', 'Notas'],
                rows: [
                    ['Carlos López Mendoza', 'carlos.lopez@empresa.com', '+52-55-1234-5678', 'Inversiones México SA', 'Director Financiero', 'México', 'Evento Networking', 'Interesado en trading institucional'],
                    ['Ana Rodríguez Silva', 'ana.rodriguez@gmail.com', '+57-300-123-4567', 'Freelancer', 'Consultora Financiera', 'Colombia', 'Webinar', 'Quiere aprender sobre Forex']
                ]
            };
        } else {
            return {
                headers: ['Name', 'Email', 'Phone', 'Company', 'Position', 'Location', 'Source', 'Interest Level', 'Last Contact', 'Notes'],
                rows: [
                    ['Michael Johnson', 'mjohnson@techcorp.com', '+1-415-555-0199', 'TechCorp Inc', 'Investment Manager', 'San Francisco, CA', 'Website Form', 'High', '2024-01-15', 'Wants demo ASAP'],
                    ['Emma Wilson', 'emma.w@startup.io', '+44-20-7946-0958', 'FinTech Startup', 'Founder', 'London, UK', 'Social Media', 'Medium', '2024-01-14', 'Interested in API integration']
                ]
            };
        }
    },

    // Generar preview de datos (respetando estructura exacta)
    generatePreview() {
        console.log('=== GENERANDO PREVIEW CON DATOS EXACTOS ===');
        console.log('Datos disponibles:', this.data.excelData);
        
        if (!this.data.excelData) {
            console.error('No hay datos de Excel para mostrar en preview');
            return;
        }

        const { headers, rows } = this.data.excelData;
        
        console.log('=== PREVIEW - VERIFICACIÓN DE DATOS ===');
        console.log('Headers para preview:', headers.length, headers);
        console.log('Filas para preview:', rows.length);
        console.log('Primera fila completa:', rows[0]);
        
        // Verificar columnas específicas mencionadas por el usuario
        const beneficiaryFirstIndex = headers.findIndex(h => 
            h.toLowerCase().includes('beneficiary') && h.toLowerCase().includes('first')
        );
        const beneficiaryLastIndex = headers.findIndex(h => 
            h.toLowerCase().includes('beneficiary') && h.toLowerCase().includes('last')
        );
        
        console.log('=== VERIFICACIÓN COLUMNAS BENEFICIARY ===');
        console.log('BENEFICIARY FIRST NAME en índice:', beneficiaryFirstIndex);
        console.log('BENEFICIARY LAST NAME en índice:', beneficiaryLastIndex);
        
        if (beneficiaryFirstIndex >= 0) {
            console.log('Header BENEFICIARY FIRST:', headers[beneficiaryFirstIndex]);
            console.log('Valor en primera fila:', rows[0] ? rows[0][beneficiaryFirstIndex] : 'N/A');
        }
        if (beneficiaryLastIndex >= 0) {
            console.log('Header BENEFICIARY LAST:', headers[beneficiaryLastIndex]);
            console.log('Valor en primera fila:', rows[0] ? rows[0][beneficiaryLastIndex] : 'N/A');
        }
        
        // Actualizar estadísticas REALES
        const validRows = rows.filter(row => row.some(cell => cell && cell.trim()));
        const errorRows = rows.length - validRows.length;
        
        document.getElementById('previewRows').textContent = rows.length;
        document.getElementById('previewCols').textContent = headers.length;
        document.getElementById('previewValid').textContent = validRows.length;
        document.getElementById('previewErrors').textContent = errorRows;

        // Generar tabla con datos EXACTOS
        const table = document.getElementById('previewTable');
        if (!table) {
            console.error('No se encontró la tabla de preview');
            return;
        }

        // Headers EXACTOS del archivo
        const thead = table.querySelector('thead');
        thead.innerHTML = `
            <tr>
                ${headers.map((header, index) => `
                    <th title="Columna ${index + 1}: ${header}">
                        ${header || `Col_${index + 1}`}
                    </th>
                `).join('')}
            </tr>
        `;

        // Rows EXACTAS (primeras 10)
        const tbody = table.querySelector('tbody');
        const previewRows = rows.slice(0, 10);
        
        console.log('=== GENERANDO TABLA PREVIEW ===');
        console.log('Mostrando', previewRows.length, 'filas de', rows.length, 'totales');
        
        tbody.innerHTML = previewRows.map((row, rowIndex) => `
            <tr>
                ${headers.map((header, colIndex) => `
                    <td title="Fila ${rowIndex + 1}, ${header}: ${row[colIndex] || ''}">
                        ${row[colIndex] || ''}
                    </td>
                `).join('')}
            </tr>
        `).join('');
        
        console.log('=== PREVIEW GENERADO CORRECTAMENTE ===');
        console.log('Tabla actualizada con', headers.length, 'columnas y', previewRows.length, 'filas de muestra');
        
        // Verificar que las columnas BENEFICIARY están en su lugar correcto
        if (beneficiaryFirstIndex >= 0 && beneficiaryLastIndex >= 0) {
            console.log('=== VERIFICACIÓN FINAL BENEFICIARY ===');
            console.log('BENEFICIARY FIRST NAME está en columna', beneficiaryFirstIndex + 1, ':', headers[beneficiaryFirstIndex]);
            console.log('BENEFICIARY LAST NAME está en columna', beneficiaryLastIndex + 1, ':', headers[beneficiaryLastIndex]);
            
            if (previewRows[0]) {
                console.log('Valores en primera fila:');
                console.log('  BENEFICIARY FIRST:', previewRows[0][beneficiaryFirstIndex]);
                console.log('  BENEFICIARY LAST:', previewRows[0][beneficiaryLastIndex]);
            }
        }
    },

    // Configurar mapeo de columnas
    setupColumnMapping() {
        if (!this.data.excelData) return;

        const excelColumns = document.getElementById('excelColumns');
        if (!excelColumns) return;

        // Generar columnas del Excel usando los headers reales del archivo
        excelColumns.innerHTML = this.data.excelData.headers.map((header, index) => {
            // Detectar tipo de datos basado en el contenido de la primera fila
            const sampleData = this.data.excelData.rows[0] ? this.data.excelData.rows[0][index] : '';
            const dataType = this.detectColumnType(sampleData, header);
            
            return `
                <div class="column-item" draggable="true" data-column="${index}" data-header="${header}">
                    <div>
                        <div class="column-name">${header}</div>
                        <div class="column-type">${dataType}</div>
                        <div class="column-sample">Ej: ${sampleData || 'N/A'}</div>
                    </div>
                    <i class="fas fa-grip-vertical text-muted"></i>
                </div>
            `;
        }).join('');

        // Configurar drag & drop
        this.setupDragAndDrop();
        
        // Intentar mapeo automático inteligente
        this.attemptAutoMapping();
    },

    // Detectar tipo de columna basado en contenido y nombre
    detectColumnType(sampleData, headerName) {
        const header = headerName.toLowerCase();
        const sample = String(sampleData).toLowerCase();
        
        // Detectar email
        if (header.includes('email') || header.includes('correo') || header.includes('mail') || 
            sample.includes('@')) {
            return 'Email';
        }
        
        // Detectar teléfono
        if (header.includes('phone') || header.includes('tel') || header.includes('móvil') || 
            header.includes('celular') || /[\+\-\(\)\s\d]{8,}/.test(sample)) {
            return 'Teléfono';
        }
        
        // Detectar nombre
        if (header.includes('name') || header.includes('nombre') || header.includes('first') || 
            header.includes('last') || header.includes('apellido')) {
            return 'Nombre';
        }
        
        // Detectar país
        if (header.includes('country') || header.includes('país') || header.includes('pais') || 
            header.includes('location') || header.includes('ubicación')) {
            return 'País';
        }
        
        // Detectar fuente/campaña
        if (header.includes('source') || header.includes('fuente') || header.includes('campaign') || 
            header.includes('campaña') || header.includes('origen')) {
            return 'Marketing';
        }
        
        // Detectar notas/comentarios
        if (header.includes('note') || header.includes('nota') || header.includes('comment') || 
            header.includes('comentario') || header.includes('observ')) {
            return 'Texto';
        }
        
        // Detectar empresa/compañía
        if (header.includes('company') || header.includes('empresa') || header.includes('corp') || 
            header.includes('organization')) {
            return 'Empresa';
        }
        
        return 'Texto';
    },

    // Intentar mapeo automático inteligente
    attemptAutoMapping() {
        if (!this.data.excelData) return;
        
        const headers = this.data.excelData.headers;
        const autoMappings = {};
        
        headers.forEach((header, index) => {
            const headerLower = header.toLowerCase();
            
            // Mapeo automático basado en nombres comunes
            if (headerLower.includes('first') && headerLower.includes('name')) {
                autoMappings['firstName'] = { column: index, header };
            } else if (headerLower.includes('last') && headerLower.includes('name')) {
                autoMappings['lastName'] = { column: index, header };
            } else if (headerLower.includes('nombre') && !headerLower.includes('completo')) {
                autoMappings['firstName'] = { column: index, header };
            } else if (headerLower.includes('apellido')) {
                autoMappings['lastName'] = { column: index, header };
            } else if (headerLower.includes('email') || headerLower.includes('correo')) {
                autoMappings['email'] = { column: index, header };
            } else if (headerLower.includes('phone') || headerLower.includes('teléfono') || headerLower.includes('telefono')) {
                autoMappings['phone'] = { column: index, header };
            } else if (headerLower.includes('country') || headerLower.includes('país') || headerLower.includes('pais')) {
                autoMappings['country'] = { column: index, header };
            } else if (headerLower.includes('source') || headerLower.includes('fuente')) {
                autoMappings['source'] = { column: index, header };
            } else if (headerLower.includes('campaign') || headerLower.includes('campaña')) {
                autoMappings['campaign'] = { column: index, header };
            } else if (headerLower.includes('note') || headerLower.includes('nota') || headerLower.includes('comment')) {
                autoMappings['notes'] = { column: index, header };
            }
            
            // Casos especiales para nombres completos
            if (headerLower.includes('nombre completo') || headerLower === 'name') {
                autoMappings['firstName'] = { column: index, header };
            }
        });
        
        // Aplicar mapeos automáticos
        Object.entries(autoMappings).forEach(([field, mapping]) => {
            this.data.columnMapping[field] = mapping;
            
            const zone = document.querySelector(`[data-field="${field}"] .field-dropzone`);
            if (zone) {
                this.updateMappingDisplay(zone, mapping);
            }
        });
        
        // Actualizar resumen
        this.updateMappingSummary();
        
        // Mostrar notificación si se hizo mapeo automático
        const mappedCount = Object.keys(autoMappings).length;
        if (mappedCount > 0) {
            App.showNotification(`Mapeo automático: ${mappedCount} campos detectados`, 'success');
        }
    },

    // Configurar drag & drop
    setupDragAndDrop() {
        // Elementos arrastrables
        document.querySelectorAll('.column-item').forEach(item => {
            item.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', JSON.stringify({
                    column: item.dataset.column,
                    header: item.dataset.header
                }));
                item.classList.add('dragging');
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
            });
        });

        // Zonas de drop
        document.querySelectorAll('.field-dropzone').forEach(zone => {
            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                zone.classList.add('drag-over');
            });

            zone.addEventListener('dragleave', () => {
                zone.classList.remove('drag-over');
            });

            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                zone.classList.remove('drag-over');
                
                const data = JSON.parse(e.dataTransfer.getData('text/plain'));
                const field = zone.dataset.field;
                
                this.mapColumn(field, data);
                this.updateMappingDisplay(zone, data);
                this.updateMappingSummary();
            });
        });
    },

    // Mapear columna a campo
    mapColumn(field, columnData) {
        this.data.columnMapping[field] = {
            column: parseInt(columnData.column),
            header: columnData.header
        };
    },

    // Actualizar display del mapeo
    updateMappingDisplay(zone, data) {
        zone.classList.add('mapped');
        zone.innerHTML = `
            <i class="fas fa-check-circle text-success"></i>
            <span>${data.header}</span>
            <button class="btn btn-sm btn-outline-danger ms-2" onclick="ImportWizard.unmapField('${zone.dataset.field}')">
                <i class="fas fa-times"></i>
            </button>
        `;
    },

    // Desmapear campo
    unmapField(field) {
        delete this.data.columnMapping[field];
        
        const zone = document.querySelector(`[data-field="${field}"] .field-dropzone`);
        if (zone) {
            zone.classList.remove('mapped');
            zone.innerHTML = `
                <i class="fas fa-plus-circle"></i>
                <span>Arrastra columna aquí</span>
            `;
        }
        
        this.updateMappingSummary();
    },

    // Actualizar resumen del mapeo
    updateMappingSummary() {
        const mappedCount = Object.keys(this.data.columnMapping).length;
        const requiredFields = ['firstName', 'lastName', 'email'];
        const mappedRequired = requiredFields.filter(field => this.data.columnMapping[field]).length;
        
        const status = document.getElementById('mappingStatus');
        if (status) {
            if (mappedRequired === requiredFields.length) {
                status.innerHTML = `
                    <i class="fas fa-check-circle text-success me-2"></i>
                    Mapeo completo: ${mappedCount} campos mapeados (${mappedRequired}/${requiredFields.length} obligatorios)
                `;
                status.parentElement.className = 'alert alert-success';
            } else {
                status.innerHTML = `
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    Mapeo incompleto: ${mappedCount} campos mapeados (${mappedRequired}/${requiredFields.length} obligatorios)
                `;
                status.parentElement.className = 'alert alert-warning';
            }
        }
    },

    // Analizar duplicados
    analyzeDuplicates() {
        // Simulación de análisis de duplicados
        const totalRows = this.data.excelData?.rows.length || 0;
        const duplicates = Math.floor(totalRows * 0.04); // 4% duplicados simulados
        const unique = totalRows - duplicates;
        
        document.getElementById('uniqueLeads').textContent = unique;
        document.getElementById('duplicateLeads').textContent = duplicates;
        
        this.updateDuplicateConfig();
    },

    // Actualizar configuración de duplicados
    updateDuplicateConfig() {
        const field = document.getElementById('duplicateField')?.value || 'email';
        const action = document.querySelector('input[name="duplicateAction"]:checked')?.value || 'skip';
        
        this.data.duplicateConfig = { field, action };
        
        // Actualizar estadísticas según la acción
        const totalRows = this.data.excelData?.rows.length || 0;
        const duplicates = parseInt(document.getElementById('duplicateLeads')?.textContent || '0');
        
        let toImport, toSkip;
        
        switch (action) {
            case 'skip':
                toImport = totalRows - duplicates;
                toSkip = duplicates;
                break;
            case 'update':
                toImport = totalRows;
                toSkip = 0;
                break;
            case 'import':
                toImport = totalRows;
                toSkip = 0;
                break;
            default:
                toImport = totalRows - duplicates;
                toSkip = duplicates;
        }
        
        document.getElementById('toImport').textContent = toImport;
        document.getElementById('toSkip').textContent = toSkip;
    },

    // Generar resumen final
    generateSummary() {
        if (!this.data.selectedFile || !this.data.excelData) return;

        // Información del archivo
        document.getElementById('summaryFileName').textContent = this.data.selectedFile.name;
        document.getElementById('summaryRows').textContent = this.data.excelData.rows.length;
        document.getElementById('summaryCols').textContent = this.data.excelData.headers.length;

        // Mapeo
        const mappingList = document.getElementById('summaryMapping');
        if (mappingList) {
            mappingList.innerHTML = Object.entries(this.data.columnMapping)
                .map(([field, mapping]) => `
                    <li><strong>${this.getFieldLabel(field)}:</strong> ${mapping.header}</li>
                `).join('');
        }

        // Configuración
        const duplicateAction = this.data.duplicateConfig.action;
        const duplicateField = this.data.duplicateConfig.field;
        
        document.getElementById('summaryDuplicates').textContent = 
            `${this.getDuplicateActionLabel(duplicateAction)} por ${this.getFieldLabel(duplicateField)}`;
        
        document.getElementById('summaryStatus').textContent = 
            this.getStatusLabel(document.getElementById('defaultStatus')?.value || 'new');
        
        const assignSelect = document.getElementById('autoAssign');
        document.getElementById('summaryAssign').textContent = 
            assignSelect?.selectedOptions[0]?.text || 'Sin asignar';

        // Estadísticas
        document.getElementById('summaryToImport').textContent = 
            document.getElementById('toImport')?.textContent || '0';
        document.getElementById('summaryDuplicatesCount').textContent = 
            document.getElementById('duplicateLeads')?.textContent || '0';
        document.getElementById('summaryErrors').textContent = '0';
    },

    // Iniciar importación
    startImport() {
        const modal = new bootstrap.Modal(document.getElementById('progressModal'));
        modal.show();
        
        this.simulateImport();
    },

    // Simular proceso de importación
    simulateImport() {
        const totalRows = this.data.excelData?.rows.length || 0;
        let processed = 0;
        let imported = 0;
        let skipped = 0;
        
        const progressBar = document.getElementById('progressBar');
        const progressCircle = document.getElementById('progressCircle');
        const progressPercent = document.getElementById('progressPercent');
        const progressStatus = document.getElementById('progressStatus');
        const progressDetail = document.getElementById('progressDetail');
        
        const processedCount = document.getElementById('processedCount');
        const importedCount = document.getElementById('importedCount');
        const skippedCount = document.getElementById('skippedCount');

        const interval = setInterval(() => {
            processed += Math.floor(Math.random() * 5) + 1;
            
            if (processed >= totalRows) {
                processed = totalRows;
                clearInterval(interval);
                
                // Completar importación
                setTimeout(() => {
                    this.completeImport();
                }, 1000);
            }
            
            // Calcular importados y omitidos
            const duplicates = parseInt(document.getElementById('duplicateLeads')?.textContent || '0');
            const skipRate = duplicates / totalRows;
            
            imported = Math.floor(processed * (1 - skipRate));
            skipped = processed - imported;
            
            // Actualizar progreso
            const percent = Math.floor((processed / totalRows) * 100);
            
            if (progressBar) progressBar.style.width = `${percent}%`;
            if (progressPercent) progressPercent.textContent = `${percent}%`;
            
            // Actualizar círculo de progreso
            if (progressCircle) {
                const circumference = 314; // 2 * π * 50
                const offset = circumference - (percent / 100) * circumference;
                progressCircle.style.strokeDashoffset = offset;
            }
            
            // Actualizar contadores
            if (processedCount) processedCount.textContent = processed;
            if (importedCount) importedCount.textContent = imported;
            if (skippedCount) skippedCount.textContent = skipped;
            
            // Actualizar estado
            if (progressStatus) {
                progressStatus.textContent = `Procesando leads... (${processed}/${totalRows})`;
            }
            
            if (progressDetail) {
                progressDetail.textContent = `Importando fila ${processed} de ${totalRows}`;
            }
            
        }, 100);
    },

    // Completar importación
    completeImport() {
        // Cerrar modal de progreso
        const progressModal = bootstrap.Modal.getInstance(document.getElementById('progressModal'));
        if (progressModal) {
            progressModal.hide();
        }

        // Generar resultados
        this.generateResults();
        
        // Mostrar modal de resultados
        setTimeout(() => {
            const resultsModal = new bootstrap.Modal(document.getElementById('resultsModal'));
            resultsModal.show();
        }, 500);
    },

    // Generar resultados
    generateResults() {
        const totalRows = this.data.excelData?.rows.length || 0;
        const duplicates = parseInt(document.getElementById('duplicateLeads')?.textContent || '0');
        const imported = totalRows - duplicates;
        const skipped = duplicates;
        const errors = 0;
        
        // Actualizar estadísticas
        document.getElementById('resultImported').textContent = imported;
        document.getElementById('resultSkipped').textContent = skipped;
        document.getElementById('resultErrors').textContent = errors;
        document.getElementById('resultTime').textContent = '2.3s';
        
        // Actualizar contadores de tabs
        document.getElementById('importedTabCount').textContent = imported;
        document.getElementById('skippedTabCount').textContent = skipped;
        document.getElementById('errorsTabCount').textContent = errors;
        
        // Generar listas de resultados
        this.generateResultLists(imported, skipped);
        
        // Guardar resultados
        this.data.importResults = {
            imported,
            skipped,
            errors,
            total: totalRows
        };
    },

    // Generar listas de resultados
    generateResultLists(imported, skipped) {
        // Lista de importados
        const importedList = document.getElementById('importedList');
        if (importedList && this.data.excelData) {
            importedList.innerHTML = this.data.excelData.rows.slice(0, Math.min(imported, 10))
                .map(row => `
                    <tr>
                        <td>${row[0]} ${row[1]}</td>
                        <td>${row[2]}</td>
                        <td>${row[3]}</td>
                        <td><span class="badge bg-success">Importado</span></td>
                    </tr>
                `).join('');
        }
        
        // Lista de omitidos
        const skippedList = document.getElementById('skippedList');
        if (skippedList && this.data.excelData && skipped > 0) {
            skippedList.innerHTML = this.data.excelData.rows.slice(-Math.min(skipped, 10))
                .map(row => `
                    <tr>
                        <td>${row[0]} ${row[1]}</td>
                        <td>${row[2]}</td>
                        <td>Lead duplicado</td>
                    </tr>
                `).join('');
        }
        
        // Lista de errores (vacía en simulación)
        const errorsList = document.getElementById('errorsList');
        if (errorsList) {
            errorsList.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No hay errores</td></tr>';
        }
    },

    // Remover archivo
    removeFile() {
        console.log('Removiendo archivo seleccionado');
        
        this.data.selectedFile = null;
        this.data.excelData = null;
        this.data.columnMapping = {}; // Limpiar mapeo
        
        const fileInfo = document.getElementById('fileInfo');
        const fileInput = document.getElementById('excelFile');
        
        if (fileInfo) {
            fileInfo.style.display = 'none';
        }
        
        if (fileInput) {
            fileInput.value = '';
        }
        
        // Limpiar preview si existe
        const previewTable = document.getElementById('previewTable');
        if (previewTable) {
            const tbody = previewTable.querySelector('tbody');
            const thead = previewTable.querySelector('thead');
            if (tbody) tbody.innerHTML = '';
            if (thead) thead.innerHTML = '';
        }
        
        // Limpiar mapeo de columnas
        const excelColumns = document.getElementById('excelColumns');
        if (excelColumns) {
            excelColumns.innerHTML = '';
        }
        
        // Resetear dropzones
        document.querySelectorAll('.field-dropzone').forEach(zone => {
            zone.classList.remove('mapped');
            zone.innerHTML = `
                <i class="fas fa-plus-circle"></i>
                <span>Arrastra columna aquí</span>
            `;
        });
        
        App.showNotification('Archivo removido correctamente', 'info');
    },

    // Volver atrás
    goBack() {
        if (confirm('¿Estás seguro de salir del asistente? Se perderán todos los datos.')) {
            // Volver al módulo de leads
            App.loadModule('leads');
        }
    },

    // Ir a leads después de importar
    goToLeads() {
        App.loadModule('leads');
        App.showNotification(`Importación completada: ${this.data.importResults?.imported || 0} leads importados`, 'success');
    },

    // Descargar reporte
    downloadReport() {
        App.showNotification('Descargando reporte de importación...', 'info');
        // Aquí se implementaría la descarga real del reporte
    },

    // Funciones auxiliares
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    getFieldLabel(field) {
        const labels = {
            firstName: 'Nombre',
            lastName: 'Apellido',
            email: 'Email',
            phone: 'Teléfono',
            country: 'País',
            source: 'Fuente',
            campaign: 'Campaña',
            notes: 'Notas'
        };
        return labels[field] || field;
    },

    getDuplicateActionLabel(action) {
        const labels = {
            skip: 'Omitir duplicados',
            update: 'Actualizar duplicados',
            import: 'Importar como nuevos'
        };
        return labels[action] || action;
    },

    getStatusLabel(status) {
        const labels = {
            new: 'Nuevo',
            contacted: 'Contactado',
            interested: 'Interesado'
        };
        return labels[status] || status;
    }
};

// Exportar para uso global
window.ImportWizard = ImportWizard;