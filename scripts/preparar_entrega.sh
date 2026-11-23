#!/usr/bin/env bash
set -euo pipefail
umask 077
cd "$(dirname "$0")/.."
out="../entrega_haki_$(date +%Y%m%d).zip"

if git ls-tree -r --name-only HEAD | grep -vEx '(\.env\.example|scripts/privilegios_minimos\.sql)' |
    grep -Ei '(^|/)\.env([./]|$)|\.(sql(\..*)?|enc|gpg|pem|key|sqlite([-].*)?)$'; then
    echo 'La revisión contiene un archivo de entorno, clave o respaldo.' >&2
    exit 1
fi

git archive --format=zip --output="$out" HEAD -- . ':(exclude,glob)**/*Zone.Identifier*'
echo "Creado: $out"
