# One Piece Haki Encryption — Autenticación segura y control de acceso

Aplicación web en **PHP 8.3 / Laravel 13 / MySQL 8** (Docker + Apache con HTTPS) para el proyecto final de Encriptación de Datos.

## Credenciales de prueba (FICTICIAS, solo para la evaluación)
| Nivel | Usuario (correo) | Contraseña |
|---|---|---|
| 500 Administrador | `admin500@haki.test` | `Capitan#Haki-500!` |
| 499 Supervisor | `visor499@haki.test` | `Vigia#Haki-4999!` |
| 300 Estándar | `basico300@haki.test` | `Nakama#Haki-3000!` |

## Instalación y ejecución
```bash
cp .env.example .env
# Completa en .env: DB_PASSWORD, DB_ROOT_PASSWORD, BACKUP_PASSPHRASE y CRYPTO_PEPPER (openssl rand -hex 32)
docker compose up -d --build          # la primera vez tarda (npm install + build)
docker exec -it laravel_roles_app composer install
docker exec -it laravel_roles_app php artisan key:generate
docker exec -it -u www-data laravel_roles_app php artisan migrate --force --seed
```
Abre **https://localhost** (certificado autofirmado de laboratorio: acepta la advertencia del navegador). El puerto 80 solo redirige a HTTPS.
Si algo se ve desactualizado: `docker exec -it -u www-data laravel_roles_app php artisan optimize:clear`.

> Si `docker compose up` ya se ejecutó antes con otras claves de MySQL, la base conserva las claves originales: `docker compose down -v` la recrea desde cero (borra datos).

## Controles de seguridad implementados
| Requisito del PDF | Implementación |
|---|---|
| Hash con sal (4.2) | **Argon2id** (`Hash::make/check` de Laravel, sal aleatoria única por hash) sobre `Base64(HMAC-SHA512(clave, pimienta))`. Cuentas antiguas v1 migran solas al iniciar sesión (`app/Services/Credentials.php`). |
| Intentos y bloqueo (4.1) | 3 fallos en 3 min → bloqueo de 3 min, por **cuenta + IP**; además límite por cuenta (10, cualquier IP) y global por origen (20). Se guarda en MySQL (`login_attempts`, `account_locks`): sobrevive a recargas, otros navegadores, sesiones nuevas y `cache:clear`. Mensaje genérico; mismo tiempo de respuesta si el usuario no existe. |
| Niveles 500/499/300 (4.3) | Middleware `role:N` en cada ruta, validado en el servidor. 500 administra; 499 consulta usuarios y bitácora; 300 solo su perfil. El 500 no se asigna desde el sistema. |
| Hashes (4.3 *) | Solo el 500, con confirmación de su contraseña, solo lectura, sin exportar y auditado (`hash_viewed`). |
| Cambio de clave (5) | Actual + nueva + confirmación; mínimo 12; rechaza contraseñas comunes/filtradas; invalida las demás sesiones; nada se registra en logs. |
| Recuperación (5) | La persona solicita → el 500 **autoriza** → enlace de **un solo uso** con vencimiento (30 min); solo se guarda el hash del token; la persona define su clave y el admin nunca la conoce. |
| URL única (6) | Portal `/Dashboard` con iframe: la barra no cambia. Ver justificación abajo. |
| MySQL (7) | Eloquent/Query Builder (consultas parametrizadas), cuenta sin root, **3306 no publicado**, credenciales en `.env`, migraciones con claves foráneas, tablas `roles`, `login_attempts`, `account_locks`, `password_resets`, `audit_logs`, `sessions`. Privilegios mínimos opcionales: `scripts/privilegios_minimos.sql`. |
| Sesiones (8) | Regenera ID al entrar, invalida al salir, 30 min de inactividad, expira al cerrar el navegador, cookies HttpOnly + Secure + SameSite=Strict, sesión cifrada, sin "recordarme". |
| CSRF / XSS (8) | `@csrf` en todos los formularios (419 si falta); Blade escapa toda salida; CSP `script-src 'self'`; sin `{!! !!}`. |
| HTTPS (8) | Apache en 443, HTTP redirige, HSTS. `APP_DEBUG=false`, `APP_ENV=production`. |
| Auditoría (9) | Tabla `audit_logs` (fecha/hora, usuario, evento, resultado, IP). Panel **Bitácora** para 499 y 500. Nunca guarda contraseñas, tokens ni cookies. |

### Justificación de la URL única (sección 6)
La barra de direcciones siempre muestra `/Dashboard`; las pantallas internas (`/sistema`, `/perfil`, `/admin/...`) se cargan dentro de un `iframe` del mismo origen. **La URL fija no es una medida de seguridad**: cada solicitud interna pasa por `auth` + `active` + `role` en el servidor, tiene CSRF y las rutas son fijas (no se cargan archivos ni rutas por parámetro). Si alguien escribe `/admin/usuarios` a mano, el middleware `framed` lo devuelve al portal y, aunque lo evadiera, un 300 recibe 403.

## Respaldos
```bash
./scripts/backup_db.sh                       # respaldo CIFRADO (AES-256) en backups/
./scripts/restore_db.sh backups/ARCHIVO.enc  # restaura y lista los usuarios para comprobar
./scripts/backup_db.sh --plain               # SQL sin cifrar (solo para el respaldo ficticio de entrega)
```
Respaldo de entrega con usuarios ficticios: ejecuta `migrate:fresh --seed`, luego `--plain`, y copia el `.sql` a `database/backup/`.

## Pruebas
- Automáticas: `docker exec -it laravel_roles_app php artisan test` (cubre las pruebas de la sección 10 salvo la restauración).
- Documentadas con capturas: `docs/PRUEBAS_SEGURIDAD.md` (guarda las imágenes en `docs/evidencias/`).
- Sustentación: `docs/SUSTENTACION.md`.

## Entrega
`./scripts/preparar_entrega.sh` genera el ZIP **sin `.env`**, sin `vendor/` y sin secretos.
