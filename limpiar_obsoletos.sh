#!/usr/bin/env bash
# Ejecutar desde la raíz del proyecto (laravel-roles-app). Borra vistas y clases que ya no se usan.
set -e
rm -fv resources/views/dashboard/admin.blade.php \
       resources/views/dashboard/basic.blade.php \
       resources/views/dashboard/viewer.blade.php \
       resources/views/dashboard/_overview.blade.php \
       resources/views/dashboard.blade.php \
       app/Http/Requests/ProfileUpdateRequest.php
rm -rfv resources/views/profile/partials
echo "Listo. Ahora ejecuta: php artisan optimize:clear (dentro del contenedor)"
