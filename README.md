# CréaPrêt

Application web de **gestion de prêt de matériel** pour un établissement (Cnam La Réunion, Centre du
Port). Elle couvre tout le cycle : consulter le **catalogue**, vérifier la **disponibilité** d'un
exemplaire sur une période, **demander** un prêt, le faire **valider**, puis en enregistrer la
**restitution**. Projet développé dans le cadre du titre **CDA — Concepteur Développeur d'Applications**
(démonstrateur pédagogique, **données fictives**).

---

## Fonctionnalités par profil

**Emprunteur**
- Parcourir le catalogue de matériel et filtrer par catégorie.
- Vérifier la disponibilité d'un matériel sur une période donnée.
- Demander un prêt, suivre l'état de ses demandes et **annuler** une demande en attente (« Mes prêts »).

**Gestionnaire** (en plus)
- **Valider** ou **refuser** les demandes de prêt (avec motif).
- Enregistrer les **retours** (dont retour avec dommage), avec signalement des prêts **en retard**.
- **Inventaire** : catégories, matériels, exemplaires (et leur état), vue « État du parc ».
- **Calendrier** d'occupation du matériel et **tableau de bord** (indicateurs, matériels les plus empruntés).

**Super-administrateur** (en plus)
- **Gestion des comptes** : création, changement de rôle, activation/désactivation (avec garde-fous).
- **Journal d'administration** (traçabilité RGPD des actions sensibles).

---

## Pile technique

| Composant | Technologie (version réelle) |
|---|---|
| Langage | PHP 8.4 |
| Cadriciel | Symfony 8.0 |
| ORM | Doctrine ORM 3 |
| Base de données | MySQL 8 |
| Gabarits | Twig 3 |
| Front | Bootstrap 5 · Stimulus + Turbo (Symfony UX) · FullCalendar + Chart.js (auto-hébergés) |
| Asynchrone / e-mails | Symfony Messenger · Symfony Mailer (Brevo) |
| Conteneurisation | Docker + Docker Compose |
| Qualité | PHPUnit 13 · PHPStan niveau 8 · PHP-CS-Fixer · SonarQube Cloud |
| Intégration continue | GitHub Actions |
| Observabilité (prod) | Uptime Kuma + Dozzle |

---

## Démarrage en local

**Prérequis** : Docker Desktop et Git.

```bash
# 1. Cloner le dépôt
git clone https://github.com/sgahovey/creapret.git
cd creapret

# 2. (optionnel) Surcharger la configuration locale
#    Les valeurs par défaut du .env suffisent pour le développement.
cp .env .env.local   # puis éditer .env.local si besoin

# 3. Démarrer les conteneurs (app php-fpm, nginx, base MySQL, phpMyAdmin)
docker compose up -d

# 4. Jouer les migrations
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# 5. Charger le jeu de données de démonstration (développement uniquement)
docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

Accès : **http://localhost:8000** (application) · **http://localhost:8080** (phpMyAdmin).

---

## Tests et qualité

```bash
# Suite de tests (environnement de test)
docker compose exec -T -e APP_ENV=test app vendor/bin/phpunit

# Analyse statique (niveau 8)
docker compose exec app vendor/bin/phpstan analyse --no-progress

# Style de code (contrôle, sans modifier)
docker compose exec app vendor/bin/php-cs-fixer fix --dry-run --diff \
  --config=.php-cs-fixer.dist.php --path-mode=intersection
```

L'**intégration continue** (GitHub Actions) exécute ces trois contrôles — style, analyse statique,
tests — sur chaque `push` et *pull request* ; la couverture est remontée à SonarQube Cloud (Quality
Gate sur le code nouveau).

---

## Documentation

| Document | Contenu |
|---|---|
| [`docs/runbook-deploiement.md`](docs/runbook-deploiement.md) | Procédures d'exploitation : déployer, dépanner, sauvegarder, restaurer. |
| [`docs/architecture-deploiement.md`](docs/architecture-deploiement.md) | Le *pourquoi* de l'infrastructure (proxy partagé, réseaux, données). |
| [`docs/plan-de-tests.md`](docs/plan-de-tests.md) | Stratégie, cartographie chiffrée, traçabilité et jeux d'essai. |
| [`docs/audit-securite-owasp.md`](docs/audit-securite-owasp.md) | Revue de sécurité mappée sur l'OWASP Top 10. |
| [`docs/DETTE_TECHNIQUE.md`](docs/DETTE_TECHNIQUE.md) | Registre de la dette technique assumée. |
| [`docs/cron-rappels.md`](docs/cron-rappels.md) · [`docs/cron-purge-audit.md`](docs/cron-purge-audit.md) | Tâches planifiées (rappels d'échéance, purge du journal RGPD). |

---

## Déploiement

Le déploiement se fait par un pipeline GitHub Actions (préproduction automatique, production sur
décision explicite avec approbation). Les **procédures** sont dans
[`docs/runbook-deploiement.md`](docs/runbook-deploiement.md) et les **choix d'architecture** dans
[`docs/architecture-deploiement.md`](docs/architecture-deploiement.md).

---

## Mention

Projet **pédagogique** à données fictives, réalisé pour le titre **CDA (Concepteur Développeur
d'Applications)** — Cnam La Réunion. Non destiné à un usage en production réelle en l'état.
