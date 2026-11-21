# MySQL ejecuta este archivo en su shell de inicialización.
configure_laravel_privileges() {
    if [[ ! "${DB_MIGRATION_PASSWORD:-}" =~ ^[[:xdigit:]]{32,}$ ]]; then
        echo 'DB_MIGRATION_PASSWORD debe contener al menos 32 caracteres hexadecimales.' >&2
        return 1
    fi

    local escaped_password=${LARAVEL_DB_PASSWORD//\'/\'\'}
    MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -u root <<SQL
SET SESSION sql_mode = CONCAT_WS(',', @@sql_mode, 'NO_BACKSLASH_ESCAPES');
DROP USER IF EXISTS 'root'@'%';
CREATE USER IF NOT EXISTS 'laravel_user'@'%' IDENTIFIED BY '$escaped_password';
ALTER USER 'laravel_user'@'%' IDENTIFIED BY '$escaped_password';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'laravel_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`laravel\_roles\`.* TO 'laravel_user'@'%';
CREATE USER IF NOT EXISTS 'laravel_migrator'@'%' IDENTIFIED BY '$DB_MIGRATION_PASSWORD';
ALTER USER 'laravel_migrator'@'%' IDENTIFIED BY '$DB_MIGRATION_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON \`laravel\_roles\`.* TO 'laravel_migrator'@'%';
SQL
}
configure_laravel_privileges
