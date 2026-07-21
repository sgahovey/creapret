# Plan de tests — CréaPrêt

Document de référence de la stratégie et de l'exécution des tests de CréaPrêt (application de gestion de
prêt de matériel, données fictives, démonstrateur CDA). Ton opérationnel : il décrit ce qui est
réellement testé, à quel niveau, avec quels chiffres — vérifiables dans `tests/`.

---

## 1. Objet et périmètre

**Couvert par ce plan** : la stratégie de test, l'environnement d'exécution, la cartographie chiffrée
de la suite, la traçabilité des règles de gestion et des besoins fonctionnels vers les tests, trois
jeux d'essai détaillés, les tests de sécurité, la procédure d'exécution et les résultats, enfin les
limites assumées.

**Non couvert (et pourquoi)** :
- le **rendu visuel** et l'**ergonomie** (mise en forme, contraste, adaptation aux tailles d'écran) :
  hors de portée des tests fonctionnels HTTP, qui vérifient des textes et des codes de statut, pas une
  apparence (cf. §9) ;
- le **comportement JavaScript** (contrôleurs Stimulus, calendrier, graphique) : aucun exécuteur de
  test front n'est en place ;
- les **tests de charge / performance** : un **degré élevé de concurrence simultanée** (N validateurs en
  parallèle) et une **sollicitation soutenue** ne sont pas mesurés — hors périmètre d'un démonstrateur.
  La **course parallèle réelle**, elle, **est** couverte : deux processus système se disputent réellement
  le verrou (cf. §6.1) ; seul son **degré** reste hors périmètre (cf. §9, point 3).

---

## 2. Stratégie

La suite est organisée en **niveaux pratiques**, chacun avec un rôle précis :

- **Unitaire** (`PHPUnit\Framework\TestCase`, sans base) — logique pure, sans effet de bord : **entités**
  (cycle de vie, calculs comme `Pret::estEnRetard`), **énumérations** (valeurs *backed* persistées,
  libellés, couleurs de badge), **validateur** de mot de passe fort, **voteur** d'autorisation.
- **Intégration** (`KernelTestCase`, transaction annulée en fin de test) — **services métier** et
  **accès aux données** : `PretService` (validation, refus, retour), disponibilité et requêtes DQL des
  **repositories**, **déclencheur d'audit** SQL, **commandes** planifiées.
- **Fonctionnel (système)** (`WebTestCase`) — **parcours HTTP** de bout en bout : catalogue,
  demande/validation/refus/retour de prêt, espace emprunteur, inventaire, tableau de bord, journal,
  administration des comptes.
- **Sécurité** — transversal : refus d'accès par rôle (403), rejet des jetons CSRF invalides, validation
  des entrées, politique de mot de passe, journalisation des actions sensibles, en-têtes de sécurité.

**Correspondance de vocabulaire** : le niveau nommé ici **« Fonctionnel (système) »** (`WebTestCase`)
correspond aux **tests système** du référentiel CDA — l'application y est sollicitée **assemblée et de
bout en bout**, par requête HTTP réelle, et non composant par composant. Les deux appellations désignent
le même niveau ; « fonctionnel » renvoie à l'outillage (Symfony), « système » au référentiel.

**Équilibre retenu** : une **pyramide** classique (davantage d'unitaire/intégration rapides, un socle
fonctionnel ciblé sur les parcours à risque). Les **parcours critiques sont couverts à plusieurs
niveaux** : la validation d'un prêt est vérifiée en **intégration** (`PretService`, invariant
transactionnel) *et* en **fonctionnel** (`GestionPretController`, effets de bord et journalisation) ;
l'administration des comptes l'est en **unitaire** (Voter anti-soi), **intégration** (repository) et
**fonctionnel** (contrôleur, garde-fous). Cette redondance ciblée protège la logique la plus sensible
(RG-1, anti-verrouillage administrateur) contre une régression qui échapperait à un seul niveau.

---

## 3. Environnement de tests

- **Base dédiée** `creapret_test`, distincte des bases de développement, préproduction et production.
- **Isolation entre exécutions** :
  - tests d'intégration : chaque test s'exécute dans une **transaction annulée** (`rollBack`) en
    `tearDown` — aucune donnée ne subsiste ;
  - tests fonctionnels : **données jetables** créées en `setUp` avec un **marqueur** (préfixe d'e-mail
    ou de nom), **purgées** en `tearDown` (`DELETE ... LIKE 'marqueur%'`) ;
  - **cas particulier du journal d'administration** : *append-only* et **sans clé étrangère**, il n'est
    atteint par **aucune suppression en cascade**, et ses libellés d'acteur/cible sont **figés à
    l'écriture** — ils ne portent donc pas le marqueur du test. Une purge par marqueur ne peut pas y
    être étanche. Les tests qui l'alimentent (validation, refus, retour passant par le contrôleur réel)
    en font une **remise à zéro déterministe** en `tearDown` ; ceux qui n'y écrivent que dans une
    **transaction annulée** n'ont rien à purger. Cette règle corrige une fuite d'isolation réelle,
    consignée et clôturée en **DT-15** ([`DETTE_TECHNIQUE.md`](DETTE_TECHNIQUE.md)).
- **Jeux de données** : construits à la main dans chaque test (graphe minimal catégorie → matériel →
  exemplaire → prêt, comptes par rôle), jamais dépendants de fixtures partagées.
- **Deux sources de peuplement, distinctes des tests** : les **`DataFixtures`** (`ReferenceFixtures`,
  `DemoFixtures`) peuplent le **développement** et la **démonstration locale** via
  `doctrine:fixtures:load` ; le script **`scripts/seed-preprod.sql`** peuple la **préproduction**, où
  l'image est construite en `composer --no-dev` et ne contient donc **pas** le *bundle* de fixtures
  (US-6.1) — d'où un script SQL versionné, exécuté manuellement sur le serveur. Les deux jeux couvrent
  les mêmes cas (même catalogue, mêmes statuts de prêt, mêmes 11 types d'entrées de journal). Ils
  diffèrent en volumétrie de comptes : les fixtures en créent cinq pour varier les profils de
  développement, le seed de préproduction trois — un par rôle — suffisants pour la recette. Les entrées
  de journal sont donc remappées sur les comptes du seed, l'objet du scénario étant l'écran de
  consultation, pas l'identité des personnes tracées. La production, elle, ne reçoit **aucune donnée de
  démonstration**.
- **Outillage qualité** :
  - **PHPStan** niveau **8** (sans *baseline*) ;
  - **PHP-CS-Fixer** (PER Coding Style + règles Symfony) ;
  - **PHPUnit** avec `failOnDeprecation`, `failOnNotice`, `failOnWarning` à `true` — une simple *notice*
    fait échouer la suite.
- **Seuil de couverture sur le code nouveau** : **Quality Gate SonarQube Cloud** appliqué aux **nouvelles
  lignes** (couverture exclue pour `src/DataFixtures/**`, `src/Kernel.php`, `migrations/**`).
- **Exécution en intégration continue** : GitHub Actions, jobs `cs-fixer`, `phpstan`, `phpunit` (sur push
  et *pull request* des branches `develop`, `preprod`, `main`).

> **Mesure de couverture** : elle n'est produite **qu'en intégration continue**. L'extension de
> couverture (pcov/xdebug) **n'est pas installée en développement** ; localement, on valide la logique
> (suite verte) et l'analyse statique, la couverture chiffrée étant calculée par la CI puis remontée à
> SonarQube Cloud.

---

## 3bis. Organisation et pilotage des tests

*(Numérotation `3bis` retenue plutôt qu'un décalage de §4 à §9, afin de préserver les renvois internes
et externes déjà établis vers ces sections.)*

### 3bis.1 Qui fait quoi, quand, sur quel environnement

| Niveau de test | Responsable | Déclencheur | Environnement | Outil | Preuve produite |
|---|---|---|---|---|---|
| **Unitaire** | Développeur | Localement à chaque commit ; en CI **à l'ouverture et à chaque mise à jour d'une *pull request*** vers `develop`/`preprod`/`main`, ainsi qu'au **push direct** de ces trois branches | Poste WSL2/Docker, puis *runner* `ubuntu-latest` | PHPUnit (`TestCase`) | Sortie `--testdox`, job `phpunit` vert |
| **Intégration** | Développeur | Idem | Poste WSL2/Docker (base `creapret_test`), puis *runner* + service MySQL 8.0 | PHPUnit (`KernelTestCase`, transaction/rollback) | Sortie `--testdox`, job `phpunit` vert |
| **Système** | Développeur | Idem | Idem | PHPUnit (`WebTestCase`, requêtes HTTP réelles) | Sortie `--testdox`, job `phpunit` vert |
| **Non-régression** | Développeur | **Chaque *pull request*** vers `develop`, `preprod`, `main` (et push direct de ces branches) — **un push sur une branche de *feature* ne déclenche rien** | *Runner* CI | PHPUnit — **la suite complète est rejouée intégralement** | Job `phpunit` vert ; un échec bloque la fusion |
| **Sécurité** | Développeur | Idem (transversal, intégré à la suite) | Idem | PHPUnit (§7) + revue OWASP documentée | §7 du présent plan ; [`audit-securite-owasp.md`](audit-securite-owasp.md) |
| **Charge (contention du verrou)** | Développeur | Avec la suite (§3bis.1 *non-régression*) | Poste WSL2/Docker | PHPUnit + `proc_open` (N processus) | `tests/Charge/ValidationConcurrenteChargeTest.php` : durées mesurées, invariant RG-1 |
| **Acceptation** | Développeur *(en tant que représentant utilisateur)* | Après déploiement en préproduction | **Préproduction** (VPS), jeu `scripts/seed-preprod.sql` | Exécution **manuelle** | Grille [`recette-acceptation.md`](recette-acceptation.md), remplie à la main |

> **Stratégie de non-régression — explicitement nommée.** Le projet ne tient pas de campagne de
> non-régression séparée : **la suite complète est rejouée intégralement** à chaque **_pull request_**
> ouverte vers `develop`, `preprod` ou `main`, et à chaque **push direct** sur l'une de ces trois
> branches (`.github/workflows/ci.yml` : `on.push.branches` **et** `on.pull_request.branches` sont
> restreints à ces trois références). Toute régression sur un comportement déjà couvert fait donc
> rougir le job `phpunit` **avant** la fusion.
>
> **Conséquence à ne pas masquer** : un **push sur une branche de *feature* ne déclenche aucune CI**.
> C'est un choix, non un oubli — il **économise des minutes de *runner*** sur les commits intermédiaires
> d'un travail en cours, qui sont nombreux et souvent instables par construction. Le contrôle n'en est
> pas affaibli : la **porte qualité reste la *pull request***, et **aucun lot ne la contourne** puisque
> `develop`, `preprod` et `main` ne reçoivent de code que par fusion de PR. Le prix à payer est un
> retour plus tardif — l'erreur se découvre à l'ouverture de la PR plutôt qu'au push —, ce que compense
> l'exécution locale de la suite avant de pousser.

> **Limite de la démarche, assumée.** Le projet est mené par un **développeur unique en alternance** :
> les rôles de **développeur**, de **testeur** et de **représentant utilisateur** sont tenus par la
> **même personne**. Un projet réel sépare ces rôles — la séparation protège du biais de confirmation
> (on teste mal ce qu'on vient d'écrire, on valide volontiers ce qu'on a conçu). La parade retenue ici
> est de rendre les critères **objectivables et outillés** (analyse statique, seuils de couverture,
> suite rejouée automatiquement) plutôt que soumis au jugement de leur auteur ; elle **atténue** le
> biais, elle ne le supprime pas.

### 3bis.2 Critères d'entrée et de sortie

**Critères d'entrée** — réunis avant toute campagne :

| # | Critère | Vérification |
|---|---|---|
| E1 | Branche à jour sur `develop` | `git pull --ff-only` avant travail |
| E2 | Dépendances installées et cohérentes avec `composer.lock` | `composer install` |
| E3 | Base de test provisionnée (base `creapret_test` + privilèges) | `scripts/init-test-db.sh` |
| E4 | Migrations appliquées sur la base de test | `doctrine:migrations:migrate` (inclus dans E3) |

**Critères de sortie** — tous requis, sans exception :

| # | Critère | Seuil |
|---|---|---|
| S1 | Job `cs-fixer` vert | 0 fichier à corriger |
| S2 | Job `phpstan` vert | Niveau 8, sans *baseline* — « No errors » |
| S3 | Job `phpunit` vert | 0 échec, 0 erreur |
| S4 | Alertes PHPUnit | **0 déprécation, 0 notice, 0 avertissement** (`failOnDeprecation`/`failOnNotice`/`failOnWarning` à `true`) |
| S5 | Quality Gate SonarQube Cloud | Passée **sur les nouvelles lignes** |

**Règle de promotion, formalisée** (reprise du §8) : **un job rouge — test, typage, style, ou simple
*notice* PHPUnit — bloque la promotion.** La suite doit être **franche** : une sortie
« *OK, but there were issues* » est traitée comme un **échec**, non comme un succès nuancé. Aucun seuil
n'est abaissé pour faire passer une campagne ; c'est le code ou les tests qui remontent au seuil
(cf. §8, « Cas vécu »).

### 3bis.3 Gestion des anomalies

**Échelle de sévérité** — chaque niveau est illustré par un cas **réellement survenu** sur le projet :

| Sévérité | Définition | Exemple réel |
|---|---|---|
| **Bloquant** | Empêche l'usage ou **compromet l'intégrité des données** | **Restauration croisée préproduction → production** : l'ancien script ignorait la base transmise, les données de production ont été écrasées par un jeu de démonstration (§6.3) |
| **Majeur** | Fonctionnalité **dégradée sans contournement** | **Interruption du script de création de base** sur le déclencheur : 7 tables créées puis arrêt, ni déclencheur ni procédure — le livrable de rétro-conception était inexploitable d'un bloc (§6.4) |
| **Mineur** | Défaut **sans impact fonctionnel** ; l'usage reste nominal | **Rendu visuel dégradé** non détecté par les tests : champ de formulaire rendu brut faute de thème, survenu à trois reprises (§9.1) |
| **Cosmétique** | Défaut de **forme** uniquement, sans effet sur l'usage ni sur la compréhension | **Accentuation incohérente du texte visible** du front (registre `DETTE_TECHNIQUE.md`, DT-6) |

**Circuit de traitement** :

1. **Détection** — suite automatisée, analyse statique, revue, ou constat manuel (les deux incidents
   bloquant/majeur ci-dessus ont été détectés **à l'exécution**, pas à la lecture).
2. **Qualification** — affectation d'une sévérité selon l'échelle ci-dessus.
3. **Traitement** :
   - **Bloquant ou majeur → correction immédiate**, la campagne ne se poursuit pas ;
   - **Mineur ou cosmétique → inscription au registre** [`DETTE_TECHNIQUE.md`](DETTE_TECHNIQUE.md) sous
     un identifiant **DT-n**, avec constat, conséquence et **condition de levée**.
4. **Vérification** — un **test de non-régression est ajouté à la suite** lorsque le défaut est
   automatisable, de sorte que la suite complète (§3bis.1) protège désormais contre sa réapparition.

> **Ce circuit a réellement été appliqué** : le registre `DETTE_TECHNIQUE.md` compte à ce jour les
> entrées **DT-1 à DT-14**, chacune avec son constat, sa conséquence et sa condition de levée ; et les
> deux incidents des §6.3 et §6.4 ont suivi le chemin complet détection → diagnostic → correction →
> **re-vérification par exécution** (garde-fou de croisement d'environnement, seconde exécution du
> script sur base vierge).

---

## 4. Cartographie (chiffres réels relevés dans `tests/`)

### 4.1 Par niveau

| Niveau | Classe de base | Fichiers | Méthodes |
|---|---|---:|---:|
| Unitaire | `TestCase` / `ConstraintValidatorTestCase` | 16 | 53 |
| Intégration | `KernelTestCase` (transaction/rollback) | 17 | 58 |
| Charge | `KernelTestCase` + `proc_open` (N processus concurrents) | 1 | 1 |
| Fonctionnel (système) | `WebTestCase` | 21 | 93 |
| **Total** | | **55** | **205** |

> **205 méthodes** de test ; la CI dénombre **207 cas**, l'écart venant de `LegalPagesTest` (une méthode
> paramétrée par un fournisseur de données couvrant les 3 pages légales).
>
> Le niveau **charge** est isolé du niveau intégration bien qu'il partage la même classe de base : son
> objet n'est pas de vérifier un comportement en isolation mais de **soumettre l'invariant RG-1 à une
> contention réelle** — `tests/Charge/ValidationConcurrenteChargeTest.php` oppose **10 processus
> système** sur un même exemplaire (cf. §3bis.1, ligne *Charge*).

### 4.2 Par domaine fonctionnel

| Domaine | Fichiers (méthodes) | Σ |
|---|---|---:|
| Sécurité — autorisation | UtilisateurVoter (3), UserChecker (2) | 5 |
| Entités & énumérations | Utilisateur (10), Pret (4), Categorie (3), Exemplaire (3), Materiel (2), EtatExemplaire (3), StatutPret (3), Role (2), TypeActionJournal (2) | 32 |
| Validateur mot de passe | MotDePasseFortValidator (6) | 6 |
| Services métier | AnnulationPret (7), PretService (4), NotificationService (3), NotificationRappelRetard (3), RetourPret (3), PretCalendarSerializer (3), DateFormatter (2), JournalAdminService (2), Statistique (2), ConcurrenceValidation (1), VerrouPessimiste (1), **ValidationConcurrenteCharge (1)** | 32 |
| Repositories, requêtes & trigger | PretRepository (9), ExemplaireDisponibilite (5), ExemplaireLibre (4), HistoriqueUtilisateurTrigger (3), UtilisateurRepository (3), PretRepositoryRappels (2), JournalAdminRepository (2), MaterielRepository (1) | 29 |
| Commandes planifiées | EnvoiRappels (5), PurgeAudit (3) | 8 |
| Fonctionnel — catalogue & emprunteur | Catalogue (9), Pret (5), MesPrets (3), CalendrierPage (2) | 19 |
| Fonctionnel — gestion & inventaire | GestionPret (6), Materiel (7), Categorie (7), Exemplaire (7), Gestion (4), NotificationsPret (4), CalendrierApi (3), TableauDeBord (3), RetourGestion (3), Parc (2), GestionPretJournal (2) | 48 |
| Fonctionnel — administration & sécurité | Compte (9), Inscription (6), Security (5), Journal (3), EnTetesSecurite (2), LegalPages (1) | 26 |
| **Total** | | **205** |

> Réconciliation : 5 + 32 + 6 + 32 + 29 + 8 + 19 + 48 + 26 = **205**, identique au total par niveau.

---

## 5. Matrice de traçabilité

Cette matrice indexe la couverture sur les **besoins fonctionnels** de
l'expression de besoins (BF-1 à BF-15), afin que la conformité au critère
« *le plan de tests couvre l'ensemble des fonctionnalités retenues* » soit
**démontrable ligne à ligne** et non affirmée. Les **règles de gestion**
(RG-1 à RG-4) n'apparaissent pas comme des lignes autonomes : ce sont des
**invariants**, rattachés au besoin fonctionnel qu'ils contraignent.

Niveaux : **U** = unitaire · **I** = intégration · **S** = système
(`WebTestCase`, cf. §2).

### 5.1 Besoins fonctionnels

| BF | Fonctionnalité retenue | Fichiers de tests | Niv. | Cas | Statut |
|---|---|---|:--:|--:|:--:|
| **BF-1** | S'inscrire et se connecter (politique de mot de passe forte) | `InscriptionControllerTest`, `SecurityControllerTest`, `MotDePasseFortValidatorTest`, `UserCheckerTest`, `UtilisateurTest` | U, S | 29 | ✅ |
| **BF-2** | Consulter le catalogue, filtrable par catégorie | `CatalogueControllerTest`, `MaterielTest`, `CategorieTest` | U, S | 14 | ✅ |
| **BF-3** | Consulter la disponibilité sur une période — **RG-4** | `ExemplaireRepositoryDisponibiliteTest`, `ExemplaireRepositoryLibreTest`, `CatalogueControllerTest` (période, `no-store`, dates invalides) | I, S | 12 | ✅ |
| **BF-4** | Demander un prêt — **CU-04** | `PretControllerTest`, `ExemplaireRepositoryLibreTest`, `NotificationsPretTest` | I, S | 10 | ✅ |
| **BF-5** | Consulter ses prêts et leur état | `MesPretsControllerTest`, `AnnulationPretTest`, `PretTest` (`estEnRetard`, `joursDeRetard`) | U, I, S | 9 | ✅ |
| **BF-6** | Annuler une demande non validée | `MesPretsControllerTest` (dont IDOR), `AnnulationPretTest` | I, S | 9 | ✅ |
| **BF-7** | Valider / refuser une demande avec motif — **CU-07, RG-1, RG-2** | `GestionPretControllerTest`, `PretServiceTest`, `VerrouPessimisteTest`, `ConcurrenceValidationTest`, `PretRepositoryTest`, `GestionPretJournalTest`, `NotificationsPretTest` | I, S | 25 | ✅ |
| **BF-8** | Enregistrer un retour et l'état de l'exemplaire — **RG-3** | `RetourPretTest`, `RetourGestionTest`, `NotificationsPretTest` | I, S | 7 | ✅ |
| **BF-9** | Gérer le catalogue (matériel, catégories) | `MaterielControllerTest`, `CategorieControllerTest`, `MaterielTest`, `CategorieTest` | U, S | 19 | ✅ |
| **BF-10** | Gérer l'inventaire (exemplaires, états) | `ExemplaireControllerTest`, `ExemplaireTest`, `EtatExemplaireTest` | U, S | 13 | ✅ |
| **BF-11** | Consulter l'état du parc | `ParcControllerTest`, `MaterielRepositoryTest` | I, S | 3 | ✅ |
| **BF-12** | Calendrier global d'occupation | `CalendrierApiControllerTest`, `CalendrierPageTest`, `PretCalendarSerializerTest` | U, S | 8 | ✅ |
| **BF-13** | Gérer les comptes (création, rôles, activation) | `CompteControllerTest`, `UtilisateurVoterTest`, `UtilisateurRepositoryTest`, `HistoriqueUtilisateurTriggerTest`, `RoleTest` | U, I, S | 20 | ✅ |
| **BF-14** | Tableau de bord et indicateurs | `TableauDeBordControllerTest`, `StatistiqueServiceTest`, `PretTest` (retards) | U, S | 5 | ✅ |
| **BF-15** | Journal des actions d'administration (RGPD) | `JournalControllerTest`, `JournalAdminServiceTest`, `JournalAdminRepositoryTest`, `TypeActionJournalTest`, `PurgeAuditCommandTest`, `GestionPretJournalTest` | U, I, S | 14 | ✅ |

**Les 15 besoins fonctionnels retenus sont couverts, aucun en défaut.** La
distribution des volumes suit la criticité : BF-7 (validation concurrente,
25 cas) et BF-1 (authentification, 29 cas) sont les plus densément
couverts ; BF-11 et BF-14, à faible enjeu d'intégrité, le sont plus
légèrement. Le comptage par BF dépasse 206 : un même cas de test peut
servir plusieurs besoins (un test de notification à la validation couvre à
la fois BF-4 et BF-7), et les parcours critiques sont délibérément couverts
à plusieurs niveaux (cf. §2, redondance ciblée).

### 5.2 Exigences transverses

Exigences issues des besoins non fonctionnels (§8 de l'expression de
besoins) et des user stories, non rattachables à un besoin fonctionnel
unique.

| Exigence | Fichiers de tests | Niv. | Cas | Statut |
|---|---|:--:|--:|:--:|
| Notifications de service, rappel J-1, alerte de retard (respect de `emailRappel`) | `NotificationServiceTest`, `NotificationRappelRetardTest`, `EnvoiRappelsCommandTest`, `PretRepositoryRappelsTest`, `DateFormatterServiceTest` | U, I | 15 | ✅ |
| En-têtes de sécurité (CSP à nonce, `nosniff`, `X-Frame-Options`, `Referrer-Policy`) | `EnTetesSecuriteTest` | S | 2 | ✅ |
| Pages légales — présence et accès sans authentification | `LegalPagesTest` | S | 3 | ✅ |
| Accessibilité RGAA — dispositions implémentées | *(vérifiées à leur niveau ; audit formel non mené)* | — | — | ⚠️ Partiel — limite de **niveau de preuve**, non de couverture (§9.6) |

---

## 6. Jeux d'essai détaillés

Format : données en entrée, résultat attendu, résultat obtenu, analyse d'écart. Résultats obtenus =
exécution automatisée **verte** des fichiers cités (sauf les §6.3 et §6.4, jeux d'essai
**opérationnels** sur les scripts).

### 6.1 RG-1 — Non-chevauchement des prêts validés (fonction la plus représentative)

**Fonctionnalité** : un exemplaire ne peut porter deux prêts actifs se chevauchant, **même en
concurrence**. Garanti par transaction + verrou pessimiste `PESSIMISTIC_WRITE` + **re-vérification
après verrou** dans `PretService::valider`.

**Données d'essai** (jetables) : un exemplaire `DISPONIBLE` ; deux emprunteurs ; deux demandes de prêt
sur des **périodes qui se chevauchent** ; un gestionnaire validateur.

| Action | Entrée | Résultat attendu | Résultat obtenu | Écart |
|---|---|---|---|:--:|
| Valider la 1ʳᵉ demande | gestionnaire valide `pret A` sur l'exemplaire | `pret A` → **VALIDE** ; exemplaire → **PRETE** | Conforme | — |
| Valider la 2ᵉ demande (chevauchante) | gestionnaire valide `pret B` sur le même exemplaire, période chevauchante | **Refus système (CONFLIT)** : `pret B` → **REFUSE** (motif posé) ; aucun 2ᵉ prêt actif | Conforme | — |
| Invariant après coup | état des prêts de l'exemplaire | **exactement un** prêt actif (VALIDE) | Conforme | — |

**Analyse d'écart** : aucun. La **course parallèle réelle** est **effectivement provoquée** :
`ConcurrenceValidationTest` lance **deux processus système distincts** (`proc_open`) qui valident chacun
une demande sur le **même** exemplaire, synchronisés par un **top-départ commun** pour atteindre le
verrou au même instant. Le test reste **déterministe** non parce qu'il éviterait la concurrence, mais
parce que l'**assertion porte sur l'invariant lui-même** — *exactement un* prêt validé — et **non sur
l'identité du processus gagnant** (indéterministe, elle rendrait le test instable). Un **garde-fou
anti-faux-positif** précède le contrôle : le test vérifie d'abord que les deux processus ont réellement
démarré et trouvé leur prêt, faute de quoi deux processus **échouant à démarrer** satisferaient
**trivialement** l'invariant « pas plus d'un validé ». Le **mécanisme de verrou** lui-même est couvert
isolément par `VerrouPessimisteTest` (le verrou est bien posé sur l'exemplaire avant la re-vérification).
Limite résiduelle (degré de concurrence) consignée au §9.

### 6.2 Refus d'auto-désactivation et protection du dernier administrateur

**Fonctionnalité** : un super-administrateur ne peut ni changer son propre rôle ni désactiver son propre
compte ; et le **dernier** super-administrateur actif ne peut être désactivé.

**Données d'essai** : super-administrateur **A** (connecté) ; selon le cas, un second super-administrateur
**B** actif.

| Action | Entrée | Résultat attendu | Résultat obtenu | Écart |
|---|---|---|---|:--:|
| A désactive son propre compte (B existe) | POST activation sur l'id de A, **jeton CSRF valide forgé** | **403** (Voter anti-soi) ; A reste **actif** | Conforme | — |
| A désactive le dernier super-admin actif (A seul) | POST activation sur A, tous les autres super-admins neutralisés | **Refus par l'invariant global** : message « dernier super-administrateur actif » ; A reste **actif** | Conforme | — |
| A modifie un autre compte / change un rôle | POST modification sur B | Autorisé ; **une seule** entrée de journal (`COMPTE_MODIFICATION` ou `COMPTE_CHANGEMENT_ROLE`) | Conforme | — |

**Analyse d'écart** : aucun. L'ordre des gardes est vérifié (invariant global **avant** le Voter). Le
refus d'auto-désactivation est prouvé **en forgeant une requête POST valide** (jeton CSRF injecté dans
la session de test), et non en constatant l'absence de bouton dans l'interface — cf. §7.
Fichiers : `CompteControllerTest`, `UtilisateurVoterTest`.

### 6.3 Sauvegarde et restauration — incident réel

**Fonctionnalité** : sauvegarde compressée cohérente et restauration sûre (confirmation explicite, refus
des croisements d'environnement). Jeu d'essai **opérationnel** (scripts, hors PHPUnit).

**État initial** (jeu de démonstration `scripts/seed-preprod.sql`) : **3 utilisateurs** (un par rôle) et
**6 prêts** couvrant tous les statuts (dont un en cours et un en retard).

| Étape | Entrée | Résultat attendu | Résultat obtenu | Écart |
|---|---|---|---|:--:|
| Incident reproduit | restauration d'une sauvegarde de **préproduction** dans la base de **production**, l'ancien script **ignorant** la base transmise | *(bug)* les données de production sont écrasées | Constaté : **0 prêt** applicatif de production, comptes de démo à identifiants publics installés | **Écart critique** |
| Détection | inspection de la production | comptes `*@creapret.local` de démonstration absents en production | Comptes de démo **détectés** en production | — |
| Correction | restauration depuis la **sauvegarde de production précédente** | retour à l'**état initial** (3 utilisateurs / 6 prêts réels) | Conforme après restauration | Résolu |
| Garde-fou ajouté | tentative « archive préprod → base prod » | **REFUS** (incohérence d'environnement) | Refusé, message explicite | — |

**Jeu d'essai du garde-fou** (détection `preprod` testée **avant** `prod`, sous-chaîne incluse) :

| Archive | Base cible | Résultat |
|---|---|---|
| `..._creapret_preprod_....sql.gz` | `creapret_prod` | **REFUSÉ** |
| `..._creapret_prod_....sql.gz` | `creapret_preprod` | **REFUSÉ** |
| `..._creapret_preprod_....sql.gz` | `creapret_preprod` | autorisé |
| `..._creapret_prod_....sql.gz` | `creapret_prod` | autorisé |
| `sauvegarde_generique.sql.gz` | `creapret_prod` | autorisé (environnement indéterminable) |

**Analyse d'écart** : l'écart initial (base cible ignorée) est **corrigé** — le 2ᵉ argument détermine
désormais la base, et un garde-fou refuse tout croisement d'environnement détecté. Détail dans
`docs/runbook-deploiement.md` §6.

### 6.4 Exécutabilité du script de création de la base — incident réel

**Fonctionnalité** : le script consolidé [`docs/conception/script-creation-bdd.sql`](conception/script-creation-bdd.sql) doit s'exécuter **en un seul bloc** sur une base vierge (destiné à la rétro-conception et à une recréation rapide et lisible). Jeu d'essai **opérationnel** (client de base, hors PHPUnit).

**État initial** : base **vierge** jetable (`creapret_verif`), supprimée en fin d'essai.

| Étape | Entrée | Résultat attendu | Résultat obtenu | Écart |
|---|---|---|---|:--:|
| Première exécution | script transmis **en un seul bloc** à une base vierge | 7 tables + déclencheur + procédure, sans erreur | **7 tables créées** puis **interruption sur une erreur de syntaxe** au niveau du déclencheur ; **ni déclencheur ni procédure** créés | **Écart bloquant** |
| Diagnostic | analyse de l'interruption | — | les **séparateurs d'instruction internes** aux corps de routines étaient interprétés comme des **fins d'instruction**, le script étant transmis d'un bloc alors que les migrations les envoyaient **séparément** | Cause identifiée |
| Correction | **changement du séparateur d'usage** autour de ces définitions, puis rétablissement | script exécutable d'un bloc | déclencheur et procédure encadrés du changement de séparateur | Résolu |
| Seconde exécution | script corrigé, base vierge | 7 tables, 1 déclencheur, 1 procédure, sans erreur | Conforme : **7 tables, 1 déclencheur, 1 procédure**, **aucune erreur** | — |
| Nettoyage | suppression de la base de vérification | base jetable supprimée | Base `creapret_verif` supprimée | — |

**Analyse d'écart** : l'écart initial (interruption sur le déclencheur) est **corrigé** — le séparateur d'instruction est temporairement modifié autour du déclencheur et de la procédure, puis rétabli. **Enseignement** : un artefact **valide à la lecture n'est pas nécessairement valide à l'exécution — seule l'exécution le prouve**. L'analyse statique jugeait le script cohérent ; le défaut n'est apparu qu'une fois le script **réellement transmis** à la base.

---

## 7. Tests de sécurité

Démarche rattachée aux exigences **OWASP / ANSSI** du référentiel. Ce qui est vérifié, par test réel :

| Vecteur | Défense | Preuve (test) | Résultat |
|---|---|---|---|
| **Accès par rôle** — un gestionnaire/emprunteur atteint une zone super-admin, un emprunteur atteint la gestion | Pare-feu `access_control` + `#[IsGranted]` en défense | `CompteControllerTest`, `JournalControllerTest`, `GestionControllerTest`, `CatalogueControllerTest` | **403** (ou 302 anonyme) |
| **Jetons CSRF invalides** — POST de mutation avec `_token` falsifié | `isCsrfTokenValid` avant tout effet | `GestionPretControllerTest`, `CategorieControllerTest`, `ExemplaireControllerTest`, `MaterielControllerTest`, `CompteControllerTest` | Redirection, **aucune mutation** |
| **Validation des entrées** — dates malformées, mots de passe non conformes | Validation serveur systématique | `CatalogueControllerTest` (« Dates invalides », « postérieure »), `InscriptionControllerTest`, `MotDePasseFortValidatorTest` | Rejet **422** / message, pas d'écriture |
| **Politique de mot de passe** — 12+ caractères, classes requises | Contrainte `MotDePasseFort` | `MotDePasseFortValidatorTest` (6 méthodes) | Messages exacts levés |
| **Journalisation des actions sensibles** — décisions de prêt, actions de compte | `JournalAdminService` (append-only) | `GestionPretJournalTest`, `CompteControllerTest` (traces `COMPTE_*`), `JournalAdminServiceTest` | Trace présente (acteur/cible) |
| **En-têtes de sécurité** — CSP à nonce, `nosniff`, `X-Frame-Options`, `Referrer-Policy` ; absence de CSP sur le JSON | `SecuriteEnTetesListener` | `EnTetesSecuriteTest` | En-têtes présents ; nonce cohérent ; JSON exempt |

**Précision — refus d'auto-désactivation vérifié en forgeant une requête** : l'interface masque le
bouton de désactivation sur sa propre ligne, **mais l'absence de bouton n'est pas une protection**. Le
test `CompteControllerTest::test_anti_soi_desactivation_refusee` **forge une requête POST valide** (jeton
CSRF injecté dans la session de test) vers la route d'activation de son propre compte et vérifie le
**403** du Voter : la garde est prouvée au niveau du contrôleur, pas seulement au niveau de la vue.

---

## 8. Exécution et résultats

### Lancer la suite (développement)
```bash
# Suite complète
docker compose exec -T -e APP_ENV=test app vendor/bin/phpunit
# Un fichier ciblé
docker compose exec -T -e APP_ENV=test app vendor/bin/phpunit tests/Service/PretServiceTest.php
# Analyse statique + style
docker compose exec -T app vendor/bin/phpstan analyse --no-progress
docker compose exec -T app vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php --path-mode=intersection
```

### Ce que produit l'intégration continue
Trois jobs (`cs-fixer`, `phpstan`, `phpunit`) sur push et *pull request* des branches `develop`,
`preprod`, `main`. La couverture est mesurée en CI (extension dédiée) puis remontée à **SonarQube
Cloud**, qui applique la **Quality Gate sur les nouvelles lignes**. Un job rouge (test, typage, style,
ou *notice* PHPUnit) **bloque** la promotion.

### Interprétation
Un échec `phpunit` signale une régression fonctionnelle ; un échec `phpstan` (niveau 8) un défaut de
typage ; un échec `cs-fixer` un écart de style ; une *notice* PHPUnit (ex. *mock* sans attente) est
traitée comme une erreur. La suite doit être **franche** : « OK, but there were issues » est considéré
comme un échec.

### Cas vécu — seuil de couverture non atteint après une modification de forme
Lors d'un chantier **de forme** (ré-accentuation du texte visible), des branches de contrôleur touchées
(rejets CSRF, dates malformées) sont repassées **sous le seuil de couverture du code nouveau**. La
résolution a consisté à **ajouter les tests manquants** (six tests ciblant ces branches précises) —
**et non à abaisser le seuil**. Le seuil qualité reste le contrat ; c'est la couverture qui remonte.

### Résultat de référence (dernière exécution verte)
| Contrôle | Outil | Résultat |
|---|---|---|
| Tests automatisés | PHPUnit | **OK — 207 cas exécutés / 207 planifiés (100 %), 690 assertions** |
| Alertes | PHPUnit (`failOn…="true"`) | **0 déprécation / 0 notice / 0 avertissement** |
| Analyse statique | PHPStan niveau 8, sans *baseline* | **No errors** |
| Style de code | PHP-CS-Fixer (PER + Symfony) | **0 fichier à corriger** |
| Gabarits | `bin/console lint:twig` | **Tous valides** |

> Relevé du 20/07/2026, vérifié sur **deux environnements pour le même état du dépôt** : poste de
> développement (WSL/Docker) et intégration continue (GitHub Actions, run 29819873048 sur `develop`,
> 21/07/2026). Les deux produisent **207 cas** et **690 assertions — chiffre identique sur les deux
> environnements**, reproductible sur **cinq exécutions locales consécutives**. Le nombre d'assertions
> reste une **donnée de contexte** ; la **référence de conformité demeure le rapport cas exécutés /
> cas planifiés**.
>
> Vérification par un tiers : `gh run view 29819873048 --log | grep 'OK ('`

> **Reproductibilité vérifiée.** Le chiffre de **690 assertions** est **stable** : cinq exécutions
> consécutives de la suite complète ont donné **exactement le même total**, et le contrôle
> `SELECT COUNT(*) FROM journal_admin` est revenu à **0** après chacune. Ce résultat n'allait pas de soi :
> une **fuite d'isolation** faisait auparavant dériver le compteur de **+3 assertions par exécution**
> (des entrées résiduelles s'accumulaient dans `journal_admin`, et un test assertait *par entrée*). La
> cause a été traitée — remise à zéro déterministe de la table par les tests qui l'alimentent, cf. §3 et
> **DT-15** — et c'est ce qui rend le nombre d'assertions **opposable** aujourd'hui. Il reste néanmoins
> une **donnée de contexte** : la **référence de conformité demeure le rapport cas exécutés / cas
> planifiés**, seul indicateur dont la stabilité ne dépend pas de la forme des assertions.

---

## 9. Limites et évolutions

Déclarées explicitement (honnêteté attendue d'un dossier de tests) :

1. **Rendu visuel non vérifié par les tests fonctionnels** : ceux-ci assertent des **textes** et des
   **codes HTTP**, pas une apparence. Un formulaire mal mis en forme (champ non stylé, alignement cassé)
   **passe les tests**. Ce cas s'est **présenté à trois reprises** durant le développement (champ de
   formulaire rendu brut faute de thème Bootstrap), détecté **visuellement**, pas par la suite. La
   parade reste la **validation visuelle manuelle** après chaque changement de front.
2. **Comportement JavaScript non couvert** : les contrôleurs Stimulus (confirmation de suppression,
   auto-soumission du filtre, affichage du mot de passe, calendrier FullCalendar, graphique Chart.js) ne
   sont pas testés — aucun exécuteur de test front n'est en place. Le **dégradé sans JavaScript** est en
   revanche conçu (soumission directe des formulaires, bouton de repli du filtre).
3. **Concurrence à forte charge** (RG-1) : la course parallèle réelle **est** testée —
   `ConcurrenceValidationTest` oppose **deux processus système** se disputant le verrou (§6.1), et
   `VerrouPessimisteTest` couvre le mécanisme isolément. La limite résiduelle est le **degré** de
   concurrence : deux processus, **pas** une charge concurrente élevée (N validateurs simultanés) ni une
   montée en charge soutenue. L'invariant est prouvé sous course réelle ; son comportement sous forte
   contention n'est pas mesuré — hors périmètre d'un démonstrateur (cf. point 4).
4. **Absence de tests de charge / performance** : hors périmètre ; l'application est un démonstrateur.
5. **Adaptation aux tailles d'écran (responsive)** non vérifiée automatiquement : reposant sur les
   utilitaires Bootstrap, contrôlée visuellement.
6. **Conformité RGAA — audit formel non mené (limite assumée)** : il ne s'agit pas d'un défaut de
   couverture d'un besoin fonctionnel. Les dispositions d'accessibilité **sont** implémentées et
   vérifiées à leur niveau (repères de structure, libellés de formulaire, équivalents textuels dont le
   tableau alternatif du calendrier). Ce qui n'est pas fait est l'**audit RGAA formel** — une démarche
   de conformité réglementaire, outillée et menée par un évaluateur, qui **excède le périmètre d'un
   démonstrateur pédagogique**. La limite porte donc sur le **niveau de preuve de conformité**, pas sur
   l'existence des dispositions.
7. **Redirection après connexion — choix de conception (limite assumée)** : la redirection vers
   `app_home` est **volontairement statique**. Aucun besoin fonctionnel n'exige de redirection
   différenciée par rôle : la hiérarchie des rôles étant cumulative, l'accueil expose à chacun les
   accès dont il dispose, et chaque zone reste protégée par son propre contrôle d'autorisation. Il n'y
   a donc **rien à couvrir** ici : l'absence de test dédié découle de l'absence de comportement
   différencié, non d'un oubli.

**Évolutions** : introduction d'un exécuteur de test front pour couvrir les contrôleurs Stimulus ;
extraction éventuelle facilitant un test hors HTTP de la concurrence ; audit RGAA formel.

---

## 10. Veille sur les tests logiciels

La veille est **outillée**, non subie : un bot dédié agrège **15 flux RSS/Atom** (dont Symfony, PHP,
PHP.Watch, OWASP, CERT-FR, NVD, Snyk) et publie un **digest quotidien à 7 h** sur un canal Discord,
complété d'un **récapitulatif hebdomadaire**. Trois sujets ont directement façonné ce plan de tests :
la **politique de dépréciation de PHPUnit 13**, qui a conduit à traiter toute alerte comme un échec
(`failOnDeprecation`/`failOnNotice`/`failOnWarning` à `true`) ; la **sécurité des dépendances**, avec
`composer audit` exécuté et **sans vulnérabilité connue** au dernier relevé, mais **non automatisé en
CI** — limite explicitement consignée ; et le maintien de **PHPStan niveau 8 sans *baseline*** y
compris sur `tests/`, qui impose de typer les tests aussi rigoureusement que le code de production.

Détail des sources, des décisions prises et du circuit « information → action » :
[`veille-tests-logiciels.md`](veille-tests-logiciels.md).

---

## 11. Tests d'acceptation

**Démarche.** La suite automatisée prouve que le **code** se comporte comme spécifié ; elle ne prouve
pas que l'**application déployée** répond au besoin. Les tests d'acceptation comblent cet écart : ils
sont joués **manuellement**, **sur la préproduction** (environnement iso-production), sur le jeu de
données de `scripts/seed-preprod.sql`, et **après déploiement** — donc sur l'artefact réellement
installé, proxy et configuration compris.

**Support.** La grille [`recette-acceptation.md`](recette-acceptation.md) comporte **12 scénarios**
(REC-01 à REC-12) couvrant le cycle complet : inscription et connexion, catalogue et disponibilité,
demande, annulation, validation, refus motivé, retour avec dommage, tableau de bord, journal RGPD et
cloisonnement des rôles. Chaque scénario indique les **BF couverts**, le profil, les préconditions et
des étapes numérotées avec leur résultat attendu ; deux colonnes — « Résultat obtenu » et
« Conforme O/N » — sont **remplies à la main** lors de l'exécution. Un en-tête consigne la **date**,
l'**URL**, la **version déployée (SHA)** et l'**exécutant**, de sorte qu'une campagne soit rattachable
à un artefact précis.

**État actuel, sans ambiguïté** : la grille est **vierge**. Ces scénarios **n'ont pas encore été
joués** ; aucun résultat n'y est consigné.

**Limite assumée.** Le projet **n'a pas de client externe** : la recette est jouée par le développeur
**en tant que représentant utilisateur**. C'est la même personne qui a spécifié, conçu, développé et
qui valide — configuration qu'un projet réel évite précisément parce qu'elle expose au biais de
confirmation (on valide volontiers ce que l'on a conçu). La parade retenue est de rendre les scénarios
**écrits à l'avance et opposables** : les résultats attendus sont figés **avant** l'exécution, ce qui
interdit d'ajuster l'attendu à l'obtenu. Elle atténue le biais sans le supprimer — cette limite est
consignée ici plutôt que passée sous silence (cf. §3bis.1, même limite sur les rôles).

---

*Plan de tests CréaPrêt — chiffres relevés dans `tests/` (55 fichiers, 205 méthodes, 207 cas en CI). La
dette technique est suivie dans `docs/DETTE_TECHNIQUE.md`.*
