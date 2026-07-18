#!/usr/bin/env bash
set -euo pipefail

# Restauration d'une sauvegarde produite par backup-db.sh. Operation DESTRUCTIVE : elle ecrase le
# contenu de la base cible -> confirmation explicite obligatoire. AUCUN secret : le mot de passe root
# MySQL est lu depuis l'environnement du conteneur `db`.
#
# Usage : ./scripts/restore-db.sh <archive.sql.gz> [base_cible]
#   - base_cible : 2e ARGUMENT en priorite ; a defaut variable DB_NAME ; a defaut creapret_prod.
#   - Ex. preprod : ./scripts/restore-db.sh sauvegarde.sql.gz creapret_preprod

ARCHIVE=${1:-}
# La base cible vient du 2e ARGUMENT en priorite. Correctif d'incident : auparavant le 2e argument
# etait ignore et la restauration retombait sur creapret_prod par defaut -> une sauvegarde de
# preproduction a ete restauree en PRODUCTION, y installant des comptes de demo a identifiants publics.
DB_NAME=${2:-${DB_NAME:-creapret_prod}}
COMPOSE_FILE=${COMPOSE_FILE:-compose.prod.yml}
ENV_FILE=${ENV_FILE:-.env.deploy.local}
DB_SERVICE=${DB_SERVICE:-db}

cd "$(dirname "$0")/.."

# Detecte l'environnement (preprod|prod|"") encode dans une chaine. IMPORTANT : tester « preprod »
# AVANT « prod », car la sous-chaine « prod » est incluse dans « preprod ».
env_de() {
    case "$1" in
        *preprod*) printf 'preprod' ;;
        *prod*)    printf 'prod' ;;
        *)         printf '' ;;
    esac
}

# 1. Refuser toute execution sans archive valide (presente, non vide, .sql.gz).
if [ -z "$ARCHIVE" ]; then
    echo "Usage: $0 <archive.sql.gz> [base_cible]  (defaut base : DB_NAME sinon creapret_prod)" >&2
    exit 1
fi
[ -f "$ARCHIVE" ] || { echo "ERREUR: archive introuvable : $ARCHIVE" >&2; exit 1; }
[ -s "$ARCHIVE" ] || { echo "ERREUR: archive vide : $ARCHIVE" >&2; exit 1; }
case "$ARCHIVE" in
    *.sql.gz) ;;
    *) echo "ERREUR: archive attendue au format .sql.gz : $ARCHIVE" >&2; exit 1 ;;
esac

# 2. GARDE-FOU anti-incident : refuser une archive dont l'environnement (encode dans son nom, ex.
#    creapret_creapret_preprod_*.sql.gz) ne correspond PAS a la base cible. Empeche par accident de
#    restaurer une sauvegarde de preproduction en production (et l'inverse).
ARCH_ENV=$(env_de "$(basename "$ARCHIVE")")
CIBLE_ENV=$(env_de "$DB_NAME")
if [ -n "$ARCH_ENV" ] && [ -n "$CIBLE_ENV" ] && [ "$ARCH_ENV" != "$CIBLE_ENV" ]; then
    echo "ERREUR: incoherence d'environnement -- restauration REFUSEE." >&2
    echo "        Archive '$(basename "$ARCHIVE")' detectee comme : $ARCH_ENV" >&2
    echo "        Base cible '$DB_NAME' detectee comme            : $CIBLE_ENV" >&2
    echo "        Restaurer une sauvegarde '$ARCH_ENV' dans une base '$CIBLE_ENV' est interdit." >&2
    exit 1
fi

COMPOSE=(docker compose -f "$COMPOSE_FILE")
if [ -n "$ENV_FILE" ]; then
    COMPOSE+=(--env-file "$ENV_FILE")
fi

# 3. Confirmation EXPLICITE avant d'ecraser (defense contre l'erreur de manipulation).
echo "!! ATTENTION : cette operation VA ECRASER la base '$DB_NAME'"
echo "   avec l'archive : $ARCHIVE"
read -r -p "   Pour confirmer, taper exactement  RESTAURER $DB_NAME  : " REPONSE
if [ "$REPONSE" != "RESTAURER $DB_NAME" ]; then
    echo "Abandon : confirmation non conforme." >&2
    exit 1
fi

# 4. Restauration : decompression -> mysql. Mot de passe evalue DANS le conteneur ; $DB_NAME passe en
#    argument positionnel (pas d'interpolation cote hote).
echo ">>> Restauration en cours dans '$DB_NAME'..."
gunzip -c "$ARCHIVE" | "${COMPOSE[@]}" exec -T "$DB_SERVICE" \
    sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$1"' _ "$DB_NAME"

echo ">>> Restauration terminee dans '$DB_NAME'."

# 5. Rappel : le schema restaure peut etre anterieur au code deploye. Nom de service concret selon
#    l'environnement cible (creapret-app-preprod / creapret-app-prod).
SVC="creapret-app-${CIBLE_ENV:-<env>}"
echo ""
echo "RAPPEL : verifier l'etat des migrations et migrer si necessaire :"
echo "  ${COMPOSE[*]} exec -T $SVC php bin/console doctrine:migrations:status"
echo "  ${COMPOSE[*]} exec -T $SVC php bin/console doctrine:migrations:migrate --no-interaction"
