# SecureAccess - Sistema de Gestión en Laravel

Plataforma de gestión de usuarios con control de acceso por niveles, credenciales protegidas criptográficamente y enmascaramiento de rutas.

## Características Principales
- **Seguridad Criptográfica:** Las nuevas contraseñas y claves temporales se guardan con Argon2id mediante Laravel. Los hashes de la versión anterior se verifican con su configuración original y se migran al iniciar sesión correctamente o cambiar la contraseña.
- **Control de Roles (RBAC):**
  - **Nivel 500 (Administrador):** Control total, creación y gestión de usuarios.
  - **Nivel 499 (Visor):** Acceso de solo lectura y panel de estadísticas.
  - **Nivel 300 (Básico):** Acceso estándar al panel.
- **Enmascaramiento de URL (URL Masking):** Navegación interna blindada mediante un portal ciego (Iframe) en `/portal` que congela la barra de direcciones para ocultar la estructura interna de rutas (`/sistema`, `/admin/usuarios`).
- **Entorno Dockerizado:** Infraestructura completa con contenedores optimizados para PHP/Laravel, servidor web, base de datos MySQL y automatización de NPM (Node).
- **Interfaz Consistente:** Paneles adaptados por rol con tarjetas de bienvenida y estadísticas dinámicas.

## Comprobación de contraseñas nuevas

El registro, el alta administrativa, el cambio de contraseña y la asignación de claves temporales comparan la contraseña y sus variantes normalizadas con una tabla local de hashes SHA-256 en `config/password_blocklist.php`. La verificación funciona sin conexión. Solo detecta entradas de la tabla y las variantes que coincidan tras la normalización. Para ampliarla, añade como claves los SHA-256 en minúsculas de contraseñas públicas conocidas o de sus bases normalizadas y vuelve a generar la caché de configuración si está habilitada. Las contraseñas de las cuentas se siguen almacenando con Argon2id.

## Laboratorio MySQL

Requiere Docker Compose, OpenSSL y GnuPG. Copia `.env.example` a `.env` en una instalación nueva. Genera tres contraseñas distintas con `openssl rand -hex 24`. Guarda `DB_PASSWORD` en `.env` y las variables `DB_ROOT_PASSWORD` y `DB_MIGRATION_PASSWORD` en el archivo privado de Compose fuera de la carpeta montada en la aplicación. Si ya estaban en `.env`, muévelas. Conserva el `APP_KEY`, `CRYPTO_PEPPER` y `CRYPTO_ITERATIONS` de una instalación existente.

```bash
chmod 600 .env
install -d -m 700 "$HOME/.config/secureaccess"
(umask 077; touch "$HOME/.config/secureaccess/mysql.env")
chmod 600 "$HOME/.config/secureaccess/mysql.env"
# Guarda DB_ROOT_PASSWORD y DB_MIGRATION_PASSWORD en mysql.env antes de continuar.
export COMPOSE_ENV_FILES=".env,$HOME/.config/secureaccess/mysql.env"
docker compose up -d --wait db
docker compose build app
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate  # solo instalación nueva
(
  read -rsp 'Contraseña de laravel_migrator: ' DB_PASSWORD
  export DB_PASSWORD
  echo
  docker compose run --rm --no-deps -e DB_USERNAME=laravel_migrator -e DB_PASSWORD app php artisan migrate --force
)
docker compose up -d app
```

La aplicación usa `laravel_user` con `SELECT, INSERT, UPDATE, DELETE`. El migrador tiene permisos DDL sobre el esquema de la aplicación. MySQL está en una red interna sin puertos publicados. Root se usa por socket local para aprovisionamiento y restauración. Con un volumen previo, ejecuta el aprovisionamiento tras recrear el servicio con la configuración nueva y antes de arrancar la aplicación. El procedimiento elimina el usuario remoto `root@%`. Conserva el volumen y usa la contraseña root con la que se inicializó.

```bash
docker compose up -d --wait db
docker compose exec -T db bash /docker-entrypoint-initdb.d/10-privileges.sh
```

### Pruebas de seguridad

Con dependencias instaladas y `APP_KEY` configurada, ejecuta la selección sobre SQLite aislado en memoria.

```bash
docker compose run --rm --no-deps -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= app \
  php vendor/bin/phpunit --filter 'AuthenticationTest|SecurityControlsTest|AccessMatrixTest|PasswordResetTest|PasswordUpdateTest|ProfileTest'
```

Para ejecutar la misma selección en MySQL, usa un proyecto Compose de laboratorio nuevo con su propio volumen y la cuenta `laravel_migrator`. PHPUnit ejecuta migraciones desde cero, por lo que esta instancia debe ser desechable.

```bash
docker compose -p laravel-security-lab up -d --wait db
(
  read -rsp 'Contraseña de laravel_migrator: ' DB_PASSWORD
  export DB_PASSWORD
  echo
  docker compose -p laravel-security-lab run --rm --no-deps \
    -e DB_USERNAME=laravel_migrator -e DB_PASSWORD -e DB_URL= app \
    php vendor/bin/phpunit --filter 'AuthenticationTest|SecurityControlsTest|AccessMatrixTest|PasswordResetTest|PasswordUpdateTest|ProfileTest'
)
```

### Copia cifrada y restauración

Usa la huella completa de una clave GPG de confianza cuya clave privada esté disponible en el laboratorio de restauración. Ejecuta desde la raíz del proyecto con una base ya migrada. La copia se cifra directamente desde `mysqldump`. La restauración exige un esquema nuevo con prefijo `laravel_restore_`, importa con una cuenta temporal limitada a ese esquema y verifica los conteos y las referencias foráneas. Usa un nombre diferente para cada ensayo. Restaura solo archivos de origen conocido.

```bash
mkdir -m 700 -p backups
gpg --list-keys --fingerprint
read -r -p 'Huella de la clave GPG: ' BACKUP_GPG_FINGERPRINT
bash docker/mysql/backup.sh backups/laravel-lab.sql.gpg "$BACKUP_GPG_FINGERPRINT"
bash docker/mysql/restore-lab.sh backups/laravel-lab.sql.gpg laravel_restore_ensayo1
```

El esquema restaurado se conserva para inspección. Un fallo de importación puede dejar un esquema parcial. Guarda las claves privadas y la configuración necesaria para acceder a credenciales heredadas fuera de la copia de datos.
