# Dette technique — CréaPrêt

Ce document recense les compromis techniques assumés et les points à traiter ultérieurement.
Chaque entrée précise le constat, l'impact, et la piste de résolution. Tenir ce registre à jour
fait partie de la démarche qualité du projet (reconnaître et tracer les compromis plutôt que
les laisser implicites).

| Statut | Signification |
|---|---|
| Ouverte | Non traitée |
| Planifiée | Résolution rattachée à une US/itération |
| Résolue | Corrigée (conservée pour historique) |

---

## DT-1 — Absence de `compose.prod.yml` (prérequis trigger en préprod/prod)

- **Statut** : Planifiée (itération 6, déploiement).
- **Constat** : la migration `Version20260707182041` crée un trigger et une procédure stockée. Avec
  le binary logging actif, MySQL exige `log_bin_trust_function_creators=1` pour créer ces objets sans
  privilège SUPER (sinon erreur 1419). Ce flag n'est présent que dans `docker-compose.yml` (dev) ;
  il n'existe pas de `compose.prod.yml`.
- **Impact** : sans ce flag, la migration échouera lors de la promotion `develop -> preprod -> prod`.
- **Résolution** : créer `compose.prod.yml` avec `command: --log-bin-trust-function-creators=1` sur le
  service `db`, avant de jouer les migrations en préprod (US-6.2).

## DT-2 — Table `historique_utilisateur` non mappée en entité Doctrine

- **Statut** : Ouverte (choix d'architecture assumé).
- **Constat** : la table d'audit est alimentée exclusivement par un trigger SQL et lue par une
  procédure stockée / du SQL natif. Elle n'est volontairement pas mappée en entité Doctrine.
- **Impact** : pas d'accès ORM à l'historique ; toute lecture passe par SQL natif. C'est cohérent avec
  la nature de l'audit (écriture par la base, garantie même hors application), mais cela signifie qu'un
  éventuel affichage de l'historique (BF-15) devra s'appuyer sur la procédure, pas sur un repository.
- **Résolution** : si un affichage riche devient nécessaire, envisager une entité en lecture seule
  (`readOnly`) mappée sur la table, ou un service dédié encapsulant l'appel de la procédure.

## DT-3 — Artefacts orphelins dans `public/assets/`

- **Statut** : Ouverte (cosmétique, non fonctionnel).
- **Constat** : `asset-map:compile` écrit les nouveaux fichiers hachés sans supprimer les anciens. Après
  plusieurs recompilations, `public/assets/` accumule des versions obsolètes (ex. plusieurs
  `graphique_materiels_controller-*.js`).
- **Impact** : encombrement du répertoire de build uniquement ; le HTML référence toujours le bon hash
  via le manifeste. `public/assets/` est git-ignoré, donc aucun impact sur le dépôt.
- **Résolution** : purge avant compilation (`rm -rf public/assets && bin/console asset-map:compile`),
  à intégrer au processus de build de déploiement (US-6.2).

## DT-4 — Activation du prerequis trigger au runtime en CI

- **Statut** : Ouverte (contournement assume).
- **Constat** : le service MySQL de GitHub Actions (`services: mysql`) n'accepte pas de champ
  `command:`, contrairement au `docker-compose.yml` de developpement. Impossible d'y passer
  `--log-bin-trust-function-creators=1` au demarrage. La migration du trigger echouait donc en CI
  (erreur 1419).
- **Impact** : sans correctif, tout le pipeline echoue des qu'une migration cree un trigger ou une
  procedure.
- **Resolution appliquee** : une etape du job `phpunit` execute `SET GLOBAL
  log_bin_trust_function_creators = 1` (en root) juste avant les migrations. Les migrations et les
  tests restent joues sous l'utilisateur applicatif de moindre privilege (fidele production), le
  privilege n'etant eleve que pour l'activation de la variable globale.
- **Amelioration possible** : si une image MySQL personnalisee ou un service configurable est adopte
  en CI, integrer le flag au demarrage plutot qu'au runtime.
