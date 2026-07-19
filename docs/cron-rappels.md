# Planification des rappels et alertes de retard (US-4.3)

La commande `app:prets:rappels` envoie, en un passage :
- les **rappels d'echeance** la veille de la date de fin des prets VALIDE non retournes ;
- les **alertes de retard** pour les prets VALIDE dont la date de fin est depassee.

Elle est **idempotente** : chaque pret traite est marque (`rappel_echeance_envoye_at` /
`retard_notifie_at`), donc un second passage le meme jour ne renvoie rien.

## Fuseau horaire

Les bornes sont calculees en `Indian/Reunion` (UTC+4). Un cron planifie en UTC doit en tenir compte.

## Exemple de cron (production, sur le VPS)

Passage quotidien a 08:00 heure de La Reunion (= 04:00 UTC). Le prefixe `$PFX` designe la
composition de production (`docker compose -f compose.prod.yml --env-file .env.deploy.local`),
comme dans le runbook (§7) :

```cron
# Service nomme explicitement : le service generique "app" n'existe que dans la
# composition de developpement ; la production ne definit que creapret-app-preprod
# et creapret-app-prod. On vise donc creapret-app-prod (environnement production).
# Journal en chemin absolu (~/cron-logs/) : une entree cron herite du repertoire
# courant ; un chemin relatif redirigerait le journal ailleurs en silence si ce
# repertoire de travail venait a changer.
0 4 * * * cd ~/creapret && $PFX exec -T creapret-app-prod php bin/console app:prets:rappels >> ~/cron-logs/rappels.log 2>&1
```

Le worker Messenger (`messenger:consume async`) doit tourner en parallele pour depiler les emails
mis en file par la commande.

## Test manuel (dev, WSL)

```bash
docker compose exec app php bin/console app:prets:rappels
```
