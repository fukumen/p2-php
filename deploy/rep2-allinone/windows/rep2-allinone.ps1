$ErrorActionPreference = "Stop"
$HERE = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $HERE

$DATA_BASE_DIR = "$HERE\var"
$PHP_BIN = "$HERE\bin\php.exe"
$PHP_CGI_BIN = "$HERE\bin\php-cgi.exe"
$PHP_INI = "$HERE\conf\php.ini"
$SECRETS_FILE = "$HERE\conf\secrets.conf"
$CACERT_PEM = "$HERE\conf\cacert.pem"

# Prepare caddy data directories
$XDG_CONFIG_HOME = "$DATA_BASE_DIR\caddy_config"
$XDG_DATA_HOME = "$DATA_BASE_DIR\caddy_data"
if (!(Test-Path $XDG_CONFIG_HOME)) { New-Item -ItemType Directory -Force -Path $XDG_CONFIG_HOME | Out-Null }
if (!(Test-Path $XDG_DATA_HOME)) { New-Item -ItemType Directory -Force -Path $XDG_DATA_HOME | Out-Null }

# Initial setup: Copy missing files from .orig
# (Similar to 'cp -Rn src/* dest/')
foreach ($folder in @("conf", "data")) {
    $srcDir = "$HERE\p2-php\$folder.orig"
    $destDir = "$HERE\p2-php\$folder"
    if (!(Test-Path $destDir)) {
        New-Item -ItemType Directory -Force -Path $destDir | Out-Null
    }
    if (Test-Path $srcDir) {
        Get-ChildItem -Path "$srcDir\*" | ForEach-Object {
            $destItem = Join-Path $destDir $_.Name
            if (!(Test-Path $destItem)) {
                Copy-Item -Path $_.FullName -Destination $destItem -Recurse -Force
            }
        }
    }
}

# Always overwrite conf_user_def*
if (Test-Path "$HERE\p2-php\conf.orig\conf_user_def*") {
    Copy-Item -Path "$HERE\p2-php\conf.orig\conf_user_def*" -Destination "$HERE\p2-php\conf\" -Force
}

# Generate SECRET_KEY if not exists
if (!(Test-Path $SECRETS_FILE)) {
    Write-Host "Generating new SECRET_KEY..."
    $key = & $PHP_BIN -r "echo bin2hex(random_bytes(32));"
    "SECRET_KEY=$key" | Out-File -FilePath $SECRETS_FILE -Encoding ASCII
}

# Load SECRET_KEY and build information (Set environment variables for the process)
foreach ($file in @("$SECRETS_FILE", "$HERE\conf\build_info")) {
    if (Test-Path $file) {
        foreach ($line in Get-Content $file) {
            if ($line -match '^([^#=]+)=(.*)$') {
                [System.Environment]::SetEnvironmentVariable($matches[1], $matches[2].Trim(), "Process")
            }
        }
    }
}

$env:ORIG_CONF = "$HERE\p2-php\conf.orig\conf.inc.php"
$env:TARGET_CONF = "$HERE\p2-php\conf\conf.inc.php"
$phpScript = @'
<?php
$orig_file = getenv('ORIG_CONF');
$target_file = getenv('TARGET_CONF');

if (!file_exists($orig_file) || !file_exists($target_file)) {
    exit;
}

$orig_lines = file($orig_file);
$target_lines = file($target_file);
$new_line = '';

foreach ($orig_lines as $line) {
    if (strpos($line, "'p2version'") !== false && strpos($line, '=>') !== false) {
        $new_line = $line;
        break;
    }
}

if ($new_line !== '') {
    foreach ($target_lines as $k => $line) {
        if (strpos($line, "'p2version'") !== false && strpos($line, '=>') !== false) {
            $target_lines[$k] = $new_line;
            break;
        }
    }
    file_put_contents($target_file, implode('', $target_lines));
}
'@
$phpScript | & $PHP_BIN

# Run setup
& $PHP_BIN -d extension_dir="$HERE\bin\ext" -d curl.cainfo="$CACERT_PEM" -d openssl.cafile="$CACERT_PEM" -c "$PHP_INI" "$HERE\p2-php\scripts\ic2.php" setup

# Cleanup existing processes to prevent port conflicts
Stop-Process -Name "php-cgi", "caddy" -Force -ErrorAction SilentlyContinue

try {
    Write-Host "Starting PHP FastCGI server..."
    $env:PHP_FCGI_MAX_REQUESTS = "1000"
    $phpArgs = @("-b", "127.0.0.1:9000", "-d", "extension_dir=""$HERE\bin\ext""", "-d", "curl.cainfo=""$CACERT_PEM""", "-d", "openssl.cafile=""$CACERT_PEM""", "-c", """$PHP_INI""")
    $php = Start-Process -FilePath $PHP_CGI_BIN -ArgumentList $phpArgs -NoNewWindow -PassThru

    Write-Host "Starting Caddy server..."
    & "$HERE\bin\caddy.exe" run --config "$HERE\conf\Caddyfile" --adapter caddyfile
} finally {
    Write-Host "Stopping servers..."
    Stop-Process -Name "php-cgi" -Force -ErrorAction SilentlyContinue
}
