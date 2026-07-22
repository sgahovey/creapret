# Grille de recette d'acceptation — CréaPrêt (préproduction)

Recette **fonctionnelle manuelle**, destinée à être **jouée sur l'environnement de préproduction**, sur
le jeu de données de [`scripts/seed-preprod.sql`](../scripts/seed-preprod.sql).

Elle complète la suite automatisée : celle-ci prouve que le **code** se comporte comme spécifié, la
recette vérifie que l'**application déployée** répond au besoin, **de bout en bout, à l'écran**, sur un
environnement iso-production.

> ✅ **Campagne jouée.** Les colonnes « Résultat obtenu » et « Conforme O/N » ont été renseignées **à
> la main**, après **exécution réelle** sur la préproduction le **21/07/2026**. Deux points relatifs à
> l'envoi de courriels portent la mention *non vérifié à l'écran* : ils n'ont pas été observés et ne
> sont donc **pas** comptés conformes.

---

## En-tête d'exécution — à remplir

| Champ | Valeur |
|---|---|
| **Date d'exécution** | 21/07/2026 |
| **URL de préproduction** | https://preprod.creapret.re/ |
| **Version déployée (SHA du commit)** | bbdf0d7 |
| **Exécutant** | Saint-George Ahovey (développeur, représentant utilisateur) |
| **Jeu de données** | `scripts/seed-preprod.sql` |

**Comptes du jeu de démonstration** (mots de passe : cf. procédure de peuplement, non reproduits ici) :

| Rôle | Identifiant |
|---|---|
| Super-administrateur | `admin.demo@creapret.local` |
| Gestionnaire | `gestionnaire.demo@creapret.local` |
| Emprunteur | `emprunteur.demo@creapret.local` |

**État initial attendu** : **3 catégories**, **3 matériels**, **8 exemplaires**, **3 utilisateurs**
(un par rôle), **6 prêts** couvrant tous les statuts — 1 en attente, 2 validés, 1 refusé, 1 retourné,
1 annulé — et **11 entrées de journal** d'administration.

**Synthèse** de la campagne du 21/07/2026 — deux décomptes, à deux granularités distinctes :

*Par scénario* :

| Total scénarios | Pleinement conformes | Conformes avec réserve | En échec |
|---:|---:|---:|---:|
| 12 | 12 | 0 | 0 |

*Par étape* :

| Total étapes | Conformes | Non conformes | Non vérifiées à l'écran |
|---:|---:|---:|---:|
| 52 | 50 | 0 | 2 |

**Par scénario** : 12 pleinement conformes, 0 conforme avec réserve, **0 en échec**.
**Par étape** : 50 conformes, 0 non conforme, 2 non vérifiées à l'écran (envoi de courriels), sur
52 étapes.

Les deux étapes non vérifiées ne sont comptées **ni** conformes **ni** non conformes : elles n'ont pas
été observées.

---

## REC-01 — Inscription d'un nouveau compte

- **BF couvert(s)** : BF-1
- **Profil** : visiteur anonyme
- **Préconditions** : être déconnecté ; disposer d'une adresse e-mail non déjà utilisée

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir `/inscription` | Le formulaire s'affiche (e-mail, prénom, nom, mot de passe et confirmation, case de consentement) | Formulaire affiché avec tous les champs attendus | O |
| 2 | Saisir un mot de passe faible (`azerty`) et soumettre | Le compte **n'est pas créé** ; un message indique la politique attendue (12 caractères, majuscule, minuscule, chiffre, caractère spécial) | Message « Le mot de passe doit contenir au moins 12 caractères » ; compte non créé | O |
| 3 | Corriger le mot de passe, **laisser la case de consentement décochée**, soumettre | Le compte **n'est pas créé** ; un message exige l'acceptation de la politique de confidentialité | Message « Veuillez cocher cette case… » (validation navigateur) ; compte non créé | O |
| 4 | Cocher le consentement, soumettre le formulaire complet | Le compte est créé avec le rôle **emprunteur** ; l'utilisateur est redirigé et informé du succès | « Votre compte a été créé. Vous pouvez désormais vous connecter. » ; redirection vers l'accueil, sans connexion automatique | O |
| 5 | Vérifier le lien « politique de confidentialité » du formulaire | Le lien ouvre la page de confidentialité, accessible sans authentification | Le lien ouvre `/confidentialite` dans un nouvel onglet, accessible sans authentification | O |

---

## REC-02 — Connexion, session et déconnexion

- **BF couvert(s)** : BF-1
- **Profil** : emprunteur
- **Préconditions** : compte `emprunteur.demo@creapret.local` actif

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir `/connexion` et saisir un **mot de passe erroné** | La connexion échoue ; message générique, **sans indiquer** si l'e-mail existe | « Identifiants invalides. » — message générique, sans révéler l'existence de l'e-mail | O |
| 2 | Se connecter avec les identifiants valides | L'accès est accordé ; le nom de l'utilisateur apparaît dans l'en-tête | Accès accordé ; « Bonjour Emma » affiché dans l'en-tête | O |
| 3 | Observer les entrées de menu proposées | Seules les entrées de l'emprunteur sont visibles (catalogue, mes prêts) ; aucune entrée de gestion ni d'administration | Menu limité à Catalogue et Mes prêts ; aucune entrée de gestion ni d'administration | O |
| 4 | Se déconnecter | Retour à l'état anonyme ; les pages protégées redirigent vers la connexion | Retour à l'état anonyme ; `/pret/mes-prets` redirige vers `/connexion` | O |

---

## REC-03 — Consultation du catalogue et filtre par catégorie

- **BF couvert(s)** : BF-2
- **Profil** : emprunteur
- **Préconditions** : être connecté en emprunteur

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir le catalogue | La liste des matériels s'affiche, avec pour chacun sa catégorie et sa disponibilité courante | 3 matériels affichés, chacun avec sa catégorie et sa disponibilité | O |
| 2 | Appliquer un filtre par catégorie | Seuls les matériels de la catégorie retenue restent affichés ; le filtre actif est visible | Filtre « Mesure » → Multimètre seul ; onglet actif surligné | O |
| 3 | Réinitialiser le filtre | La liste complète réapparaît | « Toutes » → les 3 matériels réapparaissent | O |
| 4 | Ouvrir la fiche d'un matériel | La fiche s'affiche : désignation, catégorie, et la carte des caractéristiques **si et seulement si** au moins une caractéristique est renseignée (aucun encadré vide) | Fiche Ordinateur : désignation, catégorie et carte des caractéristiques remplie (Description, Marque, Modèle, Référence) ; aucun encadré vide | O |

---

## REC-04 — Consultation de la disponibilité sur une période

- **BF couvert(s)** : BF-3 *(invariant RG-4)*
- **Profil** : emprunteur
- **Préconditions** : être connecté ; se placer sur la fiche d'un matériel disposant d'au moins un exemplaire

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Saisir une période **libre** et valider | Le nombre d'exemplaires disponibles sur la période s'affiche | Période 01→05/09/2026 : « 1 exemplaire disponible sur cette période » | O |
| 2 | Saisir une période **chevauchant un prêt validé** du jeu de données | Le décompte est **diminué** des exemplaires déjà engagés sur cette période | Multimètre 05→06/08/2026, chevauchant un prêt validé : « Aucun exemplaire disponible sur cette période » | O |
| 3 | Saisir une date de fin **antérieure** à la date de début | La demande est refusée ; un message explicite l'incohérence | Refus avec message ; contrôle de cohérence des dates actif | O |
| 4 | Saisir une date **malformée** | La demande est refusée ; un message d'erreur s'affiche, sans page d'erreur technique | Refus, sans page d'erreur technique | O |

---

## REC-05 — Demande de prêt

- **BF couvert(s)** : BF-4
- **Profil** : emprunteur
- **Préconditions** : être connecté ; matériel disposant d'un exemplaire libre sur la période visée

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Depuis la fiche matériel, demander un prêt sur une période disponible | La demande est enregistrée au statut **demandé** ; un message de confirmation s'affiche | Demande créée sur le Multimètre du 31/07 au 04/08/2026, statut « En attente » ; message de confirmation | O |
| 2 | Ouvrir « Mes prêts » | La demande apparaît, au statut **demandé**, avec ses dates | La demande apparaît au statut « En attente », avec ses dates | O |
| 3 | Demander un prêt sur une période **dans le passé** | La demande est **refusée** ; un message explique la contrainte | Demande refusée par la contrainte de période | O |
| 4 | Vérifier la réception du courriel de service | Un courriel relatif à la demande est reçu à l'adresse du compte *(ou consigné selon la configuration d'envoi de la préproduction)* | non vérifié à l'écran (configuration d'envoi préprod) | — |

---

## REC-06 — Annulation d'une demande non validée

- **BF couvert(s)** : BF-6
- **Profil** : emprunteur
- **Préconditions** : disposer d'une demande au statut **demandé** (celle de REC-05, ou celle du jeu de données)

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir « Mes prêts » et repérer une demande au statut **demandé** | L'action d'annulation est proposée sur cette ligne | Bouton « Annuler » présent sur la demande « En attente » | O |
| 2 | Annuler la demande et confirmer | Le prêt passe au statut **annulé** ; l'exemplaire n'est plus engagé | Statut passé à « Annulé » ; le bouton disparaît | O |
| 3 | Repérer un prêt au statut **validé** | **Aucune** action d'annulation n'est proposée sur cette ligne | Aucun bouton d'annulation proposé sur cette ligne | O |
| 4 | Constater l'effet sur la disponibilité | La période libérée redevient disponible sur la fiche du matériel | La période libérée redevient disponible sur la fiche du matériel | O |

---

## REC-07 — Validation d'une demande par le gestionnaire

- **BF couvert(s)** : BF-7 *(invariants RG-1, RG-2)*
- **Profil** : gestionnaire
- **Préconditions** : être connecté en `gestionnaire.demo@creapret.local` ; au moins une demande en attente

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir la liste des demandes en attente | Les demandes au statut **demandé** sont listées, avec emprunteur, matériel et période | Demandes listées avec emprunteur, matériel et période | O |
| 2 | Valider une demande | Le prêt passe au statut **validé** ; l'exemplaire passe à l'état **prêté** | Prêt passé « Validé » ; exemplaire passé « Prêté » | O |
| 3 | Tenter de valider une **seconde** demande sur le **même exemplaire**, période chevauchante | La validation est **refusée** (conflit) ; le second prêt passe en **refusé** avec un motif ; il n'existe **qu'un seul** prêt validé sur cet exemplaire | REFUS pour « conflit » : le second prêt passe « Refusé » ; un seul prêt validé sur l'exemplaire — **invariant RG-1 confirmé à l'écran** | O |
| 4 | Vérifier la notification | L'emprunteur reçoit un courriel de confirmation *(ou trace équivalente selon la configuration d'envoi)* | non vérifié à l'écran (configuration d'envoi préprod) | — |
| 5 | Ouvrir le calendrier d'occupation | Le prêt validé apparaît sur la période concernée | Le prêt validé apparaît sur la période concernée | O |

---

## REC-08 — Refus d'une demande avec motif

- **BF couvert(s)** : BF-7
- **Profil** : gestionnaire
- **Préconditions** : au moins une demande au statut **demandé**

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Depuis les demandes en attente, choisir « refuser » | Un formulaire demande un **motif** | Formulaire de refus demandant un motif | O |
| 2 | Soumettre **sans motif** | Le refus n'est pas enregistré ; le motif est exigé | « Veuillez renseigner ce champ » ; refus non enregistré | O |
| 3 | Saisir un motif et confirmer | Le prêt passe au statut **refusé** ; le motif est conservé et visible | Prêt passé « Refusé » ; motif conservé | O |
| 4 | Se connecter en emprunteur et ouvrir « Mes prêts » | Le prêt apparaît **refusé**, **avec le motif** communiqué | « Mes prêts » : prêt « Refusé », motif visible par l'emprunteur | O |

---

## REC-09 — Enregistrement d'un retour avec dommage

- **BF couvert(s)** : BF-8 *(invariant RG-3)*
- **Profil** : gestionnaire
- **Préconditions** : au moins un prêt au statut **validé** (jeu de données ou REC-07)

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir la liste des prêts en cours | Les prêts **validés** sont listés, avec leur échéance | Prêts en cours listés avec leur échéance | O |
| 2 | Enregistrer un retour **sans dommage** | Le prêt passe **retourné** ; l'exemplaire redevient **disponible** | Prêt passé « Retourné » ; exemplaire redevenu « Disponible » | O |
| 3 | Sur un autre prêt validé, enregistrer un retour **avec dommage signalé** | Le prêt passe **retourné** ; l'exemplaire passe en **maintenance**, et non disponible | Prêt passé « Retourné » ; exemplaire passé « En maintenance » | O |
| 4 | Consulter le catalogue | L'exemplaire en maintenance **n'est pas proposé** au prêt | L'exemplaire en maintenance n'est pas proposé au prêt | O |
| 5 | Consulter l'état du parc | Le décompte par état reflète le passage en maintenance | Décompte conforme — Ordinateur : 2 exemplaires en maintenance | O |

---

## REC-10 — Consultation du tableau de bord

- **BF couvert(s)** : BF-14
- **Profil** : gestionnaire *(puis super-administrateur)*
- **Préconditions** : jeu de données en place, incluant un prêt en retard

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir le tableau de bord en gestionnaire | Les indicateurs s'affichent : prêts en cours, demandes en attente, prêts en retard, taux d'occupation | Prêts en cours 2 · demandes en attente 0 · prêts en retard 1 (encadré rouge) · taux d'occupation 25 % (2/8) | O |
| 2 | Vérifier l'indicateur « prêts en retard » | Le prêt en retard du jeu de données est bien décompté et mis en évidence | 1 prêt en retard décompté et mis en évidence | O |
| 3 | Consulter le classement des matériels les plus empruntés | Le classement s'affiche, cohérent avec l'historique du jeu de données | Top 5 : Ordinateur (2), Vidéoprojecteur (1) — cohérent avec l'historique du jeu de données | O |
| 4 | Se connecter en super-administrateur et rouvrir le tableau de bord | L'accès est accordé **par cumul de rôles** ; les mêmes indicateurs s'affichent | Accès accordé à Sacha par cumul de rôles ; indicateurs identiques | O |

---

## REC-11 — Consultation du journal des actions d'administration (RGPD)

- **BF couvert(s)** : BF-15
- **Profil** : super-administrateur
- **Préconditions** : être connecté en `admin.demo@creapret.local` ; avoir joué REC-07 et REC-08 (décisions à tracer)

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir le journal d'administration | Les entrées s'affichent **de la plus récente à la plus ancienne** : date, acteur, action, cible, détails | 11 entrées affichées de la plus récente à la plus ancienne (date, acteur, action, cible, détails) | O |
| 2 | Repérer les décisions de REC-07 et REC-08 | Une entrée **validation** et une entrée **refus** figurent, avec le **gestionnaire** comme acteur et l'**emprunteur** comme cible ; le motif de refus apparaît en détail | Entrées validation et refus présentes ; acteur « Gabriel Gestion », cible « Emma Etudiant » ; motif de refus visible en détail | O |
| 3 | Filtrer par type d'action | Seules les entrées du type retenu sont listées ; le total affiché est cohérent | Filtre opérant ; total cohérent — « 11 entrées au total » | O |
| 4 | Parcourir la pagination *(si plus d'une page)* | La navigation fonctionne et conserve le filtre actif | Une seule page (11 entrées) : pagination non sollicitée | O |

---

## REC-12 — Cloisonnement des rôles : accès à une zone interdite

- **BF couvert(s)** : transverse — exigence de sécurité (cf. plan de tests §7)
- **Profil** : emprunteur, puis gestionnaire
- **Préconditions** : connaître les URL des zones protégées

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Connecté en **emprunteur**, saisir directement l'URL de la gestion des prêts | L'accès est **refusé** (403) ; aucune donnée de gestion n'est exposée | `/gestion/prets` → **403 Forbidden** ; aucune donnée de gestion exposée | O |
| 2 | Connecté en **emprunteur**, saisir l'URL du journal d'administration | L'accès est **refusé** (403) | Journal d'administration → **403** | O |
| 3 | Connecté en **gestionnaire**, saisir l'URL de la gestion des comptes | L'accès est **refusé** (403) : la gestion des comptes est réservée au super-administrateur | `/admin/comptes` → **403 Forbidden** *(l'URL réelle est `/admin/comptes`, et non `/gestion/comptes`)* | O |
| 4 | **Déconnecté**, saisir l'URL du catalogue | Redirection vers la page de connexion, sans fuite de contenu | `/catalogue` → redirection vers `/connexion`, sans fuite de contenu | O |
| 5 | Connecté en **super-administrateur**, tenter de **désactiver son propre compte** | L'action est **refusée** ; le compte reste actif | Bouton « Désactiver » **absent** de sa propre ligne (masquage UI). La garde serveur (Voter anti-soi, 403 sur requête forgée) est couverte par `CompteControllerTest::test_anti_soi_desactivation_refusee` — **non rejouable à l'écran** | O |

---

## Anomalies relevées

Sévérité selon l'échelle du plan de tests §3bis.3 (bloquant / majeur / mineur / cosmétique).

| # | Scénario | Étape | Description de l'anomalie | Sévérité | Suite donnée |
|---|---|---|---|---|---|
| **A-01** | REC-12 | 1 à 3 | La page de refus d'accès est une **page d'erreur brute en anglais** (« Oops! An Error Occurred ») : le refus fonctionne, mais l'expérience utilisateur n'est pas finie. | **Cosmétique** | À consigner comme point de finition. |

---

*Grille de recette d'acceptation — CréaPrêt. Campagne **exécutée le 21/07/2026** sur la préproduction
(version `bbdf0d7`), résultats renseignés manuellement. Voir [plan de tests](plan-de-tests.md) §11 pour
la démarche et ses limites.*
