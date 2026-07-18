# CLAUDE.md — CréaPrêt

> Fichier de contexte permanent lu par Claude Code au début de **chaque** session
> (mécanisme officiel : https://code.claude.com/docs/en/memory). **Le relire sans le
> modifier ni le regénérer.** Toute décision structurante nouvelle est ajoutée ici après
> validation explicite de Saint-George.

---

## 1. Projet — contexte et objectif

**CréaPrêt** : application web de **gestion de prêt de matériel** pour le Cnam La Réunion
(Centre du Port). Démonstrateur pédagogique à **données fictives**, développé dans le cadre
du titre **CDA (Concepteur Développeur d'Applications, TP-01281 millésime 04, niveau 6)**.

Projet-**jumeau de CreaSlot** (gestion de rendez-vous) : réutilise son socle technique et ses
patterns, sur un domaine métier neuf, pour l'examen blanc MSP3.

- **Auteur / dépôt** : Saint-George AHOVEY — GitHub `sgahovey`, commits signés
  `sgahovey@gmail.com`. Dépôt : `github.com/sgahovey/creapret` (à créer).
- **Périmètre** : prêt de **matériel uniquement**. **Réservation de salles exclue** (une salle
  dépend du planning pédagogique du Cnam ; la gérer sans cette intégration produirait des
  disponibilités erronées — limite assumée).

**Référentiels CDA** :
- REAC (activités, compétences, savoir-faire) et REV (critères d'évaluation) — France
  Travail / titres professionnels : https://www.francecompetences.fr/ et
  https://www.francetravail.fr/ (documents fournis dans le projet).

---

## 2. Stack technique (identique à CreaSlot)

| Domaine | Techno | Documentation officielle |
|---|---|---|
| Langage | PHP 8.4 | https://www.php.net/manual/fr/ |
| Framework | Symfony 8.0 (pinné) | https://symfony.com/doc/current/index.html |
| ORM | Doctrine ORM 3 | https://www.doctrine-project.org/projects/doctrine-orm/en/current/index.html |
| DBAL | Doctrine DBAL 4 | https://www.doctrine-project.org/projects/doctrine-dbal/en/current/index.html |
| SGBD | MySQL 8 | https://dev.mysql.com/doc/refman/8.0/en/ |
| Templates | Twig 3 | https://twig.symfony.com/doc/3.x/ |
| CSS | Bootstrap 5 | https://getbootstrap.com/docs/5.3/ |
| Assets | AssetMapper (sans Node) | https://symfony.com/doc/current/frontend/asset_mapper.html |
| JS | Stimulus + Turbo (Symfony UX) | https://symfony.com/doc/current/frontend/ux.html · https://stimulus.hotwired.dev/ · https://turbo.hotwired.dev/ |
| Calendrier | FullCalendar (self-hosté) | https://fullcalendar.io/docs |
| Graphiques | Chart.js (self-hosté) | https://www.chartjs.org/docs/latest/ |
| Mail | Symfony Mailer + Brevo | https://symfony.com/doc/current/mailer.html · https://developers.brevo.com/ |
| Asynchrone | Symfony Messenger | https://symfony.com/doc/current/messenger.html |
| Tests | PHPUnit | https://docs.phpunit.de/ |
| Reverse-proxy | Caddy | https://caddyserver.com/docs/ |
| Conteneurs | Docker + Compose | https://docs.docker.com/ · https://docs.docker.com/compose/ |
| CI/CD | GitHub Actions | https://docs.github.com/fr/actions |

**Environnements** : dev (WSL), préprod, prod. Domaines : *à définir*. VPS : *à définir*
(réutilisation possible de l'infra OVH existante).

---

## 3. Modèle de données

**Modèle plat, sans héritage** : `Catégorie → Matériel → Exemplaire → Prêt`, plus
`Utilisateur` et l'audit. Stratégie de nommage Doctrine : **underscore** (snake_case).
Réf. mapping : https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/basic-mapping.html

### Entités
- **Utilisateur** : `email` (unique), `motDePasseHash` (argon2id), `nom`, `prenom`, `role`
  (enum), `estActif`, `emailRappel`, `dateCreation`. **Minimisation RGPD** : pas de
  téléphone ni d'adresse.
- **Catégorie** : `nom`, `description`. Classe les matériels.
- **Matériel** : `nom`, `description`, `marque`, `modele`, `reference`, `idCategorie`.
- **Exemplaire** : `numeroInventaire` (unique), `etat` (enum), `idMateriel`. **C'est
  l'exemplaire qui est prêté.**
- **Prêt** : `dateDebut`, `dateFin` (datetime), `statut` (enum), `motifRefus`, `dateDemande`,
  `dateValidation`, `dateRetour`, `idExemplaire`, `idEmprunteur`, `idValidateur` (nullable).
- **HistoriqueUtilisateur** : audit alimenté par trigger (voir §11).

### Énumérations (backed enums PHP)
Réf. https://www.php.net/manual/fr/language.enumerations.php
- **Role** : `EMPRUNTEUR`, `GESTIONNAIRE`, `SUPER_ADMIN`.
- **EtatExemplaire** : `DISPONIBLE`, `PRETE`, `EN_MAINTENANCE`, `HORS_SERVICE`, `PERDU`.
- **StatutPret** : `DEMANDE`, `VALIDE`, `REFUSE`, `RETOURNE`, `ANNULE`.

### Règles de gestion
- **RG-1** — Non-chevauchement des prêts actifs sur un **exemplaire**, **même en concurrence**.
  Garanti par **transaction + verrou pessimiste `PESSIMISTIC_WRITE` + re-vérification après
  verrou**. Réf. Doctrine « Transactions and Concurrency » :
  https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/transactions-and-concurrency.html
- **RG-2** — Un exemplaire `EN_MAINTENANCE`/`HORS_SERVICE`/`PERDU` ne peut être prêté.
- **RG-3** — Au retour, l'exemplaire repasse `DISPONIBLE` (ou `EN_MAINTENANCE` si dommage) ;
  le prêt passe `RETOURNE`.
- **RG-4** — Disponibilité = exemplaires `DISPONIBLE` non engagés par un prêt actif
  chevauchant la période.

> RG-1/RG-2/RG-4 = logique applicative transactionnelle, pas contraintes déclaratives.

### Index de performance
- **Critique** : `pret (id_exemplaire, statut, date_debut, date_fin)` (RG-1/RG-4).
- `exemplaire (id_materiel, etat)` ; `pret (id_emprunteur)`.

---

## 4. Architecture en couches

**Controller → Service → Repository → Entity**. Logique métier et transactions dans les
**Services** (ex. `PretService::validerPret` porte le verrou). Autorisation par **Voters**
(https://symfony.com/doc/current/security/voters.html). DTOs, Enums, EventListeners, Form
Types (validation serveur systématique : https://symfony.com/doc/current/validation.html).

---

## 5. Rôles et sécurité

Hiérarchie cumulative (`role_hierarchy` dans `security.yaml`) :
`ROLE_SUPER_ADMIN > ROLE_GESTIONNAIRE > ROLE_EMPRUNTEUR`.
Réf. https://symfony.com/doc/current/security.html

- **Emprunteur** : catalogue, disponibilités, ses prêts.
- **Gestionnaire** : + validation, retours, catalogue, inventaire.
- **Super-administrateur** : + comptes, tableau de bord, journal RGPD.

**Sécurité** : hachage **argon2id** (prod/préprod), `time_cost` minimum **3** (Sodium).
Réf. https://symfony.com/doc/current/security/passwords.html. CSRF, en-têtes (CSP à nonce,
HSTS), validation serveur systématique. **Ne jamais committer de secret**.
Références : OWASP Top 10 https://owasp.org/www-project-top-ten/ · ANSSI
https://cyber.gouv.fr/ · RGPD (CNIL) https://www.cnil.fr/ · RGAA
https://accessibilite.numerique.gouv.fr/ · RGESN
https://ecoresponsable.numerique.gouv.fr/.

---

## 6. Workflow de développement (strict, stop-confirme)

Méthodologie **stop-confirme** : une sous-étape à la fois, validation avant de continuer.
**Aucun commit ni push sans validation explicite.**

1. **Audit lecture seule** → STOP.
2. **Carte Trello** (Itération n, Catégorie(s), difficulté Fibonacci).
3. **Branche** `feature/US-X.Y-*` via `git switch -c` avant tout commit.
4. **Code par bouts** (prompt Claude Code débutant par « Relis CLAUDE.md sans le modifier »),
   STOP entre morceaux.
5. **Qualité** : PHP-CS-Fixer + PHPStan + validation **visuelle** si front.
6. `git diff` → `git add` **explicites** (jamais `git add .`).
7. **Commit inline** signé (voir §7).
8. **PR** vers `develop` (URL `compare/develop...feature/...?expand=1`).
9. CI verte → **Squash and merge** + **Delete branch**.
10. **Cleanup** : `git checkout develop && git pull --ff-only && git branch -d ... && git remote prune origin`.

**WSL = code** (git, tests, PR) ; **VPS = déploiement**. Préciser WSL ou VPS à chaque commande.

---

## 7. Conventions Git

- Branches : `feature/US-X.Y-*` → `develop` → `preprod` → `main`.
- **Conventional Commits** en français, impératif, **sans accents**. Réf.
  https://www.conventionalcommits.org/fr/v1.0.0/. Scopes : `feat(pret)`, `fix(inventaire)`,
  `refactor(db)`, `docs(dette)`…
- **Format inline obligatoire** :
  `git -c user.name="sgahovey" -c user.email="sgahovey@gmail.com" commit -m "..." -m "..."`.
  Jamais de fichier `message.txt`.
- **Interdiction stricte** : aucun `Co-Authored-By` ni attribution IA (exigence soutenance).

---

## 8. Outils qualité (installés dès l'itération 1)

- **PHP-CS-Fixer** (PER Coding Style) : https://cs.symfony.com/
- **PHPStan** niveau 8 : https://phpstan.org/user-guide/getting-started
- **Rector** (optionnel, upgrades) : https://getrector.com/documentation
- **SonarQube Cloud** (ex-SonarCloud) — Quality Gate sur les nouvelles lignes :
  https://docs.sonarsource.com/sonarqube-cloud/
- Exclure de la couverture ce qui n'a pas de sens : `src/DataFixtures/**`, `src/Kernel.php`,
  `migrations/**` (via `sonar-project.properties`).

CI **verte dès le premier commit**.

---

## 9. Environnements, données et déploiement

- **dev** (WSL) : fixtures complètes (`doctrine:fixtures:load`).
- **préprod** : image de prod (`composer --no-dev`) → **pas de fixtures-bundle ni Composer**.
  Peuplement par **script SQL de seed versionné** (`scripts/seed-preprod.sql`), exécuté
  manuellement sur le VPS. Hash argon2id **iso-prod** (`security:hash-password` en
  `APP_ENV=prod`).
- **prod** : jamais de données de démo.
- **Pipeline** : `scripts/deploy-ci.sh` (forced command SSH) doit **synchroniser le dépôt sur
  le commit déployé** (`git fetch` + `git reset --hard "$TAG"`) **avant** le pull d'image.
  Recréation du conteneur `db` **manuelle** si sa config change.

---

## 10. Garde-fous techniques (leçons CreaSlot — à appliquer d'entrée)

- **FullCalendar v6** : bundle **global** vendored (`index.global.min.js` →
  `window.FullCalendar`) + `fr.global.min.js`. **Jamais** le split ESM jsDelivr (issue #7474 :
  https://github.com/fullcalendar/fullcalendar/issues/7474).
- **Chart.js** : bundle **UMD** (`window.Chart`), self-hosté.
- **Cache-Control sur endpoints API** : `no-store` (pas `max-age`) pour éviter les données
  périmées. Helper `jsonSansCache()`.
- **Spinner Bootstrap 5** : `.spinner-border` + `role="status"` + texte `visually-hidden`.
- **Trigger/procédure MySQL** : ajouter `command: --log-bin-trust-function-creators=1` au
  service `db` de `compose.prod.yml` **dès le départ** (sinon erreur 1419). DDL MySQL =
  auto-commit. Réf. MySQL : triggers
  https://dev.mysql.com/doc/refman/8.0/en/triggers.html · procédures
  https://dev.mysql.com/doc/refman/8.0/en/stored-routines.html · binary logging des
  programmes stockés
  https://dev.mysql.com/doc/refman/8.0/en/stored-programs-logging.html.
- **argon2id** `time_cost` minimum **3**.
- **Audit** : trigger `trg_historique_utilisateur` `AFTER UPDATE ON utilisateur` + procédure
  `consulter_historique_utilisateur` (CP8).
- **Fichiers à accents** : écriture via script Python (`pathlib.write_text`, utf-8), jamais
  sed/heredoc bash.

---

## 11. Gestion de projet (Trello)

Board dédié CréaPrêt. Opérations via bot Python (stdlib, REST API :
https://developer.atlassian.com/cloud/trello/rest/), `.env` gitignored, dry-run puis apply,
idempotent. Chaque carte : Itération (n), Catégorie(s), difficulté Fibonacci (1/2/3/5/8),
checklist « Definition of Done ». Source de vérité du contenu : `user_stories.json`.

---

## 12. Rôles Claude Code vs Claude.ai

- **Claude.ai** (orchestrateur) : conception, décisions, rédaction des prompts, revue. Ne
  code pas directement.
- **Claude Code** (WSL) : rédige le code réel. **Ne touche jamais à git.** Zéro attribution
  IA. Chaque prompt débute par « Relis CLAUDE.md sans le modifier ni le regénérer ».

---

## 13. Ce que Claude Code ne doit JAMAIS faire

- Committer, pusher, gérer des branches (toute opération git).
- Ajouter `Co-Authored-By` ou toute mention d'IA.
- `git add .` (toujours explicite).
- Modifier/regénérer ce CLAUDE.md de sa propre initiative.
- Committer un secret.
- Lancer des commandes liées à des outils non encore installés (vérifier l'état réel avant).

---

*CLAUDE.md — CréaPrêt — v1 (avec liens documentaires vérifiés). Dérivé du socle CreaSlot,
adapté au domaine du prêt. À enrichir après validation explicite.*
