param(
  [string]$DbHost = "localhost",
  [string]$DbName = "",
  [string]$DbUser = "",
  [string]$DbPassword = "",
  [string]$AppUrl = "https://tu-dominio.com",
  [switch]$SkipBuild,
  [switch]$IncludeVendor,
  [string]$OutputDir = "deploy/out"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Write-Info($msg) { Write-Host "[INFO] $msg" -ForegroundColor Cyan }
function Write-Ok($msg)   { Write-Host "[OK]  $msg" -ForegroundColor Green }
function Write-Warn($msg) { Write-Host "[WARN] $msg" -ForegroundColor Yellow }
function Write-Err($msg)  { Write-Host "[ERR] $msg" -ForegroundColor Red }

# Resolve repo root (this script lives in scripts/)
$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$FrontendDir = Join-Path $RepoRoot 'frontend'
$PublicDir   = Join-Path $RepoRoot 'public'
$ConfigDir   = Join-Path $RepoRoot 'config'
$SrcDir      = Join-Path $RepoRoot 'src'
$VendorDir   = Join-Path $RepoRoot 'vendor'

Write-Info "Repositorio: $RepoRoot"

# Validate required inputs
if ([string]::IsNullOrWhiteSpace($DbName) -or [string]::IsNullOrWhiteSpace($DbUser) -or [string]::IsNullOrWhiteSpace($DbPassword)) {
  Write-Err "Debes proporcionar DB_NAME, DB_USER y DB_PASSWORD. Ejemplo: -DbName mydb -DbUser admin -DbPassword 'secret'"
  exit 1
}

# Optional: build frontend
if (-not $SkipBuild) {
  Write-Info "Compilando frontend (npm ci && npm run build)..."
  if (-not (Test-Path $FrontendDir)) { Write-Err "No se encontró directorio frontend en $FrontendDir"; exit 1 }
  Push-Location $FrontendDir
  try {
    if (-not (Test-Path (Join-Path $FrontendDir 'node_modules'))) {
      npm ci | Out-Host
    }
    npm run build | Out-Host
    Write-Ok "Build de frontend completado"
  } catch {
    Write-Err "Falló la compilación del frontend: $($_.Exception.Message)"
    exit 1
  } finally { Pop-Location }
} else {
  Write-Warn "SkipBuild activo, no se compila el frontend"
}

# Prepare package directory
if (-not (Test-Path $OutputDir)) { New-Item -ItemType Directory -Path $OutputDir | Out-Null }
$Stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$PackageDir = Join-Path $OutputDir "package-$Stamp"
New-Item -ItemType Directory -Path $PackageDir | Out-Null
Write-Info "Creando paquete en: $PackageDir"

# Copy required directories/files
function Copy-Safe($src, $dest) {
  if (Test-Path $src) {
    Copy-Item -Path $src -Destination $dest -Recurse -Force -ErrorAction Stop
  } else {
    Write-Warn "No existe: $src (omitido)"
  }
}

# Estructura recomendada: public/, config/, src/, .htaccess, composer.json
Copy-Safe $PublicDir (Join-Path $PackageDir 'public')
Copy-Safe $ConfigDir (Join-Path $PackageDir 'config')
Copy-Safe $SrcDir    (Join-Path $PackageDir 'src')
Copy-Safe (Join-Path $RepoRoot '.htaccess') (Join-Path $PackageDir '.htaccess')
Copy-Safe (Join-Path $RepoRoot 'composer.json') (Join-Path $PackageDir 'composer.json')
Copy-Safe (Join-Path $RepoRoot 'composer.lock') (Join-Path $PackageDir 'composer.lock')

# Include built assets inside package's public/assets (do not alter local repo)
$DistDir = Join-Path $FrontendDir 'dist'
$PackagePublicAssetsDir = Join-Path $PackageDir 'public\assets'
if (Test-Path $DistDir) {
  Write-Info "Incluyendo assets compilados en el paquete (public/assets)..."
  if (-not (Test-Path $PackagePublicAssetsDir)) { New-Item -ItemType Directory -Path $PackagePublicAssetsDir | Out-Null }
  Copy-Item -Path (Join-Path $DistDir 'assets\*') -Destination $PackagePublicAssetsDir -Recurse -Force -ErrorAction SilentlyContinue
  Write-Ok "Assets del frontend añadidos al paquete"
} else {
  Write-Warn "No se encontró frontend/dist; el paquete usará assets actuales de public/"
}

if ($IncludeVendor) {
  Write-Warn "Incluyendo vendor/ (aumenta tamaño del paquete)"
  Copy-Safe $VendorDir (Join-Path $PackageDir 'vendor')
} else {
  Write-Info "Sin vendor/. Ejecuta 'composer install --no-dev --optimize-autoloader' en servidor"
}

# Generate .env.production in package
$EnvProdPath = Join-Path $PackageDir '.env.production'
$envContent = @(
  "APP_ENV=production",
  "APP_DEBUG=false",
  "APP_URL=$AppUrl",
  "DB_CONNECTION=mysql",
  "DB_HOST=$DbHost",
  "DB_PORT=3306",
  "DB_DATABASE=$DbName",
  "DB_USERNAME=$DbUser",
  "DB_PASSWORD=$DbPassword",
  "SESSION_LIFETIME=180",
  "FORCE_HTTPS=true"
) -join [Environment]::NewLine
Set-Content -Path $EnvProdPath -Value $envContent -Encoding UTF8
Write-Ok ".env.production generado"

# Generate deployment instructions
$InstructionsPath = Join-Path $PackageDir 'INSTRUCCIONES_DESPLIEGUE.md'
$instructions = @"
# Instrucciones de Despliegue (paquete $Stamp)

## Contenido
- public/ (API y SPA)
- config/
- src/
- composer.json / composer.lock
- .htaccess
- .env.production (con credenciales de BD configuradas)

## Pasos
1) Subir el contenido del paquete al servidor (DocumentRoot apuntando a public/)
2) En el servidor, ejecutar: `composer install --no-dev --optimize-autoloader`
3) Asegurar permisos de escritura en `public/uploads/` y `storage/` (si existe)
4) Renombrar `.env.production` a `.env` o configurar el loader para `.env.production`
5) (Opcional) Importar `initial_data.sql` si es primera instalación
6) Probar salud: `https://tu-dominio.com/api/health.php`
7) Verificar SPA: `https://tu-dominio.com/`

## Notas
- Este paquete no modifica tu código fuente local
- Las credenciales solo están en este paquete (`.env.production`)
- Si usas Nginx, asegúrate de reescrituras al `public/index.php`
"@
Set-Content -Path $InstructionsPath -Value $instructions -Encoding UTF8
Write-Ok "Instrucciones de despliegue generadas"

# Create zip archive
$ZipPath = Join-Path $OutputDir "deployment-package-$Stamp.zip"
Compress-Archive -Path (Join-Path $PackageDir '*') -DestinationPath $ZipPath -Force
Write-Ok "Paquete comprimido: $ZipPath"

Write-Host ""; Write-Ok "Asistente de despliegue finalizado"
Write-Info "Siguiente: sube el ZIP al servidor y sigue INSTRUCCIONES_DESPLIEGUE.md"