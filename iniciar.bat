@echo off
setlocal
title One Piece Haki Encryption
echo ==================================================
echo   Iniciando One Piece Haki Encryption...
echo ==================================================
echo.

rem ---- Verificar que Docker este corriendo ---------------------
docker info >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Docker no esta corriendo.
    echo         Abre Docker Desktop y vuelve a ejecutar este script.
    echo.
    pause
    exit /b 1
)

rem ---- Crear .env si no existe ---------------------------------
if not exist .env (
    if exist .env.example (
        echo [1/4] Creando archivo .env desde .env.example...
        copy .env.example .env >nul
        echo       Archivo .env creado.
        echo.
        echo [IMPORTANTE] Debes configurar los siguientes valores en .env
        echo   antes de continuar:
        echo     - APP_KEY          ^(ejecuta: docker compose run --rm --no-deps app php artisan key:generate^)
        echo     - CRYPTO_PEPPER    ^(ejecuta en WSL/Git Bash: openssl rand -hex 32^)
        echo     - DB_PASSWORD
        echo     - DB_ROOT_PASSWORD
        echo     - DB_MIGRATION_PASSWORD
        echo.
        echo   Edita el archivo .env, completa esos campos y vuelve a ejecutar este script.
        echo.
        pause
        exit /b 1
    ) else (
        echo [ERROR] No se encontro .env ni .env.example
        pause
        exit /b 1
    )
)

rem ---- Detener contenedores anteriores -------------------------
echo [1/4] Deteniendo contenedores anteriores...
docker compose down >nul 2>&1

rem ---- Levantar contenedores -----------------------------------
echo [2/4] Compilando y levantando contenedores...
docker compose up -d --build
if errorlevel 1 (
    echo.
    echo [ERROR] Fallo al levantar los contenedores.
    echo         Revisa los mensajes de arriba para mas detalles.
    echo.
    pause
    exit /b 1
)

rem ---- Esperar a que los servicios esten listos ----------------
echo [3/4] Esperando a que los servicios inicien...
echo       (esto puede tomar 15-30 segundos)

set /a intentos=0
:esperar_db
set /a intentos+=1
if %intentos% gtr 30 (
    echo.
    echo [AVISO] MySQL tardo demasiado en arrancar.
    echo         La aplicacion puede no estar lista aun.
    goto abrir
)
timeout /t 2 /nobreak >nul
docker compose exec -T db mysqladmin ping -h 127.0.0.1 --silent >nul 2>&1
if errorlevel 1 goto esperar_db

echo       MySQL listo.
timeout /t 3 /nobreak >nul

rem ---- Abrir navegador -----------------------------------------
:abrir
echo [4/4] Abriendo aplicacion en el navegador...
start https://localhost:8443

echo.
echo ==================================================
echo   Listo! Aplicacion corriendo en:
echo   https://localhost:8443
echo.
echo   (Acepta la advertencia del certificado autofirmado)
echo.
echo   Credenciales de prueba:
echo   - Admin 500:      admin500@haki.test  / Capitan#Haki-500!
echo   - Supervisor 499: visor499@haki.test  / Vigia#Haki-4999!
echo   - Estandar 300:   basico300@haki.test / Nakama#Haki-3000!
echo ==================================================
echo.
echo   Para detener: docker compose down
echo.
pause
