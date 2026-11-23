#!/usr/bin/env bash
set -euo pipefail

echo "=================================================="
echo "  Iniciando One Piece Haki Encryption..."
echo "=================================================="
echo

# ---- Verificar Docker ----------------------------------------
if ! docker info &>/dev/null; then
    echo "[ERROR] Docker no esta corriendo."
    echo "        Inicia Docker y vuelve a ejecutar este script."
    exit 1
fi

# ---- Crear .env si no existe ----------------------------------
if [[ ! -f .env ]]; then
    if [[ -f .env.example ]]; then
        echo "[!] Creando archivo .env desde .env.example..."
        cp .env.example .env
    else
        echo "[ERROR] No se encontro .env ni .env.example"
        exit 1
    fi
fi

# ---- Cargar .env y generar valores faltantes ------------------
set -a
source .env 2>/dev/null || true
set +a

changed=false

if [[ -z "${APP_KEY:-}" ]]; then
    APP_KEY="base64:$(openssl rand -base64 32)"
    echo "APP_KEY=$APP_KEY" >> .env
    echo "[+] Generado APP_KEY"
    changed=true
fi

if [[ -z "${CRYPTO_PEPPER:-}" ]]; then
    CRYPTO_PEPPER="$(openssl rand -hex 32)"
    echo "CRYPTO_PEPPER=$CRYPTO_PEPPER" >> .env
    echo "[+] Generado CRYPTO_PEPPER"
    changed=true
fi

if [[ -z "${DB_PASSWORD:-}" ]]; then
    DB_PASSWORD="$(openssl rand -base64 18 | tr -d '/+=')"
    echo "DB_PASSWORD=$DB_PASSWORD" >> .env
    echo "[+] Generado DB_PASSWORD"
    changed=true
fi

if [[ -z "${DB_ROOT_PASSWORD:-}" ]]; then
    DB_ROOT_PASSWORD="$(openssl rand -hex 16)"
    echo "DB_ROOT_PASSWORD=$DB_ROOT_PASSWORD" >> .env
    echo "[+] Generado DB_ROOT_PASSWORD"
    changed=true
fi

if [[ -z "${DB_MIGRATION_PASSWORD:-}" ]]; then
    DB_MIGRATION_PASSWORD="$(openssl rand -hex 32)"
    echo "DB_MIGRATION_PASSWORD=$DB_MIGRATION_PASSWORD" >> .env
    echo "[+] Generado DB_MIGRATION_PASSWORD"
    changed=true
fi

if $changed; then
    echo "[!] Valores generados y guardados en .env"
    echo
fi

# ---- Detener contenedores anteriores -------------------------
echo "[1/3] Deteniendo contenedores anteriores..."
docker compose down 2>/dev/null || true

# ---- Levantar contenedores -----------------------------------
echo "[2/3] Compilando y levantando contenedores..."
docker compose up -d --build

# ---- Esperar a MySQL -----------------------------------------
echo "[3/3] Esperando a que MySQL este listo..."
for i in $(seq 1 30); do
    if docker compose exec -T db mysqladmin ping -h 127.0.0.1 --silent &>/dev/null; then
        echo "      MySQL listo."
        break
    fi
    sleep 2
done
sleep 3

echo
echo "=================================================="
echo "  Listo! Abre en tu navegador:"
echo "  https://localhost:8443"
echo
echo "  (Acepta la advertencia del certificado autofirmado)"
echo
echo "  Credenciales de prueba:"
echo "  - Admin 500:      admin500@haki.test  / Capitan#Haki-500!"
echo "  - Supervisor 499: visor499@haki.test  / Vigia#Haki-4999!"
echo "  - Estandar 300:   basico300@haki.test / Nakama#Haki-3000!"
echo "=================================================="
echo
echo "  Para detener: docker compose down"
