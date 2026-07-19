# CréaPrêt — Expression de besoins

> **Projet de formation (CDA — TP-01281 millésime 04).** Ce projet n'est pas issu d'une
> commande réelle : conformément au référentiel d'évaluation (REV CDA), le candidat formule
> lui-même l'expression de besoins pour définir les objectifs **et les limites** du projet.
> CréaPrêt est un outil que je proposerais au Cnam La Réunion en complément de CreaSlot
> (gestion des rendez-vous).
>
> *Nom de travail : CréaPrêt — à valider.*

---

## 1. Contexte et problématique

Le Cnam La Réunion (Centre du Port) met à disposition de ses auditeurs et de son personnel
un parc de **matériel pédagogique** : vidéoprojecteurs, ordinateurs portables, kits de
travaux pratiques réseau et électronique, appareils de mesure, matériel audiovisuel.

Aujourd'hui, la gestion de ces prêts repose sur des moyens non outillés (tableur partagé,
échanges par courriel, cahier papier), ce qui génère des dysfonctionnements récurrents :

- **Doubles réservations** : deux personnes réservent le même exemplaire sur des périodes qui
  se chevauchent, faute de contrôle de disponibilité en temps réel.
- **Perte de traçabilité** : impossible de savoir rapidement qui détient quel matériel, ni
  depuis quand.
- **Absence de vision d'inventaire** : aucun état consolidé du parc (nombre d'exemplaires,
  lesquels sont disponibles, en maintenance ou hors service).
- **Oublis de retour** : aucun rappel automatique, d'où des retards et des indisponibilités
  prolongées.
- **Absence de vision décisionnelle** : ni statistiques d'utilisation, ni historique fiable
  pour arbitrer les achats de matériel.

CréaPrêt outille ce processus de bout en bout, de la demande de prêt jusqu'au retour, avec
un **suivi d'inventaire** par exemplaire, un contrôle strict de la disponibilité et une
traçabilité conforme au RGPD.

---

## 2. Objectifs

1. Offrir un **catalogue** consultable du matériel, avec sa disponibilité en temps réel.
2. Assurer un **suivi d'inventaire** : connaître à tout moment le nombre d'exemplaires d'un
   matériel et l'état de chacun.
3. Permettre à un emprunteur de **demander un prêt** sur une période, et à un gestionnaire de
   le **valider ou le refuser**.
4. **Garantir l'absence de double prêt** d'un même exemplaire sur des périodes qui se
   chevauchent, y compris en cas d'accès concurrents.
5. **Automatiser les notifications** (confirmation, rappel de retour la veille, alerte de
   retard).
6. Assurer la **traçabilité** des actions sensibles (RGPD) et la **conformité** réglementaire
   (RGAA, mentions légales).
7. Fournir aux gestionnaires un **tableau de bord** d'aide à la décision (matériel le plus
   emprunté, taux d'utilisation, prêts en retard).

---

## 3. Périmètre

### Inclus (dans le périmètre)

- Gestion des comptes et des trois rôles (emprunteur, gestionnaire, super-administrateur).
- Catalogue du matériel, organisé par catégories.
- **Gestion d'inventaire** : exemplaires de chaque matériel et suivi de leur état.
- Cycle de vie complet d'un prêt : demande → validation/refus → prêt en cours → retour.
- Contrôle de disponibilité avec gestion de la concurrence (verrou pessimiste).
- Notifications transactionnelles par courriel.
- Tableau de bord et statistiques d'utilisation.
- Journal des actions d'administration (RGPD) et purge automatisée.
- Pages légales (mentions, CGU, confidentialité RGPD, accessibilité RGAA).

### Exclus (hors périmètre, limites du projet)

- **Réservation de salles** : une salle de formation est prioritairement occupée par les
  cours du **planning pédagogique** du Cnam. Une réservation de salles ignorant ce planning
  afficherait des disponibilités erronées (défaut d'intégrité des données). La traiter
  correctement supposerait l'**intégration au planning pédagogique**, hors périmètre de ce
  projet. La réservation de salles est donc **volontairement exclue**.
- **Gestion financière** (caution, facturation) : non traitée.
- **Traçabilité physique par codes-barres / QR / puce** : le suivi d'inventaire reste logique
  (saisie manuelle des états par le gestionnaire), sans lecture matérielle.
- **Application mobile native** : l'application est responsive, mais pas d'app native.
- **Intégration à un annuaire existant** (LDAP/SSO Cnam) : authentification autonome.
- **Multi-établissements** : périmètre limité au Centre du Port.

*Ces limites sont assumées : elles cadrent un projet réalisable dans le temps imparti tout en
couvrant l'ensemble des compétences visées, et évitent de modéliser des besoins qui ne
peuvent l'être correctement sans brique externe (cas des salles).*

---

## 4. Acteurs et rôles

| Rôle | Description | Droits principaux |
|---|---|---|
| **Emprunteur** (auditeur) | Utilisateur final qui emprunte du matériel. | Consulter le catalogue et les disponibilités, demander un prêt, consulter et suivre ses propres prêts, annuler une demande non encore validée. |
| **Gestionnaire** (personnel) | Responsable du parc, de l'inventaire et des prêts. | Valider/refuser les demandes, enregistrer les retours et l'état au retour, gérer le catalogue (matériel, catégories) **et l'inventaire (exemplaires, états)**, consulter le calendrier de disponibilité. |
| **Super-administrateur** | Administrateur de l'application. | Gérer les comptes utilisateurs (création, activation/désactivation, rôles), consulter le tableau de bord et les statistiques, consulter le journal RGPD. |

La hiérarchie des rôles est cumulative : `SUPER_ADMIN ⊃ GESTIONNAIRE ⊃ EMPRUNTEUR`
(un super-administrateur peut agir comme gestionnaire, etc.), reprenant l'architecture de
sécurité éprouvée sur CreaSlot.

---

## 5. Modèle de données — concepts clés

Le domaine distingue **trois niveaux** dont la séparation est structurante. Les confondre
(par exemple prêter « un modèle » plutôt qu'« un appareil précis ») rendrait impossibles le
suivi d'inventaire et le contrôle de disponibilité.

### 5.1 Le Matériel

Un **Matériel** est un modèle d'équipement du catalogue — par exemple « Vidéoprojecteur Epson
EB-2247U ». Il porte les attributs descriptifs (`nom`, `description`, `marque`, `modele`,
`reference`) et appartient à une **Catégorie** (informatique, audiovisuel, mesure…). Le
matériel n'est pas prêté directement : c'est un exemplaire physique qui l'est.

### 5.2 L'Exemplaire (le cœur de l'inventaire)

Un **Exemplaire** est une **unité physique** d'un matériel. Le Cnam peut posséder trois
vidéoprojecteurs identiques : ce sont **trois exemplaires** d'un même Matériel. Chaque
exemplaire porte :

- un **numéro d'inventaire** unique (identifiant physique) ;
- un **état d'inventaire** parmi : `DISPONIBLE`, `PRÊTÉ`, `EN_MAINTENANCE`, `HORS_SERVICE`,
  `PERDU`.

C'est **l'exemplaire** que l'on prête. Le suivi d'inventaire consiste à maintenir l'état de
chaque exemplaire à jour, notamment au retour d'un prêt.

### 5.3 Le Prêt

Un **Prêt** rattache **un exemplaire précis** à **un emprunteur**, sur une **période**
`[date de début, date de fin]` exprimée en date + heure (`datetime`). Il suit un cycle de
vie : `DEMANDÉ` → `VALIDÉ` (prêt en cours) → `RETOURNÉ` ; ou `DEMANDÉ` → `REFUSÉ` (avec
motif) ; ou `DEMANDÉ` → `ANNULÉ` (par l'emprunteur, avant validation). Le retour renseigne
l'**état de l'exemplaire au retour**, ce qui peut le faire basculer en `EN_MAINTENANCE` s'il
revient endommagé.

### 5.4 Vue d'ensemble des associations

```
Catégorie (1) ──< (N) Matériel (1) ──< (N) Exemplaire (1) ──< (N) Prêt >── (1) Utilisateur
```

*(Formalisé en MCD/MLD dans le livrable de conception dédié.)*

---

## 6. Besoins fonctionnels

### 6.1 Emprunteur

- **BF-1** : s'inscrire et se connecter (mot de passe conforme à une politique forte).
- **BF-2** : consulter le catalogue du matériel, filtrable par catégorie.
- **BF-3** : consulter la **disponibilité** d'un matériel sur un calendrier (nombre
  d'exemplaires disponibles sur la période demandée).
- **BF-4** : demander un prêt en précisant le matériel et la période souhaitée.
- **BF-5** : consulter la liste de ses prêts (demandés, en cours, historiques) et leur état.
- **BF-6** : annuler une demande tant qu'elle n'est pas validée.

### 6.2 Gestionnaire

- **BF-7** : consulter les demandes en attente et les **valider ou refuser** (avec motif).
- **BF-8** : enregistrer un **retour** et l'**état de l'exemplaire** au retour.
- **BF-9** : gérer le **catalogue** (créer/modifier un matériel, gérer les catégories).
- **BF-10** : gérer l'**inventaire** — ajouter/retirer des exemplaires d'un matériel, changer
  l'état d'un exemplaire (mise en maintenance, hors service).
- **BF-11** : consulter l'**état du parc** (inventaire consolidé : par matériel, nombre
  d'exemplaires par état).
- **BF-12** : consulter un **calendrier global** d'occupation du parc.

### 6.3 Super-administrateur

- **BF-13** : gérer les comptes (création, rôles, activation/désactivation).
- **BF-14** : consulter un **tableau de bord** (matériel le plus emprunté, taux
  d'utilisation, prêts en retard) avec graphiques.
- **BF-15** : consulter le **journal des actions** d'administration (RGPD).

---

## 7. Règles de gestion (invariants métier)

> **RG-1 — Non-chevauchement des prêts actifs.** Un même **exemplaire** ne peut faire l'objet
> de deux prêts **actifs** (statut `VALIDÉ`/en cours) dont les périodes se chevauchent. Cette
> règle doit être garantie **même en cas de validations concurrentes** par deux gestionnaires.

C'est le cœur technique du projet. Sa garantie repose sur une transaction avec **verrou
pessimiste** (`PESSIMISTIC_WRITE`) sur l'exemplaire concerné, suivie d'une re-vérification de
disponibilité après acquisition du verrou, avant confirmation du prêt. Ce pattern, éprouvé
sur CreaSlot pour la réservation de créneaux, est transposé ici à la réservation
d'exemplaires sur une période.

> **RG-2 — Prêt limité aux exemplaires disponibles.** Un exemplaire dont l'état est
> `EN_MAINTENANCE`, `HORS_SERVICE` ou `PERDU` ne peut pas être proposé au prêt ni validé.

> **RG-3 — Mise à jour de l'inventaire au retour.** À l'enregistrement d'un retour,
> l'exemplaire repasse `DISPONIBLE` par défaut, ou est basculé en `EN_MAINTENANCE` si le
> gestionnaire signale un dommage. Le prêt passe au statut `RETOURNÉ`.

> **RG-4 — Disponibilité affichée = exemplaires libres sur la période.** La disponibilité
> présentée à l'emprunteur (BF-3) correspond au nombre d'exemplaires `DISPONIBLE` du matériel
> **non déjà engagés** par un prêt actif chevauchant la période demandée.

---

## 8. Besoins non fonctionnels

| Domaine | Exigence |
|---|---|
| **Sécurité** | Authentification robuste (hachage argon2id), autorisation par rôles et *Voters*, protection CSRF, en-têtes de sécurité (CSP à nonce, HSTS), validation systématique des entrées côté serveur. Respect des recommandations OWASP et ANSSI. |
| **Confidentialité (RGPD)** | Minimisation des données, journal des actions sensibles avec durée de conservation bornée et purge automatisée, information des personnes, consentement à l'inscription. |
| **Accessibilité (RGAA)** | Composants accessibles (rôles ARIA, libellés, navigation clavier), retours visuels de chargement, contrastes conformes. |
| **Éco-conception (RGESN)** | Ressources front self-hostées (pas de CDN), requêtes optimisées (pas de N+1), sobriété des échanges réseau. |
| **Performance** | Réponses interactives ; disponibilité chargée par fenêtre d'affichage, pas en bloc. |
| **Portabilité / exploitation** | Environnement conteneurisé (Docker), séparation dev/préprod/prod, déploiement automatisé (CI/CD), supervision. |

---

## 9. Contraintes techniques (stack imposée par le candidat)

- **Back-end** : PHP 8.4, Symfony 8.0, Doctrine ORM 3 + DBAL 4.
- **Base de données** : MySQL 8.
- **Front-end** : Twig 3, Bootstrap 5, AssetMapper + Stimulus + Turbo (sans Node.js),
  FullCalendar (self-hosté), Chart.js (self-hosté).
- **Tests** : PHPUnit (tests unitaires, d'intégration et de sécurité).
- **Qualité (outillée dès l'initialisation du projet)** : PHP-CS-Fixer (PER Coding Style),
  PHPStan (niveau 8), **analyse SonarCloud avec Quality Gate sur les nouvelles lignes**. Ces
  outils objectivent les critères « règles de nommage conformes », « code documenté » (CP3)
  et « procédures qualité mises en œuvre » (CP4), et s'exécutent dans le pipeline CI.
- **Infrastructure** : Docker, Caddy (reverse-proxy + TLS), Brevo (courriels
  transactionnels), Messenger (traitement asynchrone).
- **CI/CD** : GitHub Actions (intégration continue + déploiement préprod/prod).

Ce choix reproduit délibérément la stack de CreaSlot, afin de capitaliser sur les patterns
d'architecture, de sécurité et d'outillage déjà maîtrisés — et d'installer la chaîne qualité
**dès le premier commit** (CI verte d'entrée, maturité DevOps démontrée).

---

## 10. Couverture des compétences du REAC (aperçu)

| Activité type | Compétence | Comment CréaPrêt la mobilise |
|---|---|---|
| AT1 | CP1 — Environnement de travail | Docker, Git/GitHub, CI/CD, gestion de projet Trello. |
| AT1 | CP2 — Interfaces utilisateur | Catalogue, calendrier de disponibilité, inventaire, tableau de bord ; responsive, RGAA. |
| AT1 | CP3 — Composants métier | Services de gestion des prêts, de l'inventaire, des notifications ; POO, tests, qualité outillée. |
| AT1 | CP4 — Gestion de projet | Planning, itérations, DoD, Quality Gate SonarCloud. |
| AT2 | CP5 — Analyse des besoins et maquettes | Le présent document + cas d'usage + maquettes. |
| AT2 | CP6 — Architecture logicielle | Architecture en couches Controller → Service → Repository → Entity ; DTO, Voters, EventListeners. |
| AT2 | CP7 — Base de données relationnelle | MCD/MLD, migrations, intégrité référentielle, index de disponibilité, jeu d'essai, sauvegarde/restauration. |
| AT2 | CP8 — Composants d'accès aux données | Repositories, **verrou pessimiste** (RG-1), trigger + procédure stockée d'audit. |
| AT3 | CP9 — Plans de tests | Tests unitaires, d'intégration et de sécurité. |
| AT3 | CP10 — Préparer le déploiement | Runbook, environnements séparés, documentation. |
| AT3 | CP11 — Mise en production DevOps | Pipeline CI/CD, supervision, reverse-proxy TLS. |

---

*Document de conception — CréaPrêt — version 3 (périmètre recentré sur le prêt de matériel ;
réservation de salles exclue avec justification d'intégrité ; modèle `Matériel → Exemplaire →
Prêt` sans héritage). À enrichir : diagramme de cas d'usage, MCD/MLD, dossier d'architecture,
diagramme de séquence du prêt.*
