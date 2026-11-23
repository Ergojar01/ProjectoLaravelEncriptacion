@echo off
title One Piece Haki Encryption
echo ==================================================
echo   Iniciando One Piece Haki Encryption...
echo ==================================================

if not exist .env (
    if exist .env.example (
        echo [!] Creando archivo .env desde .env.example...
        copy .env.example .env >nul
    )
)

if not exist vendor (
    echo [1/3] Instalando dependencias de Composer...
    docker compose run --rm --no-deps app composer install --optimize-autoloader
)

echo [2/3] Levantando contenedores en segundo plano...
docker compose up -d --build

echo [3/3] Esperando inicializacion de servicios...
timeout /t 6 /nobreak >nul

echo Abriendo aplicacion en el navegador...
start https://localhost

echo ==================================================
echo   Listo! Aplicacion corriendo en:
echo   https://localhost
echo.
echo   Credenciales de prueba:
echo   - Admin 500:      admin500@haki.test  / Capitan#Haki-500!
echo   - Supervisor 499: visor499@haki.test  / Vigia#Haki-4999!
echo   - Estandar 300:   basico300@haki.test / Nakama#Haki-3000!
echo ==================================================
pause
