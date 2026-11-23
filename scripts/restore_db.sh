#!/usr/bin/env bash
# Restaura un respaldo (.sql o .sql.enc) en la base de datos y comprueba que quedó bien.
# Uso: ./scripts/restore_db.sh backups/archivo.sql.enc
set -euo pipefail
cd "$(dirname "$0")/.."
FILE="${1:?Indica el archivo de respaldo}"

env_get() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed 's/[[:space:]]*#.*$//; s/^"//; s/"$//'; }
ROOT_PASS="$(env_get DB_ROOT_PASSWORD)"; DB="$(env_get DB_DATABASE)"; export BACKUP_PASSPHRASE="$(env_get BACKUP_PASSPHRASE)"

read_dump() { case "$FILE" in *.enc) openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_PASSPHRASE -in "$FILE";; *) cat "$FILE";; esac; }
SQL=(docker compose exec -T -e MYSQL_PWD="$ROOT_PASS" db mysql -uroot)

"${SQL[@]}" -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
read_dump | "${SQL[@]}" "$DB"
echo "Restauración terminada. Usuarios en la base:"
"${SQL[@]}" "$DB" -e "SELECT id, email, role_level, is_active FROM users;"
