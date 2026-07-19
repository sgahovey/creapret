# Purge des traces d\'audit (RGPD)

La commande `app:audit:purger` supprime les traces d\'audit au-dela de la duree de conservation, pour
respecter le principe de limitation de conservation (RGPD, CNIL art. 5.1.e). Elle couvre les deux
mecanismes d\'audit du projet :

- le **journal d\'administration** applicatif (`journal_admin`) ;
- l\'**historique des comptes** alimente par trigger (`historique_utilisateur`).

## Utilisation

```bash
# Simulation : compte les entrees purgeables sans rien supprimer
php bin/console app:audit:purger --dry-run

# Purge reelle avec la duree par defaut (365 jours)
php bin/console app:audit:purger

# Purge avec une duree personnalisee
php bin/console app:audit:purger --jours=180
```

## Planification (cron)

En production, la purge est planifiee une fois par mois. Le serveur (VPS) etant en UTC, l\'heure est
choisie en dehors des periodes d\'activite. Le prefixe `$PFX` designe la composition de production
(`docker compose -f compose.prod.yml --env-file .env.deploy.local`), comme dans le runbook (§7).

```cron
# Purge mensuelle des traces d\'audit (le 1er du mois a 03h00 UTC, retention 365 jours)
# Service nomme explicitement : le service generique "app" n'existe que dans la
# composition de developpement ; la production ne definit que creapret-app-preprod
# et creapret-app-prod. On vise donc creapret-app-prod (environnement production).
# Journal en chemin absolu (~/cron-logs/) : une entree cron herite du repertoire
# courant ; un chemin relatif redirigerait le journal ailleurs en silence si ce
# repertoire de travail venait a changer.
0 3 1 * * cd ~/creapret && $PFX exec -T creapret-app-prod php bin/console app:audit:purger >> ~/cron-logs/purge-audit.log 2>&1
```

## Notes

- Le seuil de conservation par defaut (365 jours) est defini par `JournalAdmin::DUREE_CONSERVATION_JOURS`.
- Un meme seuil est applique aux deux tables (meme finalite d\'accountability, meme sensibilite).
- Le `DELETE` sur `historique_utilisateur` ne declenche pas le trigger d\'audit (`AFTER UPDATE`).
