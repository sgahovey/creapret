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
choisie en dehors des periodes d\'activite.

```cron
# Purge mensuelle des traces d\'audit (le 1er du mois a 03h00 UTC, retention 365 jours)
0 3 1 * * cd /var/www/creapret && php bin/console app:audit:purger >> var/log/purge-audit.log 2>&1
```

## Notes

- Le seuil de conservation par defaut (365 jours) est defini par `JournalAdmin::DUREE_CONSERVATION_JOURS`.
- Un meme seuil est applique aux deux tables (meme finalite d\'accountability, meme sensibilite).
- Le `DELETE` sur `historique_utilisateur` ne declenche pas le trigger d\'audit (`AFTER UPDATE`).
