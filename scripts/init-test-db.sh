#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Initialisation de la base de test locale (developpement).
#
# POURQUOI CE SCRIPT EXISTE EN PLUS DU FICHIER D'INIT :
# les scripts deposes dans /docker-entrypoint-initdb.d ne sont executes par
# l'image MySQL qu'a la PREMIERE initialisation, sur un volume de donnees
# VIERGE. Un poste dont le volume `mysql_data` existe deja (cas courant) ne les
# jouera jamais. Ce script applique donc le MEME fichier SQL -- il ne le
# duplique pas, il le redirige -- sur un conteneur `db` deja demarre.
#
# Idempotent : CREATE DATABASE IF NOT EXISTS, GRANT et SET GLOBAL sont
# rejouables, et doctrine:migrations:migrate ne rejoue pas les migrations deja
# appliquees. Le script peut donc etre relance sans effet de bord.
#
# Usage (WSL, a la racine du depot) : ./scripts/init-test-db.sh
# ---------------------------------------------------------------------------

# Le script vit dans scripts/ : se placer a la racine du depot, quel que soit
# le repertoire d'appel.
cd "$(dirname "$0")/.."

SQL_FILE="docker/mysql/init-dev.d/01-creer-base-de-test.sql"
DB_SERVICE="${DB_SERVICE:-db}"
APP_SERVICE="${APP_SERVICE:-app}"

[ -f "$SQL_FILE" ] || { echo "ERREUR: fichier introuvable : $SQL_FILE" >&2; exit 1; }

# 1. Appliquer le SQL en root sur le conteneur `db` deja demarre.
#    $MYSQL_ROOT_PASSWORD est en quotes SIMPLES -> evalue DANS le conteneur,
#    jamais cote hote : aucun secret ne transite par la ligne de commande.
echo ">>> Application de $SQL_FILE sur le service $DB_SERVICE"
docker compose exec -T "$DB_SERVICE" \
    sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD"' < "$SQL_FILE"

# 2. Jouer les migrations sur la base de test (Doctrine ajoute le suffixe _test
#    en APP_ENV=test, cf. config/packages/doctrine.yaml).
echo ">>> Migrations Doctrine sur la base de test ($APP_SERVICE, APP_ENV=test)"
docker compose exec -T -e APP_ENV=test "$APP_SERVICE" \
    php bin/console doctrine:migrations:migrate --no-interaction

echo ">>> OK : base de test prete."
