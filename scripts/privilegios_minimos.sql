-- Para aprovisionar cuentas y restringir root a localhost, use el script
-- docker/mysql/10-privileges.sh según el procedimiento del README.
SHOW GRANTS FOR 'laravel_user'@'%';
SHOW GRANTS FOR 'laravel_migrator'@'%';
