#!/bin/sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
BACKUP_DIR="$ROOT_DIR/storage/backups"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"

if [ ! -f "$ENV_FILE" ]; then
  echo "Arquivo .env nao encontrado em $ENV_FILE" >&2
  exit 1
fi

# shellcheck disable=SC1090
set -a
. "$ENV_FILE"
set +a

: "${DB_HOST:?DB_HOST ausente}"
: "${DB_PORT:?DB_PORT ausente}"
: "${DB_NAME:?DB_NAME ausente}"
: "${DB_USER:?DB_USER ausente}"
: "${DB_PASS:?DB_PASS ausente}"

mkdir -p "$BACKUP_DIR"
OUT_SQL="$BACKUP_DIR/${DB_NAME}_${TIMESTAMP}.sql"
OUT_GZ="$OUT_SQL.gz"

if ! command -v mysqldump >/dev/null 2>&1; then
  echo "mysqldump nao encontrado. Instale o cliente MySQL no servidor." >&2
  exit 1
fi

MYSQL_PWD="$DB_PASS" mysqldump \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USER" \
  --single-transaction \
  --routines \
  --events \
  --triggers \
  --default-character-set=utf8mb4 \
  "$DB_NAME" > "$OUT_SQL"

gzip -f "$OUT_SQL"

if [ -f "$OUT_GZ" ]; then
  echo "Backup gerado: $OUT_GZ"
else
  echo "Falha ao gerar backup." >&2
  exit 1
fi
