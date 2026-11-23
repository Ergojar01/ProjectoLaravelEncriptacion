#!/usr/bin/env bash
# Respaldo CIFRADO de la base de datos (AES-256 con la frase BACKUP_PASSPHRASE del .env).
# Uso:  ./scripts/backup_db.sh            -> backups/laravel_roles_FECHA.sql.enc
#       ./scripts/backup_db.sh --plain    -> .sql sin cifrar (solo para el respaldo ficticio de entrega)
set -euo pipefail
cd "$(dirname "$0")/.."

env_get() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed 's/[[:space:]]*#.*$//; s/^"//; s/"$//'; }
ROOT_PASS="$(env_get DB_ROOT_PASSWORD)"; DB="$(env_get DB_DATABASE)"; export BACKUP_PASSPHRASE="$(env_get BACKUP_PASSPHRASE)"; PASS="$BACKUP_PASSPHRASE"
[ -n "$ROOT_PASS" ] || { echo "Falta DB_ROOT_PASSWORD en .env"; exit 1; }

mkdir -p backups && chmod 700 backups
STAMP="$(date +%Y%m%d_%H%M%S)"
DUMP=(docker compose exec -T -e MYSQL_PWD="$ROOT_PASS" db mysqldump -uroot --single-transaction --routines --no-tablespaces "$DB")

if [ "${1:-}" = "--plain" ]; then
  OUT="backups/${DB}_${STAMP}.sql"; "${DUMP[@]}" > "$OUT"
else
  [ -n "$PASS" ] || { echo "Falta BACKUP_PASSPHRASE en .env (o usa --plain)"; exit 1; }
  OUT="backups/${DB}_${STAMP}.sql.enc"
  "${DUMP[@]}" | openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_PASSPHRASE > "$OUT"
fi
chmod 600 "$OUT"; echo "Respaldo creado: $OUT"
