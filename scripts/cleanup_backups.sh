#!/bin/sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
BACKUP_DIR="$ROOT_DIR/storage/backups"
RETENTION_DAYS="${1:-14}"

if [ ! -d "$BACKUP_DIR" ]; then
  echo "Diretorio de backups nao existe: $BACKUP_DIR"
  exit 0
fi

find "$BACKUP_DIR" -type f -name '*.sql.gz' -mtime "+$RETENTION_DAYS" -print -delete

echo "Limpeza concluida (retencao: $RETENTION_DAYS dias)."
