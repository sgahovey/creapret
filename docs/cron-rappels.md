# Planification des rappels et alertes de retard (US-4.3)

La commande `app:prets:rappels` envoie, en un passage :
- les **rappels d'echeance** la veille de la date de fin des prets VALIDE non retournes ;
- les **alertes de retard** pour les prets VALIDE dont la date de fin est depassee.

Elle est **idempotente** : chaque pret traite est marque (`rappel_echeance_envoye_at` /
`retard_notifie_at`), donc un second passage le meme jour ne renvoie rien.

## Fuseau horaire

Les bornes sont calculees en `Indian/Reunion` (UTC+4). Un cron planifie en UTC doit en tenir compte.

## Exemple de cron (production, sur le VPS)

Passage quotidien a 08:00 heure de La Reunion (= 04:00 UTC) :

```cron
0 4 * * * cd /chemin/vers/creapret && docker compose exec -T app php bin/console app:prets:rappels >> var/log/rappels.log 2>&1
```

Le worker Messenger (`messenger:consume async`) doit tourner en parallele pour depiler les emails
mis en file par la commande.

## Test manuel (dev, WSL)

```bash
docker compose exec app php bin/console app:prets:rappels
```
