-- Privilegios mínimos para la cuenta de la aplicación (ejecutar como root, DESPUÉS de migrar).
-- La app solo necesita leer y escribir datos; no crea ni borra tablas ni toca otras bases.
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'laravel_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON laravel_roles.* TO 'laravel_user'@'%';
FLUSH PRIVILEGES;
-- Para volver a migrar:  GRANT ALL PRIVILEGES ON laravel_roles.* TO 'laravel_user'@'%'; FLUSH PRIVILEGES;
