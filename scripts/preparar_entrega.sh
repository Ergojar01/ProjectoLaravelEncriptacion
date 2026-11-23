#!/usr/bin/env bash
# Crea el ZIP de entrega SIN secretos: sin .env, sin vendor/node_modules, sin logs ni respaldos reales.
set -euo pipefail
cd "$(dirname "$0")/.."
OUT="../entrega_haki_$(date +%Y%m%d).zip"
rm -f "$OUT"
zip -qr "$OUT" . \
  -x ".env" -x ".env.save" -x ".env.backup" -x "vendor/*" -x "node_modules/*" -x ".git/*" \
  -x "storage/logs/*" -x "storage/framework/*" -x "backups/*" -x "public/build/*" -x "*Zone.Identifier"
echo "Creado: $OUT"
echo "Comprobando que no incluye secretos..."
if unzip -l "$OUT" | grep -E '(^|/)\.env$' ; then echo "ERROR: el zip contiene .env"; exit 1; fi
echo "OK: sin .env. Recuerda incluir las capturas en docs/evidencias/."
