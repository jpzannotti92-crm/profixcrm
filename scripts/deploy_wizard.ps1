param(
  [string]$DbHost = "localhost",
  [string]$DbName = "",
  [string]$DbUser = "",
  [string]$DbPassword = "",
  [string]$AppUrl = "https://tu-dominio.com",
  [switch]$SkipBuild,
  [switch]$IncludeVendor,
  [string]$OutputDir = "deploy/out",
  [string]$RemoteHost = "",
  [string]$RemoteUser = "",
  [string]$RemotePath = "",
  [switch]$CheckOnly
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Write-Info($msg) { Write-Host "[INFO] $msg" -ForegroundColor Cyan }
function Write-Ok($msg)   { Write-Host "[OK]  $msg" -ForegroundColor Green }
function Write-Warn($msg) { Write-Host "[WARN] $msg" -ForegroundColor Yellow }
function Write-Err($msg)  { Write-Host "[ERR] $msg" -ForegroundColor Red }

$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$FrontendDir = Join-Path $RepoRoot 'frontend'
$PublicDir   = Join-Path $RepoRoot 'public'

Write-Info "Asistente de despliegue iniciado"
Write-Info "Repositorio: $RepoRoot"

function Test-Command($name) {
  $cmd = Get-Command $name -ErrorAction SilentlyContinue
  return [bool]$cmd
}

function Get-PHPExe() {
  $localPhp = Join-Path $RepoRoot 'php82\php.exe'
  if (Test-Path $localPhp) { return $localPhp }
  if (Test-Command 'php') { return 'php' }
  return $null
}

function Get-PHPVersion($exe) {
  try {
    $verOut = & $exe -v 2>$null | Out-String
    if ($verOut -match 'PHP\s+(\d+)\.(\d+)\.(\d+)') {
      return @([int]$Matches[1],[int]$Matches[2],[int]$Matches[3])
    }
    return $null
  } catch { return $null }
}

function Compare-Version($v, $minMajor, $minMinor) {
  if ($null -eq $v) { return $false }
  if ($v[0] -gt $minMajor) { return $true }
  if ($v[0] -eq $minMajor -and $v[1] -ge $minMinor) { return $true }
  return $false
}

# 1) Comprobaciones locales
Write-Info "Verificando requisitos locales..."
$phpExe = Get-PHPExe
if ($null -eq $phpExe) { Write-Err "PHP no encontrado (ni php82\\php.exe ni 'php' en PATH)"; exit 1 }
$phpVer = Get-PHPVersion $phpExe
if (-not (Compare-Version $phpVer 8 2)) { Write-Err "Se requiere PHP >= 8.2 (detectado: $($phpVer -join '.'))"; exit 1 } else { Write-Ok "PHP $($phpVer -join '.') OK ($phpExe)" }

if (-not (Test-Command 'composer')) { Write-Err "Composer no encontrado en PATH"; exit 1 } else { Write-Ok "Composer OK" }
if (-not (Test-Command 'npm')) { Write-Warn "npm no encontrado; usa -SkipBuild si ya tienes assets" } else { Write-Ok "npm OK" }

if ([string]::IsNullOrWhiteSpace($DbName) -or [string]::IsNullOrWhiteSpace($DbUser) -or [string]::IsNullOrWhiteSpace($DbPassword)) {
  Write-Err "Debes proporcionar DB_NAME, DB_USER y DB_PASSWORD. Ejemplo: -DbName mydb -DbUser admin -DbPassword 'secret'"
  exit 1
}

if ($CheckOnly) { Write-Ok "Requisitos locales verificados. Modo CheckOnly, saliendo."; exit 0 }

# 2) Comprobaciones remotas (opcionales)
if (-not [string]::IsNullOrWhiteSpace($RemoteHost)) {
  Write-Info "Verificando requisitos en servidor remoto $RemoteUser@$RemoteHost ..."
  if (-not (Test-Command 'ssh')) {
    Write-Warn "No se encontró 'ssh' en local. Saltando chequeos remotos."
  } else {
    try {
      $remotePhpOut = ssh "$RemoteUser@$RemoteHost" "php -v" 2>$null | Out-String
      if ($remotePhpOut -match 'PHP\s+(\d+)\.(\d+)\.(\d+)') {
        $rv = @([int]$Matches[1],[int]$Matches[2],[int]$Matches[3])
        if (-not (Compare-Version $rv 8 0)) {
          Write-Warn "PHP remoto recomendado >=8.2 (detectado: $($rv -join '.')), considera actualizar"
        } else { Write-Ok "PHP remoto $($rv -join '.')" }
      } else { Write-Warn "No pude leer versión de PHP remoto" }

      $remoteComposerOut = ssh "$RemoteUser@$RemoteHost" "composer --version" 2>$null | Out-String
      if ([string]::IsNullOrWhiteSpace($remoteComposerOut)) { Write-Warn "Composer remoto no detectado" } else { Write-Ok "Composer remoto OK" }

      $remoteExt = ssh "$RemoteUser@$RemoteHost" "php -m" 2>$null | Out-String
      if ($remoteExt -notmatch '(?s)PDO(?=.*mysql)') { Write-Warn "Extensión pdo_mysql no detectada en remoto" } else { Write-Ok "pdo_mysql presente en remoto" }
    } catch { Write-Warn "Chequeos remotos fallaron: $($_.Exception.Message)" }
  }
}

# 3) Construcción y empaquetado utilizando el asistente existente
Write-Info "Ejecutando empaquetado con scripts\\deploy_assistant.ps1 ..."
$assistantPath = Join-Path $RepoRoot 'scripts\deploy_assistant.ps1'
if (-not (Test-Path $assistantPath)) { Write-Err "No se encontró $assistantPath"; exit 1 }

& $assistantPath -DbHost $DbHost -DbName $DbName -DbUser $DbUser -DbPassword $DbPassword -AppUrl $AppUrl -SkipBuild:$SkipBuild -IncludeVendor:$IncludeVendor -OutputDir $OutputDir

function Get-LatestZip($dir) {
  if (-not (Test-Path $dir)) { return $null }
  $z = Get-ChildItem -Path $dir -Filter 'deployment-package-*.zip' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
  return $z?.FullName
}

$zipPath = Get-LatestZip $OutputDir
if ($null -eq $zipPath) { Write-Err "No se encontró ZIP generado en $OutputDir"; exit 1 } else { Write-Ok "Paquete: $zipPath" }

# 4) Subida y despliegue en servidor (opcional si se indican parámetros remotos)
if (-not [string]::IsNullOrWhiteSpace($RemoteHost) -and -not [string]::IsNullOrWhiteSpace($RemoteUser) -and -not [string]::IsNullOrWhiteSpace($RemotePath)) {
  Write-Info "Preparando subida a $RemoteUser@$RemoteHost:$RemotePath ..."
  $zipName = Split-Path $zipPath -Leaf

  if (-not (Test-Command 'scp')) {
    Write-Warn "scp no disponible. Sube manualmente el ZIP a $RemotePath y continúa con los comandos sugeridos."
  } else {
    try {
      ssh "$RemoteUser@$RemoteHost" "mkdir -p '$RemotePath'"
      scp $zipPath "$RemoteUser@$RemoteHost:$RemotePath/"
      Write-Ok "ZIP subido"
    } catch { Write-Warn "Falló subida remota: $($_.Exception.Message)" }
  }

  if (Test-Command 'ssh') {
    try {
      $unzipCmd = "cd '$RemotePath' && unzip -o '$zipName'"
      ssh "$RemoteUser@$RemoteHost" $unzipCmd
      Write-Ok "ZIP descomprimido en remoto"

      $postCmd = @(
        "cd '$RemotePath'",
        "[ -f .env.production ] && mv .env.production .env || true",
        "composer install --no-dev --optimize-autoloader || true",
        "mkdir -p public/uploads storage/logs storage/cache storage/sessions",
        "chmod -R 775 public/uploads storage || true",
        "[ -f run_migration.php ] && php run_migration.php || true",
        "[ -f database/install.php ] && php database/install.php || true"
      ) -join ' && '

      ssh "$RemoteUser@$RemoteHost" $postCmd
      Write-Ok "Dependencias instaladas y permisos configurados en remoto"
    } catch { Write-Warn "Comandos remotos fallaron: $($_.Exception.Message)" }
  }
}

# 5) Pruebas de salud
Write-Info "Verificando salud en $AppUrl/api/health.php ..."
try {
  $resp = Invoke-WebRequest -Uri ("$AppUrl/api/health.php") -UseBasicParsing -TimeoutSec 15
  if ($resp.StatusCode -lt 400) { Write-Ok "Health API responde: $($resp.StatusCode)" } else { Write-Warn "Health API status: $($resp.StatusCode)" }
} catch { Write-Warn "No se pudo verificar health API: $($_.Exception.Message)" }

Write-Host ""
Write-Ok "Asistente completado"
Write-Info "Siguientes pasos: validar login y flujo de navegación en $AppUrl"