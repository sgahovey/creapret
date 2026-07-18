#!/usr/bin/env bash
set -euo pipefail

# Restauration d'une sauvegarde produite par backup-db.sh. Operation DESTRUCTIVE : elle
# ecrase le contenu de la base cible -> confirmation explicite obligatoire. AUCUN secret :
# le mot de passe root MySQL est lu depuis l'environnement du conteneur `db`.
#
# Usage : ./scripts/restore-db.sh <archive.sql.gz>
#         DB_NAME=creapret_preprod ./scripts/restore-db.sh <archive.sql.gz>

ARCHIVE=${1:-}
COMPOSE_FILE=${COMPOSE_FILE:-compose.prod.yml}
ENV_FILE=${ENV_FILE:-.env.deploy.local}
DB_SERVICE=${DB_SERVICE:-db}
DB_NAME=${DB_NAME:-creapret_prod}

cd "$(dirname "$0")/.."

# 1. Refuser toute execution sans archive valide (presente, non vide, .sql.gz).
if [ -z "$ARCHIVE" ]; then
    echo "Usage: $0 <archive.sql.gz>  (variable DB_NAME pour cibler une autre base)" >&2
    exit 1
fi
[ -f "$ARCHIVE" ] || { echo "ERREUR: archive introuvable : $ARCHIVE" >&2; exit 1; }
[ -s "$ARCHIVE" ] || { echo "ERREUR: archive vide : $ARCHIVE" >&2; exit 1; }
case "$ARCHIVE" in
    *.sql.gz) ;;
    *) echo "ERREUR: archive attendue au format .sql.gz : $ARCHIVE" >&2; exit 1 ;;
esac

COMPOSE=(docker compose -f "$COMPOSE_FILE")
if [ -n "$ENV_FILE" ]; then
    COMPOSE+=(--env-file "$ENV_FILE")
fi

# 2. Confirmation EXPLICITE avant d'ecraser.
echo "!! ATTENTION : cette operation VA ECRASER la base '$DB_NAME'"
echo "   avec l'archive : $ARCHIVE"
read -r -p "   Pour confirmer, taper exactement  RESTAURER $DB_NAME  : " REPONSE
if [ "$REPONSE" != "RESTAURER $DB_NAME" ]; then
    echo "Abandon : confirmation non conforme." >&2
    exit 1
fi

# 3. Restauration : decompression -> mysql. Mot de passe evalue DANS le conteneur ;
#    $DB_NAME passe en argument positionnel (pas d'interpolation cote hote).
echo ">>> Restauration en cours dans '$DB_NAME'..."
gunzip -c "$ARCHIVE" | "${COMPOSE[@]}" exec -T "$DB_SERVICE" \
    sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$1"' _ "$DB_NAME"

echo ">>> Restauration terminee dans '$DB_NAME'."
# 4. Rappel : le schema restaure peut etre anterieur au code deploye.
echo ""
echo "RAPPEL : verifier l'etat des migrations et migrer si necessaire :"
echo "  ${COMPOSE[*]} exec -T app-<env> php bin/console doctrine:migrations:status"
echo "  ${COMPOSE[*]} exec -T app-<env> php bin/console doctrine:migrations:migrate --no-interaction"
