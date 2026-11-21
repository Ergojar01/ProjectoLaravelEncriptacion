#!/usr/bin/env bash
set -euo pipefail

if [[ $# != 2 || ! "$2" =~ ^laravel_restore_[a-z0-9_]{1,40}$ || ! -f "$1" ]]; then
    echo "Uso: bash docker/mysql/restore-lab.sh RESPALDO.sql.gpg laravel_restore_NOMBRE" >&2
    exit 2
fi

archive=$1
schema=$2
grant_schema=${schema//_/\\_}

mysql_as_root() {
    docker compose exec -T db sh -ec '
        export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"
        exec mysql --protocol=socket -u root "$@"
    ' sh "$@"
}

# CREATE sin IF NOT EXISTS impide importar encima de una base existente.
mysql_as_root -e "CREATE DATABASE \`$schema\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

restore_user="restore_$(openssl rand -hex 8)"
restore_password=$(openssl rand -hex 24)
trap 'mysql_as_root -e "DROP USER IF EXISTS '\''$restore_user'\''@'\''localhost'\''" >/dev/null' EXIT
mysql_as_root <<SQL
CREATE USER '$restore_user'@'localhost' IDENTIFIED BY '$restore_password';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON \`$grant_schema\`.* TO '$restore_user'@'localhost';
SQL
{ printf '%s\n' "$restore_password"; gpg --decrypt -- "$archive"; } |
    docker compose exec -T db sh -ec '
        IFS= read -r MYSQL_PWD
        export MYSQL_PWD
        exec mysql --protocol=socket --binary-mode -u "$1" --database="$2"
    ' sh "$restore_user" "$schema"
unset restore_password

violations=$(mysql_as_root --database="$schema" --batch --skip-column-names <<'SQL'
SELECT
    (SELECT COUNT(*) FROM users u LEFT JOIN roles r ON r.id = u.role_level WHERE r.id IS NULL)
  + (SELECT COUNT(*) FROM sessions s LEFT JOIN users u ON u.id = s.user_id WHERE s.user_id IS NOT NULL AND u.id IS NULL)
  + (SELECT COUNT(*) FROM password_resets p LEFT JOIN users u ON u.id = p.user_id WHERE u.id IS NULL)
  + (SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id WHERE a.actor_id IS NOT NULL AND u.id IS NULL)
  + (SELECT COUNT(*) FROM audit_logs a LEFT JOIN roles r ON r.id = a.actor_level WHERE r.id IS NULL)
  + (SELECT COUNT(*) FROM login_attempts a LEFT JOIN users u ON u.id = a.user_id WHERE a.user_id IS NOT NULL AND u.id IS NULL)
  + (SELECT COUNT(*) FROM account_locks l LEFT JOIN users u ON u.id = l.user_id WHERE l.user_id IS NOT NULL AND u.id IS NULL);
SQL
)
if [[ "$violations" != 0 ]]; then
    echo "La restauración contiene $violations referencias huérfanas." >&2
    exit 1
fi

mysql_as_root --database="$schema" --table <<'SQL'
SELECT
    (SELECT COUNT(*) FROM users) AS users,
    (SELECT COUNT(*) FROM roles) AS roles,
    (SELECT COUNT(*) FROM login_attempts) AS login_attempts,
    (SELECT COUNT(*) FROM account_locks) AS account_locks,
    (SELECT COUNT(*) FROM password_resets) AS password_resets,
    (SELECT COUNT(*) FROM audit_logs) AS audit_logs,
    (SELECT COUNT(*) FROM sessions) AS sessions;
SQL
echo "Restauración verificada en $schema. Referencias huérfanas: 0."
