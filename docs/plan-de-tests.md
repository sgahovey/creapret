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
- les **tests de charge / performance** et la **concurrence parallèle réelle** : non reproductibles de
  façon déterministe dans un exécuteur PHPUnit mono-processus ; l'invariant menacé est testé à sa place
  (cf. §6).

---

## 2. Stratégie

La suite est organisée en **niveaux pratiques**, chacun avec un rôle précis :

- **Unitaire** (`PHPUnit\Framework\TestCase`, sans base) — logique pure, sans effet de bord : **entités**
  (cycle de vie, calculs comme `Pret::estEnRetard`), **énumérations** (valeurs *backed* persistées,
  libellés, couleurs de badge), **validateur** de mot de passe fort, **voteur** d'autorisation.
- **Intégration** (`KernelTestCase`, transaction annulée en fin de test) — **services métier** et
  **accès aux données** : `PretService` (validation, refus, retour), disponibilité et requêtes DQL des
  **repositories**, **déclencheur d'audit** SQL, **commandes** planifiées.
- **Fonctionnel** (`WebTestCase`) — **parcours HTTP** de bout en bout : catalogue, demande/validation/
  refus/retour de prêt, espace emprunteur, inventaire, tableau de bord, journal, administration des
  comptes.
- **Sécurité** — transversal : refus d'accès par rôle (403), rejet des jetons CSRF invalides, validation
  des entrées, politique de mot de passe, journalisation des actions sensibles, en-têtes de sécurité.

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
    ou de nom), **purgées** en `tearDown` (`DELETE ... LIKE 'marqueur%'`) ; le journal d'audit
    *append-only* est nettoyé par le libellé d'acteur/cible.
- **Jeux de données** : construits à la main dans chaque test (graphe minimal catégorie → matériel →
  exemplaire → prêt, comptes par rôle), jamais dépendants de fixtures partagées.
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

## 4. Cartographie (chiffres réels relevés dans `tests/`)

### 4.1 Par niveau

| Niveau | Classe de base | Fichiers | Méthodes |
|---|---|---:|---:|
| Unitaire | `TestCase` / `ConstraintValidatorTestCase` | 16 | 53 |
| Intégration | `KernelTestCase` (transaction/rollback) | 17 | 58 |
| Fonctionnel | `WebTestCase` | 21 | 93 |
| **Total** | | **54** | **204** |

> **204 méthodes** de test ; la CI dénombre **206 cas**, l'écart venant de `LegalPagesTest` (une méthode
> paramétrée par un fournisseur de données couvrant les 3 pages légales).

### 4.2 Par domaine fonctionnel

| Domaine | Fichiers (méthodes) | Σ |
|---|---|---:|
| Sécurité — autorisation | UtilisateurVoter (3), UserChecker (2) | 5 |
| Entités & énumérations | Utilisateur (10), Pret (4), Categorie (3), Exemplaire (3), Materiel (2), EtatExemplaire (3), StatutPret (3), Role (2), TypeActionJournal (2) | 32 |
| Validateur mot de passe | MotDePasseFortValidator (6) | 6 |
| Services métier | AnnulationPret (7), PretService (4), NotificationService (3), NotificationRappelRetard (3), RetourPret (3), PretCalendarSerializer (3), DateFormatter (2), JournalAdminService (2), Statistique (2), ConcurrenceValidation (1), VerrouPessimiste (1) | 31 |
| Repositories, requêtes & trigger | PretRepository (9), ExemplaireDisponibilite (5), ExemplaireLibre (4), HistoriqueUtilisateurTrigger (3), UtilisateurRepository (3), PretRepositoryRappels (2), JournalAdminRepository (2), MaterielRepository (1) | 29 |
| Commandes planifiées | EnvoiRappels (5), PurgeAudit (3) | 8 |
| Fonctionnel — catalogue & emprunteur | Catalogue (9), Pret (5), MesPrets (3), CalendrierPage (2) | 19 |
| Fonctionnel — gestion & inventaire | GestionPret (6), Materiel (7), Categorie (7), Exemplaire (7), Gestion (4), NotificationsPret (4), CalendrierApi (3), TableauDeBord (3), RetourGestion (3), Parc (2), GestionPretJournal (2) | 48 |
| Fonctionnel — administration & sécurité | Compte (9), Inscription (6), Security (5), Journal (3), EnTetesSecurite (2), LegalPages (1) | 26 |
| **Total** | | **204** |

> Réconciliation : 5 + 32 + 6 + 31 + 29 + 8 + 19 + 48 + 26 = **204**, identique au total par niveau.

---

## 5. Matrice de traçabilité

Niveau(x) : **U** = unitaire, **I** = intégration, **F** = fonctionnel.

| Règle de gestion / besoin | Fichiers de tests | Niveau(x) | Statut |
|---|---|:--:|:--:|
| **RG-1** — non-chevauchement des prêts actifs (verrou pessimiste) | PretServiceTest, VerrouPessimisteTest, ConcurrenceValidationTest, PretRepositoryTest, GestionPretControllerTest | I, F | ✅ |
| **RG-2** — exemplaire indisponible non prêtable | PretServiceTest, ExemplaireRepositoryDisponibiliteTest, ExemplaireRepositoryLibreTest | I | ✅ |
| **RG-3** — retour : état exemplaire + statut prêt | RetourPretTest, RetourGestionTest | I, F | ✅ |
| **RG-4** — disponibilité sur période | ExemplaireRepositoryDisponibiliteTest, ExemplaireRepositoryLibreTest, CatalogueControllerTest | I, F | ✅ |
| Catalogue & fiche matériel | CatalogueControllerTest | F | ✅ |
| Demande / validation / refus de prêt | GestionPretControllerTest, PretServiceTest, GestionPretJournalTest | I, F | ✅ |
| Espace emprunteur & annulation | MesPretsControllerTest, PretControllerTest, AnnulationPretTest | I, F | ✅ |
| Prêt en retard (KPI + badge) | PretTest (`estEnRetard`/`joursDeRetard`), RetourGestionTest | U, F | ✅ |
| Calendrier d'occupation | CalendrierApiControllerTest, CalendrierPageTest, PretCalendarSerializerTest | U, F | ✅ |
| Tableau de bord / statistiques | TableauDeBordControllerTest, StatistiqueServiceTest | U, F | ✅ |
| Notifications & rappels / alertes retard | NotificationServiceTest, NotificationsPretTest, NotificationRappelRetardTest, EnvoiRappelsCommandTest, PretRepositoryRappelsTest, DateFormatterServiceTest | U, I, F | ✅ |
| Journal RGPD (consultation) | JournalControllerTest, JournalAdminServiceTest, JournalAdminRepositoryTest | U, I, F | ✅ |
| Purge du journal (rétention RGPD) | PurgeAuditCommandTest | I | ✅ |
| Audit des comptes (trigger SQL) | HistoriqueUtilisateurTriggerTest | I | ✅ |
| Inventaire — CRUD catégorie/matériel/exemplaire/parc | CategorieControllerTest, MaterielControllerTest, ExemplaireControllerTest, ParcControllerTest, MaterielRepositoryTest, Entity/* | U, I, F | ✅ |
| Gestion des comptes (super-admin, anti-soi, anti-verrouillage) | CompteControllerTest, UtilisateurVoterTest, UtilisateurRepositoryTest | U, I, F | ✅ |
| Inscription / connexion / mot de passe fort | InscriptionControllerTest, SecurityControllerTest, MotDePasseFortValidatorTest, UserCheckerTest | U, F | ✅ |
| En-têtes de sécurité (CSP à nonce…) | EnTetesSecuriteTest | F | ✅ |
| Pages légales / RGAA (base) | LegalPagesTest | F | ⚠️ Partiel — présence/accessibilité de base vérifiées, **conformité RGAA complète non testée** (§9) |
| Redirection par rôle après connexion | *(aucun test dédié)* | — | ⚠️ Trou — la redirection est aujourd'hui statique (`app_home`), non couverte (§9) |

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

**Analyse d'écart** : aucun. La **course parallèle réelle** (deux validations simultanées) n'est pas
reproductible de façon fiable en PHPUnit mono-processus ; on teste donc l'**invariant qu'elle menace**
de manière **déterministe** (`ConcurrenceValidationTest`, `PretServiceTest`), et le **mécanisme de
verrou** lui-même est couvert par `VerrouPessimisteTest` (le verrou est bien posé sur l'exemplaire avant
la re-vérification). Limite consignée au §9.

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
| Tests automatisés | PHPUnit | **OK — 206 cas, 670 assertions** |
| Alertes | PHPUnit (`failOn…="true"`) | **0 déprécation / 0 notice / 0 avertissement** |
| Analyse statique | PHPStan niveau 8, sans *baseline* | **No errors** |
| Style de code | PHP-CS-Fixer (PER + Symfony) | **0 fichier à corriger** |
| Gabarits | `bin/console lint:twig` | **Tous valides** |

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
3. **Concurrence parallèle réelle** (RG-1) : non reproductible de façon déterministe en PHPUnit
   mono-processus. Mitigation : invariant testé déterministement (§6.1), verrou couvert par
   `VerrouPessimisteTest`.
4. **Absence de tests de charge / performance** : hors périmètre ; l'application est un démonstrateur.
5. **Adaptation aux tailles d'écran (responsive)** non vérifiée automatiquement : reposant sur les
   utilitaires Bootstrap, contrôlée visuellement.
6. **Conformité RGAA complète** non testée : présence des repères (landmarks), des libellés et des
   équivalents textuels (tableau alternatif du calendrier) vérifiée ; l'audit RGAA formel reste à mener.
7. **Redirection par rôle après connexion** : la redirection est statique (`app_home`) ; aucune
   redirection ciblée par rôle n'est en place ni testée.

**Évolutions** : introduction d'un exécuteur de test front pour couvrir les contrôleurs Stimulus ;
extraction éventuelle facilitant un test hors HTTP de la concurrence ; audit RGAA formel.

---

*Plan de tests CréaPrêt — chiffres relevés dans `tests/` (54 fichiers, 204 méthodes, 206 cas en CI). La
dette technique est suivie dans `docs/DETTE_TECHNIQUE.md`.*
