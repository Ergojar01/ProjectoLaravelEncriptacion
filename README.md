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

## Requisitos Previos
- Docker y Docker Compose
- WSL 2 (Windows Subsystem for Linux)

## Instalación y Despliegue Local

1. Clonar el repositorio:
```bash
git clone [https://github.com/Ergojar01/ProjectoLaravelEncriptacion.git](https://github.com/Ergojar01/ProjectoLaravelEncriptacion.git)
cd ProjectoLaravelEncriptacion
```

2. Configurar las variables de entorno:
```bash
cp .env.example .env
```
Configura la conexión a la base de datos en el archivo local `.env`. Si existen cuentas con el esquema anterior, conserva su `CRYPTO_PEPPER` y `CRYPTO_ITERATIONS` originales en la configuración privada hasta completar la migración.

3. Levantar los contenedores:
```bash
docker compose up -d --build
```

4. Preparar el backend de Laravel:
```bash
# Entrar al contenedor
docker exec -it laravel_roles_app bash

# Instalar dependencias, generar clave y migrar base de datos
composer install
php artisan key:generate
php artisan migrate

# Limpiar caché y salir
php artisan optimize:clear
exit
```