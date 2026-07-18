#!/usr/bin/env bash
set -euo pipefail

# Sauvegarde compressee d'une base CreaPret via mysqldump (coherent InnoDB grace a
# --single-transaction : pas de verrou bloquant l'application). Horodatee, compressee,
# avec rotation. AUCUN secret dans ce script : le mot de passe root MySQL est lu depuis
# l'ENVIRONNEMENT du conteneur `db` (MYSQL_ROOT_PASSWORD), jamais passe en argument.
#
# Usage PROD (defauts)      : ./scripts/backup-db.sh
# Base de preproduction     : DB_NAME=creapret_preprod ./scripts/backup-db.sh
# Usage DEV                 : COMPOSE_FILE=docker-compose.yml ENV_FILE= DB_NAME=creapret ./scripts/backup-db.sh

COMPOSE_FILE=${COMPOSE_FILE:-compose.prod.yml}
ENV_FILE=${ENV_FILE:-.env.deploy.local}
DB_SERVICE=${DB_SERVICE:-db}
DB_NAME=${DB_NAME:-creapret_prod}
BACKUP_DIR=${BACKUP_DIR:-$HOME/backups/creapret}
RETENTION_DAYS=${RETENTION_DAYS:-14}

# Le script vit dans scripts/ : se placer a la racine du repo.
cd "$(dirname "$0")/.."

# Prefixe compose ; --env-file seulement s'il est fourni (le mode DEV utilise un ENV_FILE vide).
COMPOSE=(docker compose -f "$COMPOSE_FILE")
if [ -n "$ENV_FILE" ]; then
    COMPOSE+=(--env-file "$ENV_FILE")
fi

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

OUT="$BACKUP_DIR/creapret_${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql.gz"
TMP="$OUT.part"
trap 'rm -f "$TMP"' EXIT

# Dump atomique. Avec pipefail, un echec de mysqldump fait echouer le pipe.
# $MYSQL_ROOT_PASSWORD en quotes SIMPLES -> evalue DANS le conteneur (jamais cote hote).
# $DB_NAME passe en argument positionnel ($1 du sh -c) -> aucune interpolation, pas d'injection.
"${COMPOSE[@]}" exec -T "$DB_SERVICE" \
    sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --no-tablespaces "$1"' _ "$DB_NAME" \
    | gzip -c > "$TMP"

# Un dump vide signale un echec silencieux : on refuse de le promouvoir.
[ -s "$TMP" ] || { echo "ERREUR: dump vide" >&2; exit 1; }

mv "$TMP" "$OUT"
chmod 600 "$OUT"

# Rotation : purge des sauvegardes au-dela de la retention.
find "$BACKUP_DIR" -name 'creapret_*.sql.gz' -type f -mtime +"$RETENTION_DAYS" -delete

echo "Sauvegarde OK : $OUT"
du -h "$OUT"
