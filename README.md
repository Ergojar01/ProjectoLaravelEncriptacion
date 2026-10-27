# SecureAccess - Sistema de Gestión en Laravel

Plataforma de gestión de usuarios con control de acceso por niveles, credenciales protegidas criptográficamente y enmascaramiento de rutas.

## Características Principales
- **Seguridad Criptográfica:** Contraseñas protegidas mediante hash avanzado con una *salt* aleatoria única de 256 bits por usuario, *pepper* secreta almacenada fuera de la base de datos y control de versiones criptográficas.
- **Control de Roles (RBAC):**
  - **Nivel 500 (Administrador):** Control total, creación y gestión de usuarios.
  - **Nivel 499 (Visor):** Acceso de solo lectura y panel de estadísticas.
  - **Nivel 300 (Básico):** Acceso estándar al panel.
- **Enmascaramiento de URL (URL Masking):** Navegación interna blindada mediante un portal ciego (Iframe) en `/portal` que congela la barra de direcciones para ocultar la estructura interna de rutas (`/sistema`, `/admin/usuarios`).
- **Entorno Dockerizado:** Infraestructura completa con contenedores optimizados para PHP/Laravel, servidor web, base de datos MySQL y automatización de NPM (Node).
- **Interfaz Consistente:** Paneles adaptados por rol con tarjetas de bienvenida y estadísticas dinámicas.

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
*(Asegúrate de configurar los datos de conexión a la base de datos y la variable de encriptación en el `.env`).*

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