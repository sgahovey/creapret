# Réalisation — Gestion des versions et déploiement

> Pour un lecteur technique. **Conception correspondante** : `../conception/versionnement.md`. Ce
> document décrit **ce qui a été mis en place** ; il renvoie aux documents détaillés sans les recopier.

## Les lignes de développement

Trois lignes permanentes, telles qu'elles s'appellent :

- **`develop`** — ligne d'**intégration** du travail courant.
- **`preprod`** — ligne de **validation** ; son contenu est déployé **automatiquement** en
  préproduction (accès restreint).
- **`main`** — ligne de **service** (production).

Chaque modification isolée a vécu sur une branche temporaire **`feature/US-X.Y-*`**, versée dans
`develop` par une **demande d'intégration** (*pull request*) relue.

## La plateforme et les déclencheurs

- **Dépôt et automatisation** : GitHub, avec GitHub Actions (`.github/workflows/`).
- **Déclencheurs configurés** :
  - **Contrôles** (`build-push.yml` et l'intégration continue) : à chaque `push` et chaque *pull
    request*.
  - **`deploy-preprod.yml`** : **automatique** sur `push` vers `preprod` (et `workflow_dispatch`
    manuel).
  - **`deploy-prod.yml`** : **`workflow_dispatch` uniquement** — **aucun** déploiement automatique en
    production ; la clause `environment: production` impose en outre une **approbation**.

## Les contrôles exécutés

- **Style** : PHP-CS-Fixer (jeu de règles PER).
- **Analyse statique** : PHPStan **niveau 8**.
- **Tests** : PHPUnit.
- **Qualité du code nouveau** : SonarQube Cloud (Quality Gate sur les nouvelles lignes).
- **Bon fonctionnement après déploiement** : un *smoke test* qui **échoue** si la réponse est vide,
  `000` ou `5xx` (cf. `DETTE_TECHNIQUE.md`, DT-10).

## La version publiée et sa désignation

- **Empreinte technique** : image *build-once* étiquetée par l'empreinte du commit —
  `ghcr.io/sgahovey/creapret:<sha>`. C'est l'identifiant interne, illisible, qui garantit qu'on
  déploie **exactement** ce qui a été construit.
- **Désignation lisible** : une **étiquette de version sémantique** à trois positions
  (`MAJEUR.MINEUR.CORRECTIF`), posée au moment de la mise en service.
- **Retour arrière** : redéployer une **empreinte** (ou une **version**) antérieure.

## Vérification — mode d'exécution de l'application en ligne

**Le contrôle.** S'assurer qu'un environnement **accessible** s'exécute en **mode production**
(`APP_ENV=prod`, `APP_DEBUG=0`) et **non** en mode développement.

**Preuve de configuration (statique).** `compose.prod.yml` fixe `APP_ENV: prod` pour
`creapret-app-preprod` **et** `creapret-app-prod` (lignes 28 et 43).

**Vérification à l'exécution.** Le contrôle **interroge l'application elle-même** — l'état que le
noyau applique et les extensions réellement chargées par l'interpréteur — et **non la valeur d'une
variable** : c'est l'**état effectif** qui est constaté, pas l'**intention**.

```bash
# Etat effectif vu par l'application (noyau), pour chaque service applicatif
docker compose -f compose.prod.yml exec creapret-app-prod    php bin/console about
docker compose -f compose.prod.yml exec creapret-app-preprod php bin/console about
#   -> Environment : prod   Debug : false

# Extension de debogage reellement chargee par l'interpreteur
docker compose -f compose.prod.yml exec creapret-app-prod    php -m | grep -i xdebug
docker compose -f compose.prod.yml exec creapret-app-preprod php -m | grep -i xdebug
#   -> aucune sortie : l'extension de debogage est absente de l'image
```

Contrôle complémentaire côté HTTP : une page d'erreur ne doit **pas** exposer de trace d'exécution
détaillée ni de barre de débogage.

**Résultat (vérification menée le 18 juillet 2026 sur le serveur).** Les deux services sont conformes :

- **environnement de service** : mode **production**, **débogage désactivé**, **extension de débogage
  absente** ;
- **environnement de validation** : **identique**.

**Pourquoi ce constat est le bon.** Un environnement de validation qui ne s'exécuterait **pas dans les
mêmes conditions** que celui de service ne validerait **rien de représentatif** : ce que l'on éprouve
avant la mise en service doit se comporter comme le service réel. La seule chose qui **distingue** les
deux environnements est un **libellé d'environnement** (`APP_ENVIRONMENT_LABEL`), **non un mode
d'exécution** — les deux tournent en mode production. L'**absence de l'extension de débogage** dans
l'image réduit par ailleurs la **surface exposée** et le **coût d'exécution**.

**Pourquoi cela importe.** En mode développement, l'application divulgue des **traces d'exécution
détaillées**, une **barre de débogage** et des **messages d'erreur internes** (chemins, requêtes,
versions) : c'est une **fuite d'information** directement exploitable par un attaquant, doublée d'un
surcoût de performance. Un environnement accessible au public doit donc **impérativement** tourner en
mode production.

## Écarts par rapport à la conception

- La conception ne décrit que **trois lignes** ; en pratique s'y ajoutent les **branches de
  fonctionnalité temporaires** (`feature/*`) — conformes à la règle « une modification isolée vit sur
  sa propre ligne temporaire », mais non représentées comme lignes permanentes.
- La mise en service est **plus verrouillée** que la conception ne l'exige : elle est **déclenchée
  manuellement** (`workflow_dispatch`) **et** soumise à une **approbation** (`environment:
  production`) — deux barrières plutôt qu'une.
