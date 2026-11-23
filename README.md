# One Piece Haki Encryption — Autenticación segura y control de acceso

Aplicación web en **PHP 8.4 / Laravel 13 / MySQL 8** (Docker + Apache con HTTPS) para el proyecto final de Encriptación de Datos.

## Credenciales de prueba (FICTICIAS, solo para la evaluación)
| Nivel | Usuario (correo) | Contraseña |
|---|---|---|
| 500 Administrador | `admin500@haki.test` | `Capitan#Haki-500!` |
| 499 Supervisor | `visor499@haki.test` | `Vigia#Haki-4999!` |
| 300 Estándar | `basico300@haki.test` | `Nakama#Haki-3000!` |

## Instalación y ejecución

Copia `.env.example` a `.env` solo si es una instalación nueva. Configura `DB_PASSWORD` y `CRYPTO_PEPPER` en el `.env` de Laravel. Para cuentas existentes, conserva el `APP_KEY`, `CRYPTO_PEPPER` y los parámetros criptográficos originales. Guarda las credenciales privilegiadas fuera del directorio montado por el contenedor de la aplicación.

```bash
chmod 600 .env
install -d -m 700 "$HOME/.config/secureaccess"
(umask 077; touch "$HOME/.config/secureaccess/mysql.env")
chmod 600 "$HOME/.config/secureaccess/mysql.env"
export COMPOSE_ENV_FILES=".env,$HOME/.config/secureaccess/mysql.env"
```

Completa en `mysql.env` las variables `DB_ROOT_PASSWORD` y `DB_MIGRATION_PASSWORD`. La primera debe coincidir con la contraseña root original si el volumen ya existe. Para credenciales nuevas usa un valor independiente de `openssl rand -hex 32` para cada variable. Mueve al archivo privado las credenciales privilegiadas que estuvieran antes en `.env`.

```bash
docker compose up -d --build --wait db
docker compose build app
docker compose run --rm --no-deps app composer install
# Solo en una instalación nueva
docker compose run --rm --no-deps app php artisan key:generate --force
```

Para un **volumen existente**, aprovisiona las cuentas con `docker compose exec -T db bash /docker-entrypoint-initdb.d/10-privileges.sh`. Este paso conserva el volumen y retira el usuario remoto `root@%`. La aplicación usa `laravel_user` con permisos DML, y la cuenta `laravel_migrator` ejecuta las migraciones.

```bash
(
  read -rsp 'Contraseña de laravel_migrator: ' DB_PASSWORD
  export DB_PASSWORD
  echo
  docker compose run --rm --no-deps -e DB_USERNAME=laravel_migrator -e DB_PASSWORD app \
    php artisan migrate --force
)
# Solo en la base aislada de evaluación
docker compose run --rm --no-deps -e SECURITY_DEMO_SEED=true app php artisan db:seed --force
docker compose up -d app
```

Abre **https://localhost**. El certificado autofirmado se limita al laboratorio. Configura un transporte SMTP para enviar enlaces de recuperación. En un laboratorio sin SMTP, `RESET_SHOW_LINK=true` muestra el enlace de prueba al administrador. Mantén esta opción desactivada con cuentas reales.

## Controles de seguridad implementados
| Requisito del PDF | Implementación |
|---|---|
| Hash con sal (4.2) | **Argon2id** (`Hash::make/check` de Laravel, sal aleatoria única por hash) sobre `Base64(HMAC-SHA512(clave, pimienta))`. Cuentas antiguas v1 migran solas al iniciar sesión (`app/Services/Credentials.php`). |
| Intentos y bloqueo (4.1) | 3 fallos en 3 min → bloqueo de 3 min, por **cuenta + IP**; además límite por cuenta (10, cualquier IP) y global por origen (20). Se guarda en MySQL (`login_attempts`, `account_locks`): sobrevive a recargas, otros navegadores, sesiones nuevas y `cache:clear`. Mensaje genérico; las cuentas inexistentes también ejecutan una operación Argon2id. |
| Niveles 500/499/300 (4.3) | Middleware `role:N` en cada ruta, validado en el servidor. 500 administra; 499 consulta usuarios y bitácora; 300 solo su perfil. El 500 no se asigna desde el sistema. |
| Hashes (4.3 *) | Solo el 500, con confirmación de su contraseña, solo lectura, sin exportar y auditado (`hash_viewed`). |
| Cambio de clave (5) | Actual + nueva + confirmación; mínimo 12; rechaza contraseñas comunes/filtradas; invalida las demás sesiones; nada se registra en logs. |
| Recuperación (5) | La persona solicita → el 500 **autoriza** → enlace de **un solo uso** con vencimiento (30 min); solo se guarda el hash del token; la persona define su clave y el admin nunca la conoce. |
| URL única (6) | Portal `/Dashboard` con iframe: la barra no cambia. Ver justificación abajo. |
| MySQL (7) | Eloquent/Query Builder (consultas parametrizadas), cuenta sin root, **3306 no publicado**, credenciales en `.env`, migraciones con claves foráneas, tablas `roles`, `login_attempts`, `account_locks`, `password_resets`, `audit_logs`, `sessions`. Los permisos mínimos se aplican durante el aprovisionamiento. |
| Sesiones (8) | Regenera ID al entrar, invalida al salir, 30 min de inactividad, expira al cerrar el navegador, cookies HttpOnly + Secure + SameSite=Strict, sesión cifrada, sin "recordarme". |
| CSRF / XSS (8) | `@csrf` en todos los formularios (419 si falta); Blade escapa toda salida; CSP `script-src 'self'`; sin `{!! !!}`. |
| HTTPS (8) | Apache en 443, HTTP redirige, HSTS. `APP_DEBUG=false`, `APP_ENV=production`. |
| Auditoría (9) | Tabla `audit_logs` (fecha/hora, usuario, evento, resultado, IP). Panel **Bitácora** para 499 y 500. Nunca guarda contraseñas, tokens ni cookies. |

### Justificación de la URL única (sección 6)
La barra de direcciones siempre muestra `/Dashboard`; las pantallas internas (`/sistema`, `/perfil`, `/admin/...`) se cargan dentro de un `iframe` del mismo origen. **La URL fija no es una medida de seguridad**: cada solicitud interna pasa por `auth` + `active` + `role` en el servidor, tiene CSRF y las rutas son fijas (no se cargan archivos ni rutas por parámetro). Una petición de nivel 300 a `/admin/usuarios` devuelve 403. El redireccionamiento visual al portal se aplica después de autorizar el nivel.

## Respaldos

Se necesita una clave GPG de confianza, con la clave privada disponible en el entorno de restauración. El archivo es cifrado directamente desde `mysqldump`. Restaura únicamente archivos de origen conocido en un esquema nuevo. El script verifica integridad referencial y muestra conteos agregados.

```bash
mkdir -m 700 -p backups
gpg --list-keys --fingerprint
read -r -p 'Huella de la clave GPG: ' BACKUP_GPG_FINGERPRINT
bash scripts/backup_db.sh backups/laravel-lab.sql.gpg "$BACKUP_GPG_FINGERPRINT"
bash scripts/restore_db.sh backups/laravel-lab.sql.gpg laravel_restore_ensayo1
```

## Pruebas

La suite usa SQLite en memoria con el comando siguiente. Usa una instancia MySQL separada y desechable para repetirla con `RefreshDatabase` en MySQL. El [plan de evidencias](docs/PRUEBAS_SEGURIDAD.md) incluye los pasos del laboratorio.

```bash
docker compose run --rm --no-deps \
  -e APP_ENV=testing -e CACHE_STORE=array -e SESSION_DRIVER=array -e FORCE_HTTPS=false \
  -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= app \
  php vendor/bin/phpunit
```

Para repetir la suite en **MySQL**, crea un proyecto Compose exclusivo para las pruebas. `RefreshDatabase` reconstruye las tablas del esquema de laboratorio.

```bash
docker compose -p haki-pruebas up -d --build --wait db
(
  read -rsp 'Contraseña de laravel_migrator: ' DB_PASSWORD
  export DB_PASSWORD
  echo
  docker compose -p haki-pruebas run --rm --no-deps \
    -e APP_ENV=testing -e CACHE_STORE=array -e SESSION_DRIVER=array -e FORCE_HTTPS=false \
    -e DB_USERNAME=laravel_migrator -e DB_PASSWORD -e DB_URL= app php vendor/bin/phpunit
)
```

## Entrega

`bash scripts/preparar_entrega.sh` empaqueta los archivos de la revisión actual de Git.
