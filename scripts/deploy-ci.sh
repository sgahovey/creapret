#!/usr/bin/env bash
set -euo pipefail

# Script de deploiement invoque par le pipeline CI/CD via SSH (forced command).
# La cle SSH de deploiement est restreinte a CE script dans authorized_keys du VPS :
#   command="/chemin/creapret/scripts/deploy-ci.sh",no-pty,... <cle publique>
# Le pipeline appelle : ssh deploy@vps "<env> <tag>"
#   -> $SSH_ORIGINAL_COMMAND = "<env> <tag>" (aucune autre commande possible : pas de shell).

export PATH="/usr/local/bin:/usr/bin:/bin:$PATH"

# 1. Lire et VALIDER strictement l'entree (env + tag) depuis SSH_ORIGINAL_COMMAND.
#    Aucune interpolation shell d'entree externe : env dans une liste fermee, tag = SHA hexa.
read -r ENV TAG _ <<< "${SSH_ORIGINAL_COMMAND:-}"

case "$ENV" in
  preprod|prod) ;;
  *) echo "ERREUR: environnement invalide ('$ENV'). Attendu: preprod | prod." >&2; exit 1 ;;
esac

if ! [[ "$TAG" =~ ^[0-9a-f]{7,40}$ ]]; then
  echo "ERREUR: tag invalide ('$TAG'). Attendu: SHA hexadecimal (7-40 car.)." >&2
  exit 1
fi

# 2. Contexte projet (racine du depot, relative a ce script) + prefixe compose.
cd "$(dirname "$0")/.."
PFX=(docker compose -f compose.prod.yml --env-file .env.deploy.local)

# 3. Variable de tag selon l'environnement + services vises.
if [ "$ENV" = "preprod" ]; then
  export PREPROD_IMAGE_TAG="$TAG"
else
  export PROD_IMAGE_TAG="$TAG"
fi
APP="app-$ENV"
WORKER="worker-$ENV"

echo ">>> Deploiement $ENV @ $TAG"

# 4. Synchroniser le working tree sur le commit deploye ($TAG). Sinon les fichiers
#    d'orchestration versionnes (compose.prod.yml, Caddyfile, init-prod.sh) resteraient
#    sur un ancien commit. $TAG est deja valide par la regex ^[0-9a-f]{7,40}$ (pas d'injection).
echo ">>> Synchronisation du depot sur $TAG"
git fetch --quiet origin
git reset --hard --quiet "$TAG"
echo ">>> Depot synchronise sur $(git rev-parse --short HEAD)"

# 5. Tirer l'image GHCR (tag precis) et recreer UNIQUEMENT les services de cet env.
"${PFX[@]}" pull "$APP" "$WORKER"
"${PFX[@]}" up -d "$APP" "$WORKER"

# 6. Migrations Doctrine — sur UN SEUL service applicatif ("$APP"), JAMAIS un worker :
#    l'image etant partagee, migrer aussi le worker lancerait deux migrateurs concurrents
#    sur la meme base (course). Idempotent : ne rejoue pas les migrations deja appliquees.
"${PFX[@]}" exec -T "$APP" php bin/console doctrine:migrations:migrate --no-interaction

# 7. Verification finale que le service repond : on boote le kernel prod DANS le conteneur
#    deploye (about echoue si l'app ne demarre pas). Le smoke HTTP via Caddy pourra s'ajouter
#    une fois les blocs de site du proxy configures.
echo ">>> Verification que le service repond ($APP)"
if ! "${PFX[@]}" exec -T "$APP" php bin/console about >/dev/null 2>&1; then
  echo "ERREUR: le service $APP ne repond pas (php bin/console about a echoue)." >&2
  exit 1
fi

echo ">>> OK: $ENV deploye en $TAG."
