#!/usr/bin/env bash
set -euo pipefail
umask 077

if [[ $# != 2 ]]; then
    echo "Uso: bash scripts/backup_db.sh DESTINO.sql.gpg HUELLA_GPG" >&2
    exit 2
fi

destination=$1
recipient=$2
if [[ -e "$destination" || -L "$destination" ]]; then
    echo 'El archivo de destino ya existe.' >&2
    exit 2
fi

temporary=$(mktemp -- "${destination}.XXXXXX")
trap 'rm -f -- "$temporary"' EXIT

docker compose exec -T db sh -ec '
    export MYSQL_PWD="$LARAVEL_DB_PASSWORD"
    exec mysqldump --protocol=socket -u laravel_user --single-transaction         --skip-lock-tables --skip-add-locks --skip-disable-keys --no-tablespaces --set-gtid-purged=OFF --skip-triggers         "$MYSQL_DATABASE"
' | gpg --batch --yes --trust-model always --encrypt --recipient "$recipient" --output "$temporary"

ln -- "$temporary" "$destination"
echo "Copia cifrada creada en $destination"
