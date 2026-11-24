@echo off
setlocal EnableExtensions DisableDelayedExpansion
title One Piece Haki Encryption

pushd "%~dp0" || goto error_folder
set "HAKI_URL=https://localhost:8443/"
set "PMA_URL=http://127.0.0.1:8081/"
set "HAKI_PROJECT=haki-prueba-entrega"
set "HAKI_COMPOSE=docker compose --project-name %HAKI_PROJECT%"

if not exist docker-compose.yml (
    echo [ERROR] Extrae el ZIP completo y coloca iniciar.bat junto a docker-compose.yml.
    goto error
)
if not exist .env (
    echo [ERROR] Falta el archivo .env preparado para la demo.
    goto error
)
docker info >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Abre Docker Desktop y vuelve a ejecutar iniciar.bat.
    goto error
)
where curl.exe >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Windows necesita curl.exe para comprobar que la demo este disponible.
    goto error
)

echo Iniciando Laravel, MySQL y phpMyAdmin...
%HAKI_COMPOSE% up -d
if errorlevel 1 goto diagnostics

if not exist vendor\autoload.php (
    echo Instalando las dependencias de Laravel...
    %HAKI_COMPOSE% exec -T -e COMPOSER_ALLOW_SUPERUSER=1 app composer install --no-dev --no-interaction --prefer-dist
    if errorlevel 1 goto diagnostics
)

echo Esperando respuesta de Laravel...
call :wait_http "%HAKI_URL%" --insecure
if errorlevel 1 goto diagnostics
echo Esperando respuesta de phpMyAdmin...
call :wait_http "%PMA_URL%"
if errorlevel 1 goto diagnostics

echo Laravel: %HAKI_URL%
echo phpMyAdmin: %PMA_URL%
set "HAKI_BROWSER_FAILED="
start "" "%HAKI_URL%" || set "HAKI_BROWSER_FAILED=1"
start "" "%PMA_URL%" || set "HAKI_BROWSER_FAILED=1"
if defined HAKI_BROWSER_FAILED (
    echo [AVISO] Si una pagina no se abrio, copia su direccion en el navegador.
    pause
)
popd
exit /b 0

:wait_http
set /a HAKI_ATTEMPTS=0
:probe
curl.exe --disable --noproxy "*" %~2 --fail --silent --max-time 3 --output NUL "%~1"
if not errorlevel 1 exit /b 0
set /a HAKI_ATTEMPTS+=1
if %HAKI_ATTEMPTS% geq 20 (
    echo [ERROR] No se pudo abrir %~1
    curl.exe --disable --noproxy "*" %~2 --silent --show-error --max-time 5 --output NUL --write-out "HTTP %%{http_code}\n" "%~1"
    exit /b 1
)
timeout /t 2 /nobreak >nul
goto probe

:diagnostics
%HAKI_COMPOSE% ps -a
%HAKI_COMPOSE% logs --tail 15 app db phpmyadmin

:error
pause
popd
exit /b 1

:error_folder
echo [ERROR] No se pudo abrir la carpeta del proyecto.
pause
exit /b 1
