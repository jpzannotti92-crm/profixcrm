<?php
/**
 * V8 VALIDATOR WEB
 * 
 * Validador web para ProfixCRM V8
 * Resuelve problemas de redirección de V7
 * 
 * @version 8.0.0
 * @author ProfixCRM
 */

// Prevenir redirecciones
$_SERVER['SCRIPT_NAME'] = basename(__FILE__);
$_SERVER['REQUEST_METHOD'] = 'WEB';

// Headers para prevenir caché
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-Type: text/html; charset=utf-8');

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 V8 Validator Web - ProfixCRM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .controls {
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .mode-selector {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .mode-selector label {
            font-weight: 600;
            color: #495057;
        }
        
        .mode-selector select {
            padding: 8px 12px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .content {
            padding: 30px;
        }
        
        .loading {
            text-align: center;
            padding: 50px;
            font-size: 1.2em;
            color: #6c757d;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .results {
            display: none;
        }
        
        .summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .summary h2 {
            color: #495057;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .summary-card .number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .summary-card .label {
            color: #6c757d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .passed { color: #28a745; }
        .warning { color: #ffc107; }
        .failed { color: #dc3545; }
        .error { color: #6f42c1; }
        
        .checks {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .check-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: background-color 0.3s ease;
        }
        
        .check-item:hover {
            background-color: #f8f9fa;
        }
        
        .check-item:last-child {
            border-bottom: none;
        }
        
        .check-icon {
            font-size: 1.5em;
            width: 30px;
            text-align: center;
        }
        
        .check-content {
            flex: 1;
        }
        
        .check-name {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .check-message {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .check-time {
            color: #adb5bd;
            font-size: 0.8em;
            margin-left: auto;
        }
        
        .recommendations {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
            border-left: 5px solid #ffc107;
        }
        
        .recommendations h3 {
            color: #856404;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .recommendations ul {
            list-style: none;
            padding: 0;
        }
        
        .recommendations li {
            padding: 8px 0;
            color: #856404;
            position: relative;
            padding-left: 25px;
        }
        
        .recommendations li:before {
            content: "💡";
            position: absolute;
            left: 0;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .status-excellent {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .status-good {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }
        
        .status-warning {
            background: linear-gradient(135deg, #fd7e14 0%, #dc3545 100%);
            color: white;
        }
        
        .status-critical {
            background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
            color: white;
        }
        
        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .mode-selector {
                justify-content: space-between;
            }
            
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .check-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .check-time {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 V8 Validator Web</h1>
            <p>Sistema de validación inteligente para ProfixCRM V8</p>
        </div>
        
        <div class="controls">
            <div class="mode-selector">
                <label for="mode">Modo de Validación:</label>
                <select id="mode">
                    <option value="full">Completa</option>
                    <option value="quick">Rápida</option>
                    <option value="production">Producción</option>
                    <option value="debug">Debug</option>
                    <option value="cli">CLI</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="startValidation()">
                🚀 Iniciar Validación
            </button>
            <button class="btn btn-secondary" onclick="location.reload()">
                🔄 Recargar
            </button>
        </div>
        
        <div class="content">
            <div id="loading" class="loading" style="display: none;">
                <div class="spinner"></div>
                <p>Validando sistema V8...</p>
            </div>
            
            <div id="results" class="results"></div>
        </div>
        
        <div class="footer">
            <p>ProfixCRM V8 - Sistema de Validación Inteligente | 
            <a href="validate_v8_cli.php" style="color: #007bff;">Versión CLI</a> | 
            <a href="index.php" style="color: #007bff;">Inicio</a></p>
        </div>
    </div>
    
    <script>
        async function startValidation() {
            const mode = document.getElementById("mode").value;
            const loading = document.getElementById("loading");
            const results = document.getElementById("results");
            
            // Mostrar loading
            loading.style.display = "block";
            results.style.display = "none";
            results.innerHTML = "";
            
            try {
                // Realizar validación
                const response = await fetch("validate_v8_web_ajax.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({ mode: mode })
                });
                
                if (!response.ok) {
                    throw new Error("Error en la validación");
                }
                
                const data = await response.json();
                displayResults(data);
                
            } catch (error) {
                results.innerHTML = `
                    <div class="summary status-critical">
                        <h2>🔥 Error en la Validación</h2>
                        <p>${error.message}</p>
                        <p>Por favor, intenta con el modo CLI: <code>php validate_v8.php</code></p>
                    </div>
                `;
                results.style.display = "block";
            } finally {
                loading.style.display = "none";
            }
        }
        
        function displayResults(data) {
            const results = document.getElementById("results");
            const summary = data.summary;
            
            // Determinar estado general
            let overallStatus = "excellent";
            let statusText = "EXCELENTE";
            let statusIcon = "🎉";
            
            if (summary.errors > 0) {
                overallStatus = "critical";
                statusText = "CRÍTICO";
                statusIcon = "🔥";
            } else if (summary.failed > 0) {
                overallStatus = "warning";
                statusText = "ADVERTENCIAS";
                statusIcon = "⚠️";
            } else if (summary.warnings > 0) {
                overallStatus = "good";
                statusText = "BIEN CON ADVERTENCIAS";
                statusIcon = "✅";
            }
            
            let html = `
                <div class="summary status-${overallStatus}">
                    <h2>${statusIcon} Estado General: ${statusText}</h2>
                    <div class="summary-grid">
                        <div class="summary-card">
                            <div class="number passed">${summary.passed}</div>
                            <div class="label">Pasadas</div>
                        </div>
                        <div class="summary-card">
                            <div class="number warning">${summary.warnings}</div>
                            <div class="label">Advertencias</div>
                        </div>
                        <div class="summary-card">
                            <div class="number failed">${summary.failed}</div>
                            <div class="label">Fallidas</div>
                        </div>
                        <div class="summary-card">
                            <div class="number error">${summary.errors}</div>
                            <div class="label">Errores</div>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Total de verificaciones: ${summary.total_checks}</span>
                        <span>Tiempo: ${data.performance.total_time}s</span>
                    </div>
                </div>
                
                <div class="checks">
                    <h2 style="padding: 20px; margin: 0; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">
                        🔍 Verificaciones Detalladas
                    </h2>
            `;
            
            // Mostrar checks
            Object.entries(data.checks).forEach(([name, check]) => {
                const icon = {
                    'passed': '✅',
                    'warning': '⚠️',
                    'failed': '❌',
                    'error': '🔥'
                }[check.status] || '❓';
                
                html += `
                    <div class="check-item">
                        <div class="check-icon ${check.status}">${icon}</div>
                        <div class="check-content">
                            <div class="check-name">${name}</div>
                            <div class="check-message">${check.message}</div>
                        </div>
                        ${check.execution_time ? `<div class="check-time">${check.execution_time}s</div>` : ''}
                    </div>
                `;
            });
            
            html += '</div>';
            
            // Recomendaciones
            if (data.recommendations && data.recommendations.length > 0) {
                html += `
                    <div class="recommendations">
                        <h3>💡 Recomendaciones</h3>
                        <ul>
                            ${data.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }
            
            // Información técnica
            html += `
                <div style="background: #f8f9fa; border-radius: 10px; padding: 20px; margin-top: 30px;">
                    <h3 style="color: #495057; margin-bottom: 15px;">📋 Información Técnica</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                        <div><strong>Fecha:</strong> ${data.timestamp}</div>
                        <div><strong>Entorno:</strong> ${data.environment}</div>
                        <div><strong>PHP:</strong> ${data.php_version}</div>
                        <div><strong>Servidor:</strong> ${data.server_software}</div>
                    </div>
                </div>
            `;
            
            results.innerHTML = html;
            results.style.display = "block";
        }
        
        // Auto-iniciar con modo rápido
        window.addEventListener("load", function() {
            document.getElementById("mode").value = "quick";
            startValidation();
        });
    </script>
</body>
</html>';