# Runbook de déploiement et d'exploitation — CréaPrêt (production)

Procédures **opérationnelles** pour déployer, dépanner ou reprendre l'infrastructure de production de
CréaPrêt. Ce document se limite aux procédures et aux commandes copiables. Aucune valeur sensible n'y
figure : mots de passe, clés, jetons et empreintes de commit sont désignés par leur rôle.

**Préfixe commun de toutes les commandes** (sur le serveur, à la racine `~/creapret`) :

```bash
PFX="docker compose -f compose.prod.yml --env-file .env.deploy.local"
```

---

## 1. Topologie — un serveur, deux applications

CréaPrêt **cohabite** sur un même serveur avec une autre application (CreaSlot). **Le reverse-proxy
(Caddy) appartient à cette autre application** et détient les **ports standards 80/443** : aucune
seconde instance de proxy ne pouvait les prendre. CréaPrêt apporte donc **sa pile complète** et
**rejoint le réseau du proxy**, qui expose ses domaines.

**Pile CréaPrêt** (`compose.prod.yml`, projet `creapret_prod`) — 7 services :

| Service | Rôle | Exposé par le proxy ? |
|---|---|---|
| `db` | MySQL dédié (bases `creapret_preprod` + `creapret_prod`) | non (réseau interne seul) |
| `creapret-app-preprod` | Application (php-fpm) — préproduction | oui |
| `creapret-app-prod` | Application (php-fpm) — production | oui |
| `creapret-worker-preprod` | Consommateur Messenger — préproduction | non |
| `creapret-worker-prod` | Consommateur Messenger — production | non |
| `creapret-supervision` | Supervision (Uptime Kuma) | oui |
| `creapret-journaux` | Consultation des journaux (Dozzle) | oui |

**Deux réseaux** : `creapret-interne` (privé, applications ↔ base — la base n'est **que** là) et
`proxy` (déclaré **external**, `name: creaslot_prod_creaslot-prod-net`) que seuls les services devant
être joints par Caddy rejoignent.

**Conséquence structurelle à retenir** : **toute modification des blocs de site (Caddyfile) se fait
dans le dépôt de l'AUTRE application** (CreaSlot), puisque c'est elle qui héberge le proxy. CréaPrêt
n'a pas de service `caddy`.

**Noms de services préfixés `creapret-`** : sur le réseau partagé, chaque conteneur est joignable par
son **nom de service**. Un alias réseau étant **additif** (il ne remplace pas le nom de service), il
ne suffisait pas ; seul un **nom de service unique** évite la collision avec les services homonymes de
CreaSlot (`app-preprod`, `app-prod`). D'où le préfixe, qui détermine la résolution DNS.

**Domaines exposés (4)** — enregistrements DNS A pointant vers le serveur, blocs de site déclarés dans
le Caddyfile de CreaSlot :

| Domaine (rôle) | Cible interne | Accès |
|---|---|---|
| `<prod.domaine>` | `creapret-app-prod:9000` (php_fastcgi) | public |
| `<preprod.domaine>` | `creapret-app-preprod:9000` | restreint (basic_auth) |
| `<supervision.domaine>` | `creapret-supervision:3001` (reverse_proxy) | restreint (basic_auth) |
| `<journaux.domaine>` | `creapret-journaux:8080` (reverse_proxy) | restreint (basic_auth) |

> Note : quatre services de CréaPrêt sont exposés (les deux applications, la supervision, les
> journaux). La base et les deux consommateurs ne le sont pas.

---

## 2. Prérequis

- **Accès SSH** au serveur avec une clé (l'authentification par mot de passe et le login `root` sont
  désactivés). Utilisateur de déploiement : `<utilisateur>`.
- **Clé de déploiement restreinte par commande forcée** : dans `~/.ssh/authorized_keys` du serveur, la
  clé publique du pipeline est préfixée par
  `command="/chemin/creapret/scripts/deploy-ci.sh",no-pty,...` — cette clé ne peut **que** lancer le
  script de déploiement (`SSH_ORIGINAL_COMMAND = "<env> <empreinte>"`), aucune autre commande.
- **Secrets et environnements du dépôt GitHub** :
  - Secrets : `VPS_SSH_KEY` (clé privée de déploiement), `VPS_HOST` (hôte), `VPS_USER` (utilisateur SSH).
    `GITHUB_TOKEN` est fourni automatiquement (push GHCR), à ne pas déclarer.
  - Environnements : **`preprod`** et **`production`** (ce dernier avec **approbateurs requis** pour
    l'approbation manuelle). Variable d'environnement **`SITE_URL`** définie dans chacun (URL publique,
    pour le contrôle final du pipeline).
- **Fichier de variables non versionné** `~/creapret/.env.deploy.local` (couvert par `.gitignore` via
  `/.env.*.local`), gabarit `.env.deploy.example`. **Clés attendues (sans valeurs)** :
  `PREPROD_IMAGE_TAG`, `PROD_IMAGE_TAG`, `MYSQL_ROOT_PASSWORD`, `MYSQL_PREPROD_PASSWORD`,
  `MYSQL_PROD_PASSWORD`, `APP_SECRET_PREPROD`, `APP_SECRET_PROD`, `PREPROD_URL`, `PROD_URL`,
  `MAILER_DSN`, `APP_NOTIFICATION_FROM`, `APP_NOTIFICATION_REPLY_TO`, `PREPROD_MAILER_REDIRECT_TO`,
  `TRUSTED_PROXIES`.
- **Enregistrements DNS** : un enregistrement **A** par domaine du §1, pointant vers l'adresse du
  serveur.
- **Authentification du domaine auprès du routeur de courriels (Brevo)** : entrées DNS d'authentification
  d'expéditeur dans la zone du domaine — vérification d'expéditeur (`TXT`), signatures **DKIM**
  (`CNAME`), politique **DMARC** (`TXT _dmarc`). Valeurs fournies par le routeur, non reproduites ici.

---

## 3. Premier déploiement (ordre exact éprouvé)

Le réseau du proxy doit **déjà exister** (créé par la pile CreaSlot démarrée en premier). Vérifier :

```bash
docker network inspect creaslot_prod_creaslot-prod-net >/dev/null && echo "reseau proxy OK"
```

1. **Base seule + vérification de l'initialisation.** Le script `docker/mysql/init-prod.sh` crée les
   deux bases et leurs utilisateurs au **tout premier** démarrage (volume vierge).
   ```bash
   $PFX up -d db
   $PFX exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e "SHOW DATABASES LIKE \"creapret_%\""'
   # attendu : creapret_preprod  et  creapret_prod
   ```
2. **Service applicatif** (préproduction d'abord) :
   ```bash
   $PFX up -d creapret-app-preprod
   ```
3. **Migrations** — sur **un seul** service applicatif (voir §4) :
   ```bash
   $PFX exec -T creapret-app-preprod php bin/console doctrine:migrations:migrate --no-interaction
   ```
   Ces migrations créent notamment la table `messenger_messages` (transport Doctrine) et le
   déclencheur d'audit (prérequis `--log-bin-trust-function-creators=1` du service `db`, déjà en place).
4. **Consommateur** :
   ```bash
   $PFX up -d creapret-worker-preprod
   ```
   > **Attendu** : lancé **avant** les migrations, le consommateur **échoue et redémarre** tant que la
   > table `messenger_messages` n'existe pas. Ce n'est pas une erreur de configuration ; il se stabilise
   > dès que les migrations ont créé la table. Le redémarrer si besoin : `$PFX up -d creapret-worker-preprod`.
5. **Jeu de démonstration — PRÉPRODUCTION UNIQUEMENT.** Le script porte un garde-fou (`SIGNAL`) qui
   **échoue réellement** s'il n'est pas exécuté sur `creapret_preprod` :
   ```bash
   $PFX exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" creapret_preprod' < scripts/seed-preprod.sql
   ```
6. **Répéter les étapes 2 à 4 pour la production** (`creapret-app-prod`, migrations sur
   `creapret-app-prod`, `creapret-worker-prod`). **Ne jamais** exécuter le seed en production.

### Amorçage du premier administrateur (production)

La production **n'a aucun compte**, et **aucun chemin applicatif ne permet de créer le premier
super-administrateur** (l'inscription publique ne crée que des emprunteurs ; la gestion des comptes
exige déjà un super-administrateur). Procédure d'amorçage :

1. S'inscrire normalement via `<prod.domaine>/inscription` (crée un compte `emprunteur`).
2. Élever ce compte en base :
   ```bash
   $PFX exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" creapret_prod -e "UPDATE utilisateur SET role=\"super_admin\" WHERE email=\"<votre-email>\""'
   ```
   > Cette modification de rôle est **tracée par le déclencheur d'audit** (`trg_historique_utilisateur`,
   > table `historique_utilisateur`) — l'opération d'amorçage laisse donc une trace.

---

## 4. Déploiement courant

### Par le pipeline (nominal)
- **Préproduction — automatique.** Un push (via Pull Request mergée) sur la branche **`preprod`**
  déclenche le workflow *Deploiement preprod* : build de l'image au **SHA du commit**, puis déploiement
  SSH. Suivi : **GitHub → Actions → « Deploiement preprod »**.
- **Production — manuel + approbation.** Le workflow *Deploiement prod* est **déclenché manuellement**
  (`workflow_dispatch`) : **GitHub → Actions → « Deploiement prod » → Run workflow**. Le job `build`
  s'exécute, puis le job `deploy` **se met en pause** sur l'approbation de l'environnement `production`
  (**Review deployments → Approve and deploy**). Une mise en ligne de production résulte ainsi d'une
  **décision explicite**, jamais d'un simple merge.
- Chaque déploiement se termine par un **contrôle que le site répond** (échec si aucune réponse `000`
  ou erreur serveur `5xx` ; un `401` derrière l'authentification de préproduction est accepté).

### En secours manuel (pipeline indisponible)
Le script serveur `scripts/deploy-ci.sh` fait, pour un environnement `<env>` (`preprod`|`prod`) et une
empreinte `<sha>` : synchronisation du dépôt sur le commit, `pull` de l'image, recréation des services,
puis migrations. Équivalent à la main :

```bash
cd ~/creapret
git fetch origin && git reset --hard <sha>
export PROD_IMAGE_TAG=<sha>          # ou PREPROD_IMAGE_TAG pour la préprod
$PFX pull creapret-app-prod creapret-worker-prod
$PFX up -d creapret-app-prod creapret-worker-prod
# Migrations : UN SEUL service applicatif (jamais un consommateur)
$PFX exec -T creapret-app-prod php bin/console doctrine:migrations:migrate --no-interaction
```

> **Règle impérative** : les migrations s'exécutent sur **un seul service applicatif**
> (`creapret-app-<env>`), **jamais** sur un consommateur. L'image étant partagée, migrer aussi le
> consommateur lancerait **deux migrateurs simultanés** sur la même base — situation de course.

---

## 5. Proxy (Caddy — dépôt de l'autre application)

Les blocs de site de CréaPrêt vivent dans **`docker/caddy/Caddyfile` du dépôt CreaSlot**. Un bloc type
pour une application CréaPrêt :

```caddyfile
<prod.domaine> {
	tls {$CADDY_TLS}
	import securite                       # en-têtes communs (la CSP reste posée par l'app)
	root * /srv/creapret-prod             # volume d'assets de CréaPrêt (monté en external)
	php_fastcgi creapret-app-prod:9000 {
		root /var/www/html/public
	}
	file_server
}
```

> Le volume d'assets (`creapret_prod_assets_prod`) doit être monté dans le conteneur Caddy en
> **external**, et l'entrypoint de l'app y recopie `public/` à chaque démarrage.

### Valider AVANT d'appliquer (conteneur jetable)
Ne jamais recharger une configuration non validée :

```bash
docker run --rm -v "$PWD/docker/caddy/Caddyfile:/etc/caddy/Caddyfile:ro" caddy:2-alpine \
  caddy validate --config /etc/caddy/Caddyfile
```

### Recharger à chaud (et NON recréer)
```bash
docker compose -f compose.prod.yml --env-file .env.deploy.local exec caddy \
  caddy reload --config /etc/caddy/Caddyfile     # commande exécutée dans le dépôt CreaSlot
```

> **Pourquoi `reload` et pas `up -d caddy`** : recréer le conteneur Caddy entraîne ses **dépendances
> déclarées** (`depends_on`) et peut déclencher une **reconstruction locale** (`build:`) qui
> **remplacerait les conteneurs applicatifs en production**. Le rechargement à chaud applique la
> nouvelle configuration **sans recréer** aucun conteneur.

### Exposer un chemin sans authentification tout en protégeant le reste
Structure à **deux gestionnaires** (`handle`) : un chemin public, tout le reste protégé.

```caddyfile
<supervision.domaine> {
	tls {$CADDY_TLS}
	import securite

	handle /status* {                     # page de statut publique (sans authentification)
		reverse_proxy creapret-supervision:3001
	}
	handle {                              # tout le reste : protégé
		basic_auth {
			{$CREAPRET_STATUS_USER} {$CREAPRET_STATUS_HASH}
		}
		reverse_proxy creapret-supervision:3001
	}
}
```

---

## 6. Sauvegarde et restauration

- **Sauvegarde** : `scripts/backup-db.sh` — `mysqldump --single-transaction` (dump cohérent InnoDB,
  sans verrou bloquant), **compressé et horodaté** dans `~/backups/creapret/`, mot de passe **lu dans le
  conteneur** (jamais en argument). **Rétention** paramétrable (`RETENTION_DAYS`, défaut 14 jours).
  Base cible en **argument** ou par variable :
  ```bash
  ./scripts/backup-db.sh                      # creapret_prod (défaut)
  ./scripts/backup-db.sh creapret_preprod     # préproduction
  ```
- **Restauration** : `scripts/restore-db.sh` — exige une **archive valide** (présente, non vide,
  `.sql.gz`), **confirmation explicite** (saisir `RESTAURER <base>`), et **refuse les croisements
  d'environnement** (voir ci-dessous). Base cible = **2ᵉ argument**, sinon variable, sinon défaut :
  ```bash
  ./scripts/restore-db.sh ~/backups/creapret/creapret_creapret_preprod_AAAAMMJJ_HHMMSS.sql.gz creapret_preprod
  ```

### Incident survenu (et correctif)
**Constat** : une version antérieure du script de restauration **ignorait la base transmise en second
argument** et retombait sur la base par défaut (`creapret_prod`). Résultat : **une sauvegarde de
préproduction a été restaurée en production**, y installant des **comptes de démonstration aux
identifiants publics** (dont un super-administrateur).

**Traitement** : incident **détecté** (comptes de démo inattendus en production), **corrigé** par
restauration depuis **la sauvegarde de production précédente**, puis par un **contrôle de cohérence**
(absence de comptes `*@creapret.local` de démonstration en production).

**Correctif pérenne** : le 2ᵉ argument détermine désormais la base cible, **et** un garde-fou refuse
toute archive dont l'environnement (encodé dans le nom, ex. `..._creapret_preprod_...`) ne correspond
pas à la base cible.

**Jeu d'essai du garde-fou** (détection `preprod` testée avant `prod`, car « prod » est une
sous-chaîne de « preprod ») :

| Archive | Base cible | Résultat |
|---|---|---|
| `..._creapret_preprod_....sql.gz` | `creapret_prod` | **REFUSÉ** (préprod → prod) |
| `..._creapret_prod_....sql.gz` | `creapret_preprod` | **REFUSÉ** (prod → préprod) |
| `..._creapret_preprod_....sql.gz` | `creapret_preprod` | autorisé |
| `..._creapret_prod_....sql.gz` | `creapret_prod` | autorisé |
| `sauvegarde_generique.sql.gz` | `creapret_prod` | autorisé (environnement indéterminable) |

> Après une restauration, vérifier l'état des migrations (le schéma restauré peut être antérieur au
> code déployé) — le script le rappelle en fin d'exécution
> (`... exec -T creapret-app-<env> php bin/console doctrine:migrations:status`).

---

## 7. Tâches planifiées (crons)

Crontab de l'utilisateur `<utilisateur>` — **3 entrées**, serveur en **UTC** :

| Tâche | Horaire UTC | Équivalent local (Indian/Reunion, UTC+4) | Commande | Journal |
|---|---|---|---|---|
| Rappels d'échéance | `0 14 * * *` | 18h00 | `app:envoyer-rappels` | `~/cron-logs/rappels.log` |
| Purge du journal RGPD | `0 3 1 * *` | 07h00, le 1er du mois | `app:audit:purger` | `~/cron-logs/purge-audit.log` |
| Sauvegarde de la base | `30 2 * * *` | 06h30 | `scripts/backup-db.sh` | `~/cron-logs/backup.log` |

Lignes exactes et détails : `docs/cron-rappels.md` et `docs/cron-purge-audit.md`.

**Signalement conditionnel vers la supervision** : chaque tâche notifie un **moniteur de type « push »**
d'Uptime Kuma, **seulement si elle réussit** — l'appel est chaîné en `&&`, donc il ne part **que** sur
succès. L'**absence** de signal (période dépassée) déclenche l'alerte côté supervision. Exemple :

```bash
0 14 * * * cd ~/creapret && $PFX exec -T creapret-app-prod php bin/console app:envoyer-rappels \
  >> ~/cron-logs/rappels.log 2>&1 && curl -fsS "<url-push-rappels>" >/dev/null
```

> `<url-push-rappels>` est l'URL d'un moniteur push (jeton propre à Uptime Kuma) — désignée par son
> rôle, non reproduite.

---

## 8. Supervision

- **Instance dédiée** à CréaPrêt (`creapret-supervision`, Uptime Kuma), **distincte** de celle de
  l'autre application, pour ne pas mélanger les deux projets. Configurée via son interface (données
  persistées dans le volume `creapret_supervision_data`).
- **Huit sondes en place** :

  | # | Sonde | Type | Attendu / seuil |
  |---|---|---|---|
  | 1 | Application production (vue externe) | HTTP `<prod.domaine>/connexion` | code 200 |
  | 2 | Application préproduction (vue externe) | HTTP `<preprod.domaine>/connexion` | code 401 accepté (basic_auth) |
  | 3 | Service applicatif production (vue interne) | Port/TCP `creapret-app-prod:9000` depuis le réseau interne | port ouvert |
  | 4 | Battement « rappels d'échéance » | Push | période 24 h |
  | 5 | Battement « purge du journal » | Push | période ~31 j |
  | 6 | Battement « sauvegarde » | Push | période 24 h |
  | 7 | Conteneur consommateur préproduction | État Docker (`creapret-worker-preprod`) | en cours d'exécution |
  | 8 | Conteneur consommateur production | État Docker (`creapret-worker-prod`) | en cours d'exécution |

  > Les sondes **1 et 2** valident le chemin complet vu de l'extérieur (proxy + application) ; la sonde
  > **3**, depuis le réseau interne, isole l'état de l'application de celui du proxy.
- **Pourquoi les sondes 7 et 8 (consommateurs)** : les consommateurs de messages **ne servent aucune
  page** et ne peuvent donc pas être sondés par une requête. Or leur **interruption passerait
  inaperçue** — plus aucun courriel ne partirait (confirmations de prêt, rappels d'échéance, alertes de
  retard) **alors que le site continuerait de répondre normalement**, panne silencieuse. Ces deux sondes
  surveillent donc l'**état du conteneur** (en cours d'exécution), en s'appuyant sur le **socket du démon
  monté en lecture seule** sur `creapret-supervision`.
- **Notifications** : configurer au moins un canal (courriel via le routeur, ou webhook) sur chaque
  sonde. **Seuils d'expiration de certificat** réglés sur les sondes HTTP 1 et 2 (alerte anticipée).
- **Consultation des journaux** (`creapret-journaux`, Dozzle) : **filtrée par projet** via
  `DOZZLE_FILTER=label=com.docker.compose.project=creapret_prod` → seuls les conteneurs de CréaPrêt
  apparaissent (l'autre application n'est pas visible). Accès protégé par authentification au niveau du
  proxy.

> **Limites assumées** :
> - l'**espace disque n'est pas surveillé** — un disque plein (dumps, journaux, base) casserait les
>   sauvegardes et les écritures sans qu'aucune sonde ne le signale ;
> - un consommateur **vivant mais bloqué** (conteneur en cours d'exécution mais processus figé, ne
>   consommant plus la file) **ne serait pas détecté** : les sondes 7 et 8 vérifient l'exécution du
>   conteneur, pas le traitement effectif des messages ;
> - la **supervision elle-même ne peut pas signaler sa propre panne** : si l'outil tombe, aucune alerte
>   ne part, y compris la sienne. Un contrôle externe reste nécessaire pour couvrir ce cas.

---

## 9. Retour arrière (rollback)

**Revenir à une version antérieure de l'image** (le plus fréquent — le code est embarqué dans l'image,
OPcache figé) :

- Par le pipeline : relancer *Deploiement prod* en visant l'**empreinte** d'un commit stable antérieur.
- En manuel :
  ```bash
  cd ~/creapret
  export PROD_IMAGE_TAG=<sha-stable-anterieur>
  $PFX pull creapret-app-prod creapret-worker-prod
  $PFX up -d creapret-app-prod creapret-worker-prod
  ```

**Quand une restauration de base est nécessaire EN COMPLÉMENT** : uniquement si la version fautive a
appliqué une **migration destructive ou irréversible** (suppression/altération de données ou de
colonnes). Revenir à une image antérieure **ne défait pas** les migrations déjà appliquées → restaurer
alors la **dernière sauvegarde saine** (§6), puis vérifier l'état des migrations. Si la migration était
seulement additive (nouvelle table/colonne), le retour d'image seul suffit généralement.

---

## 10. Incidents courants

| Symptôme | Cause probable | Vérification | Correction |
|---|---|---|---|
| Consommateur en redémarrage permanent | Table `messenger_messages` absente (avant migrations) | `$PFX logs creapret-worker-prod --tail 50` | Jouer les migrations sur `creapret-app-prod`, puis `$PFX up -d creapret-worker-prod` |
| Le proxy sert **l'autre** application (ou l'inverse) | Résolution ambiguë : collision de nom de service sur le réseau partagé | `docker inspect` du réseau ; vérifier les noms | S'assurer que les services sont préfixés `creapret-` et que le Caddyfile cible ces noms uniques |
| Certificat non obtenu (HTTPS KO) | DNS non propagé, ou rate-limit Let's Encrypt | `dig <domaine>` ; logs Caddy (dépôt CreaSlot) | Vérifier l'enregistrement A ; tester avec la CA **staging** avant la production |
| Application en erreur 500 / variable manquante | `.env.deploy.local` incomplet (clé absente) | `$PFX exec -T creapret-app-prod php bin/console debug:dotenv` | Compléter la clé manquante (cf. §2), recréer le service applicatif |
| Ressources statiques (CSS/JS) non servies | Volume d'assets non monté côté Caddy, ou entrypoint non passé | Présence du volume `creapret_prod_assets_prod` ; contenu de `/srv/creapret-prod` | Monter le volume en external côté Caddy ; recréer l'app pour que l'entrypoint recopie `public/` |
| Reconstruction / recréation involontaire des conteneurs prod | `up -d caddy` a entraîné `depends_on` + `build:` | Historique des commandes | Utiliser `caddy reload` (§5), jamais `up -d caddy` en présence de dépendances |

---

*Runbook CréaPrêt — procédures opérationnelles. Les choix de conception (proxy partagé, image
build-once, transport Doctrine, CSP à nonce) sont hors périmètre de ce document.*
