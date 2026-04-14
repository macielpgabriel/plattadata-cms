#!/bin/sh
set -eu

if [ "$#" -lt 1 ]; then
  echo "Uso: sh scripts/restore_db.sh /caminho/backup.sql.gz" >&2
  exit 1
fi

BACKUP_FILE="$1"
ROOT_DIR="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"

if [ ! -f "$ENV_FILE" ]; then
  echo "Arquivo .env nao encontrado em $ENV_FILE" >&2
  exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
  echo "Backup nao encontrado: $BACKUP_FILE" >&2
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

if ! command -v mysql >/dev/null 2>&1; then
  echo "mysql client nao encontrado." >&2
  exit 1
fi

EXT="${BACKUP_FILE##*.}"
if [ "$EXT" = "gz" ]; then
  gunzip -c "$BACKUP_FILE" | MYSQL_PWD="$DB_PASS" mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" "$DB_NAME"
else
  MYSQL_PWD="$DB_PASS" mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" "$DB_NAME" < "$BACKUP_FILE"
fi

echo "Restore concluido para banco $DB_NAME"
