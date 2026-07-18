# Audit de sécurité — CréaPrêt (OWASP Top 10 2021)

Audit **statique et applicatif** de la posture de sécurité de CréaPrêt. Chaque mesure affirmée a été
**vérifiée dans le code** ; les mesures absentes sont signalées comme telles.

---

## 1. Objet et méthode

- **Périmètre** : code applicatif (`src/`), configuration de sécurité (`config/packages/security.yaml`),
  en-têtes applicatifs (`src/EventListener/SecuriteEnTetesListener.php`), gabarits (`templates/`),
  composition de production (`compose.prod.yml`) et dépendances (`composer.lock`).
- **Hors périmètre** : test d'intrusion externe, audit de configuration du serveur, et les en-têtes posés
  par le reverse-proxy (qui appartient à l'autre application — cf. `docs/architecture-deploiement.md`).
- **Date de l'audit** : 18/07/2026. **Version auditée** : état courant de la branche `develop` (dernière
  suite verte : 206 cas, 670 assertions ; PHPStan niveau 8 « No errors » ; PHP-CS-Fixer sans écart).
- **Démarche** : (1) revue de la posture applicative mappée sur l'**OWASP Top 10 (2021)**, catégorie par
  catégorie, chaque mesure étant confrontée au fichier qui l'implémente ; (2) contrôle des dépendances
  par `composer audit` (§4). Les preuves automatisées des contrôles sont détaillées dans
  `docs/plan-de-tests.md` §7 et ne sont pas redétaillées ici.

---

## 2. Revue par catégorie OWASP

### A01 — Contrôle d'accès rompu — ✅ Couvert

| Mesure vérifiée | Fichier / mécanisme |
|---|---|
| Hiérarchie cumulative des rôles | `security.yaml` : `ROLE_SUPER_ADMIN > ROLE_GESTIONNAIRE > ROLE_EMPRUNTEUR` |
| Cloisonnement par zone (sans attrape-tout `^/`) | `security.yaml` `access_control` : `^/admin`→SUPER_ADMIN, `^/gestion`→GESTIONNAIRE, `^/catalogue`/`^/pret`→authentifié, pages légales publiques |
| Autorisation à l'instance (anti-IDOR) | `src/Security/Voter/PretVoter.php` (un emprunteur ne voit/annule que ses prêts) |
| Autorisation d'administration | `src/Security/Voter/UtilisateurVoter.php` + **garde anti-soi** ; `#[IsGranted]` dans **12 contrôleurs** (défense en profondeur) |
| Garde-fou anti-verrouillage | `src/Controller/Admin/CompteController.php` : refus de désactiver le dernier super-administrateur actif (invariant global **avant** le Voter) |

**Réserve** : néant. La défense est multi-niveau (`access_control` + `#[IsGranted]` + Voters).

### A02 — Défaillances cryptographiques — ⚠️ Partiel (dépend du déploiement)

| Mesure vérifiée | Fichier |
|---|---|
| Hachage des mots de passe **argon2id** (tous environnements) | `security.yaml` `password_hashers` (coût de test abaissé à `time_cost: 3` en env test) |
| CSRF activé sur la connexion | `security.yaml` `form_login: enable_csrf: true` |
| Transport chiffré (HTTPS/HSTS) | HSTS posé par l'application si requête sécurisée (`SecuriteEnTetesListener`) ; TLS terminé par le proxy |
| Proxies de confiance | `SYMFONY_TRUSTED_PROXIES` injecté en production (`compose.prod.yml`) pour une IP client fiable derrière le proxy |

**Réserve** : le **certificat TLS réel** (ACME) et la **valeur effective** de `TRUSTED_PROXIES` dépendent
du serveur cible ; validés au déploiement, hors périmètre de cet audit statique.

### A03 — Injection — ✅ Couvert

| Mesure vérifiée | Constat |
|---|---|
| Accès aux données **paramétrés** (Doctrine ORM/DBAL) | `src/Repository/**` : aucune concaténation de SQL natif ; les rares requêtes DBAL (purge d'audit) utilisent des **paramètres nommés** |
| Auto-échappement des gabarits | `templates/**` : **aucun `\|raw`** sur donnée utilisateur (vérifié) |
| Validation des entrées côté serveur | Form Types (`src/Form/*Type.php`) + contrainte `MotDePasseFort` (`src/Validator/`) |

**Réserve** : néant.

### A04 — Conception non sécurisée — ✅ Couvert

| Mesure vérifiée | Fichier |
|---|---|
| **Verrou pessimiste** sur accès concurrents (RG-1) | `src/Service/PretService.php` : `LockMode::PESSIMISTIC_WRITE` (`SELECT … FOR UPDATE`) sur l'exemplaire **puis re-vérification** |
| **Suppression logique** (jamais de suppression physique de compte) | `estActif` (désactivation) ; aucune route de suppression d'utilisateur |
| **Minimisation des données** (RGPD) | `src/Entity/Utilisateur.php` : ni téléphone ni adresse ; seulement e-mail, nom, prénom, rôle, statut, dates |

**Réserve** : néant.

### A05 — Mauvaise configuration de sécurité — ✅ Couvert

| Mesure vérifiée | Fichier |
|---|---|
| **CSP à nonce** (`script-src 'self' 'nonce-…'`, sans `unsafe-inline`/`unsafe-eval`) | `src/EventListener/SecuriteEnTetesListener.php` + `src/Twig/CspExtension.php` (HTML uniquement, hors `dev`, hors JSON) |
| En-têtes durcis | même listener : `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `X-Frame-Options: DENY`, HSTS conditionnel |
| Mode production | `APP_ENV=prod` câblé (`compose.prod.yml`) ; **web-profiler en `dev` uniquement** (`config/packages/web_profiler.yaml` : `when@dev`) |
| Secrets **hors dépôt** | `.env.deploy.local` / `.env.*.local` **gitignorés** (`.gitignore` : `/.env.*.local`) ; gabarit `.env.deploy.example` sans valeurs |

**Réserve** : `style-src 'unsafe-inline'` conservé (attributs `style=` non nonçables) — compromis assumé,
**DT-9**.

### A06 — Composants vulnérables et obsolètes — ✅ Couvert (voir §4)

| Mesure vérifiée | Constat |
|---|---|
| Versions **pinnées** | `composer.json` : `php >=8.4`, `symfony/* 8.0.*`, `doctrine/orm ^3.6`, `twig ^2.12\|^3.0` |
| Verrou de dépendances versionné | `composer.lock` présent et suivi |
| Mécanisme de mise à jour | `composer update` dans les contraintes + `composer audit` (§4) |

**Réserve** : levée — `composer audit --locked` ne remonte **aucune vulnérabilité connue** (§4).

### A07 — Échecs d'identification et d'authentification — ✅ Couvert

| Mesure vérifiée | Fichier |
|---|---|
| **Anti-brute-force** | `security.yaml` : `login_throttling: max_attempts: 5` (par identifiant + IP) |
| **Politique de mot de passe** (≥ 12 caractères, majuscule/minuscule/chiffre/spécial) | `src/Validator/MotDePasseFort*.php` |
| **Comptes désactivés refusés** | `src/Security/UserChecker.php` : `DisabledException` si `!isEstActif()` |
| Messages d'authentification **neutres** | « Identifiants invalides. » (pas de distinction e-mail/mot de passe) |
| CSRF sur le formulaire de connexion | `security.yaml` `enable_csrf: true` |

**Réserve** : la **journalisation des échecs** d'authentification n'est pas en place (voir A09).

### A08 — Défaillances d'intégrité logicielle et des données — ✅ Couvert

| Mesure vérifiée | Constat |
|---|---|
| **Ressources auto-hébergées** | `assets/vendor/` : Bootstrap, Stimulus/Turbo, Popper, FullCalendar, Chart.js, Inter, icônes — **aucun CDN tiers** en production |
| Images **étiquetées par empreinte** | `.github/workflows/build-push.yml` : `ghcr.io/sgahovey/creapret:<github.sha>` (un SHA = une image immuable) |
| Intégrité de la chaîne | CI GitHub Actions (cs-fixer, phpstan, phpunit) ; `composer.lock` |

**Réserve** : le rechargement à chaud FrankenPHP charge deux scripts depuis un CDN, mais **uniquement en
développement** (bloc conditionné par `FRANKENPHP_HOT_RELOAD`, absent en production).

### A09 — Défaillances de journalisation et de supervision — ⚠️ Partiel

| Mesure vérifiée | Fichier |
|---|---|
| **Journal d'administration** *append-only* (décisions de prêt, actions de compte) | `src/Service/JournalAdminService.php`, entité `JournalAdmin` |
| **Déclencheur SQL** traçant les modifications sensibles de compte (rôle, activation) | migration `Version20260707182041` (`trg_historique_utilisateur`) |
| Purge bornée (rétention RGPD) | `src/Command/PurgeAuditCommand.php` |

**Trou identifié (vérifié)** : **la journalisation des évènements d'authentification (connexion réussie,
échec, compte désactivé) n'est PAS implémentée** — aucun listener d'évènements d'authentification, aucun
canal Monolog dédié. Le `login_throttling` (A07) limite le brute-force mais **les tentatives ne laissent
aucune trace applicative**. Risque **moyen** (pas de piste d'investigation en cas d'attaque). Recommandé
en §6.

### A10 — Falsification de requêtes côté serveur (SSRF) — ✅ Non applicable

| Constat vérifié |
|---|
| L'unique sortie réseau côté serveur est l'**envoi d'e-mail** vers l'endpoint du routeur (`MAILER_DSN`, fixe et configuré) |
| **Aucun appel sortant piloté par l'utilisateur** (pas de `file_get_contents`/`HttpClient`/`curl` sur une URL fournie) ; la récupération du calendrier est un `fetch` **côté client** vers l'API **même origine** |

**Réserve** : néant.

---

## 3. Mesures complémentaires (hors nomenclature)

- **Jetons CSRF anti-rejeu** sur toutes les actions mutantes : jetons **dédiés par action** —
  `csrf_token('valider' ~ id)`, `'retour' ~ id`, `'annuler' ~ id`, `'activation' ~ id`,
  `'supprimer_<entité>_' ~ id` — vérifiés côté contrôleur (`isCsrfTokenValid`) avant tout effet.
- **Contrôleur Stimulus de protection CSRF** (`assets/controllers/csrf_protection_controller.js`).
- **Invariant métier verrouillé** (RG-1) : non-chevauchement des prêts validés, garanti même en
  concurrence (verrou pessimiste + re-vérification).
- **Gardes d'administration** anti-soi (un super-administrateur ne peut ni se retirer son rôle ni se
  désactiver) et anti-verrouillage (dernier super-administrateur actif protégé).
- **Confirmation explicite** et **refus des croisements d'environnement** sur la restauration de base
  (`scripts/restore-db.sh`), suite à un incident réel (cf. `docs/runbook-deploiement.md` §6).

---

## 4. Contrôle des dépendances

**Commande** :
```bash
composer audit --locked
```

**Exécution** : lancée le **18 juillet 2026** sur le **serveur de production**, dans un **conteneur
jetable montant le dépôt**.

**Résultat** : **aucune vulnérabilité connue** signalée pour les dépendances déclarées dans le fichier de
verrouillage.

L'option `--locked` fait porter l'audit sur le **fichier de verrouillage** (`composer.lock`) et **non sur
les paquets installés** dans le contexte d'exécution : c'est **exactement** l'ensemble des versions
**embarquées dans l'image de production** (`composer install` restitue ce verrou). Le contrôle est donc
représentatif de ce qui tourne réellement.

**À reconduire périodiquement** : une dépendance saine aujourd'hui peut se révéler vulnérable demain (un
avis de sécurité peut être publié après coup, sans changement de version). Ce contrôle **gagnerait à être
intégré à la chaîne d'intégration continue** (job dédié `composer audit --locked`), pour une vérification
à chaque évolution du verrou.

**Vérifiable par ailleurs** : les contraintes sont **pinnées** (`symfony/* 8.0.*`, `doctrine/orm ^3.6`,
`twig ^2.12|^3.0`) et le `composer.lock` est **versionné**.

---

## 5. Écarts et réserves

| Écart | Catégorie | Risque estimé | Justification / renvoi |
|---|---|:--:|---|
| **Journalisation des échecs d'authentification absente** | A09 | **Moyen** | Aucune trace des tentatives ; le throttling limite le brute-force mais sans piste d'investigation. Recommandé §6. |
| Contrôle des dépendances récurrent | A06 | **Faible** | `composer audit --locked` = **0 vulnérabilité** au 18/07/2026 (§4) ; à reconduire périodiquement (idéalement en intégration continue). |
| Certificat TLS réel + valeur effective de `trusted_proxies` | A02/A05 | Faible | Dépendent du serveur cible ; validés au déploiement. |
| `style-src 'unsafe-inline'` conservé | A05 | Faible | Attributs `style=` non nonçables — compromis assumé, **DT-9**. |
| Mot de passe des comptes créés par un administrateur (transmis hors application, sans réinitialisation autonome) | A07 | Faible | Limite assumée, **DT-8** ; à lever avec une réinitialisation par courriel. |
| **Redirection par rôle après connexion non implémentée** | — | Faible | La redirection est statique (`app_home`) ; la landing est adaptative par rôle, mais aucune redirection ciblée n'est en place ni testée. |
| **Audit d'accessibilité (RGAA) formel non mené** | — | Faible | Repères, libellés et équivalents textuels en place ; l'audit RGAA formel reste à réaliser (cf. `docs/plan-de-tests.md` §9). |

---

## 6. Recommandations (priorisées)

| Priorité | Recommandation | Effort estimé |
|:--:|---|:--:|
| **1** | **Automatiser `composer audit --locked`** dans l'intégration continue (job dédié) : le contrôle du 18/07/2026 est propre (0 vulnérabilité), reste à le rendre récurrent pour capter un avis publié après coup (A06). | Faible |
| **2** | **Journaliser les évènements d'authentification** : canal Monolog `security` dédié (handler non bufferisé) + listeners connexion réussie / échec / compte désactivé (A09). | Moyen |
| **3** | Au déploiement, **certificat ACME réel** + valeur de `trusted_proxies` adaptée au serveur (A02/A05). | Faible (déploiement) |
| **4** | **Réinitialisation de mot de passe par courriel** (lien à usage unique), levant DT-8 et permettant un changement forcé à la première connexion. | Moyen |
| **5** | **Audit d'accessibilité (RGAA)** formel, et décision sur la redirection par rôle après connexion. | Moyen |

---

*Audit de sécurité CréaPrêt (OWASP Top 10 2021) — constats vérifiés dans le code au 18/07/2026. Les
preuves automatisées sont dans `docs/plan-de-tests.md` ; la dette technique dans
`docs/DETTE_TECHNIQUE.md`.*
