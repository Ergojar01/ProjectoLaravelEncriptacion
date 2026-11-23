#!/usr/bin/env bash
# Borra archivos obsoletos de Laravel Breeze y de herramientas de desarrollo que ya no se usan.
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf CLAUDE.md AGENTS.md limpiar_obsoletos.sh routes/auth.php \
  app/Http/Controllers/Auth app/Http/Requests/Auth app/Http/Middleware/ForcePasswordChange.php \
  resources/views/auth/register.blade.php resources/views/auth/forgot-password.blade.php \
  resources/views/auth/reset-password.blade.php resources/views/auth/verify-email.blade.php \
  resources/views/auth/confirm-password.blade.php resources/views/welcome.blade.php \
  tests/Feature/Auth tests/Feature/ProfileTest.php tests/Feature/ExampleTest.php
echo "Archivos obsoletos eliminados."
