#!/bin/sh
# Entrypoint runtime : synchronise les assets compiles bakes dans l'image (public/) vers un volume
# partage que le proxy (Caddy) sert en file_server. Resync a CHAQUE boot -> robuste aux mises a jour
# d'image (un volume nomme seul resterait fige sur l'ancienne version des assets).
#
# Partage des responsabilites (fidele a CreaSlot) :
#  - MIGRATIONS : PAS ici. Lancees par l'etape de deploiement, sur UN SEUL service applicatif
#    (doctrine:migrations:migrate). Motifs : un conteneur qui redemarre ne doit pas rejouer le schema ;
#    une migration en echec doit interrompre le deploiement, pas faire tomber le service en boucle de
#    redemarrage ; et l'image etant partagee par app + worker, migrer dans l'entrypoint lancerait deux
#    migrations concurrentes par base (course).
#  - PRECHAUFFAGE DU CACHE : PAS ici. Deja fait au build (cache:warmup dans l'image).
set -e

if [ -d /var/www/html/public ]; then
    mkdir -p /srv-assets
    cp -a /var/www/html/public/. /srv-assets/
fi

# Delegue au processus passe en argument (php-fpm pour app-*, messenger:consume pour worker-*).
exec "$@"
