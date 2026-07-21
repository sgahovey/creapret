# Grille de recette d'acceptation — CréaPrêt (préproduction)

Recette **fonctionnelle manuelle**, destinée à être **jouée sur l'environnement de préproduction**, sur
le jeu de données de [`scripts/seed-preprod.sql`](../scripts/seed-preprod.sql).

Elle complète la suite automatisée : celle-ci prouve que le **code** se comporte comme spécifié, la
recette vérifie que l'**application déployée** répond au besoin, **de bout en bout, à l'écran**, sur un
environnement iso-production.

> ⚠️ **Grille vierge.** Les colonnes « Résultat obtenu » et « Conforme O/N » sont **volontairement
> vides** : ces scénarios **n'ont pas encore été joués**. Elles sont à remplir **à la main**, après
> exécution réelle sur la préproduction. Toute case pré-remplie invaliderait la valeur de preuve du
> document.

---

## En-tête d'exécution — à remplir

| Champ | Valeur |
|---|---|
| **Date d'exécution** | |
| **URL de préproduction** | |
| **Version déployée (SHA du commit)** | |
| **Exécutant** | |
| **Jeu de données** | `scripts/seed-preprod.sql` |

**Comptes du jeu de démonstration** (mots de passe : cf. procédure de peuplement, non reproduits ici) :

| Rôle | Identifiant |
|---|---|
| Super-administrateur | `admin.demo@creapret.local` |
| Gestionnaire | `gestionnaire.demo@creapret.local` |
| Emprunteur | `emprunteur.demo@creapret.local` |

**État initial attendu** : 1 catégorie, 1 matériel et ses exemplaires, 3 utilisateurs (un par rôle),
**6 prêts** couvrant tous les statuts — 1 `demande`, 2 `valide`, 1 `refuse`, 1 `retourne`, 1 `annule`.

**Synthèse** — à compléter en fin de campagne :

| Total scénarios | Conformes | Non conformes | Non joués |
|---:|---:|---:|---:|
| 12 | | | |

---

## REC-01 — Inscription d'un nouveau compte

- **BF couvert(s)** : BF-1
- **Profil** : visiteur anonyme
- **Préconditions** : être déconnecté ; disposer d'une adresse e-mail non déjà utilisée

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir `/inscription` | Le formulaire s'affiche (e-mail, prénom, nom, mot de passe et confirmation, case de consentement) | | |
| 2 | Saisir un mot de passe faible (`azerty`) et soumettre | Le compte **n'est pas créé** ; un message indique la politique attendue (12 caractères, majuscule, minuscule, chiffre, caractère spécial) | | |
| 3 | Corriger le mot de passe, **laisser la case de consentement décochée**, soumettre | Le compte **n'est pas créé** ; un message exige l'acceptation de la politique de confidentialité | | |
| 4 | Cocher le consentement, soumettre le formulaire complet | Le compte est créé avec le rôle **emprunteur** ; l'utilisateur est redirigé et informé du succès | | |
| 5 | Vérifier le lien « politique de confidentialité » du formulaire | Le lien ouvre la page de confidentialité, accessible sans authentification | | |

---

## REC-02 — Connexion, session et déconnexion

- **BF couvert(s)** : BF-1
- **Profil** : emprunteur
- **Préconditions** : compte `emprunteur.demo@creapret.local` actif

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir `/connexion` et saisir un **mot de passe erroné** | La connexion échoue ; message générique, **sans indiquer** si l'e-mail existe | | |
| 2 | Se connecter avec les identifiants valides | L'accès est accordé ; le nom de l'utilisateur apparaît dans l'en-tête | | |
| 3 | Observer les entrées de menu proposées | Seules les entrées de l'emprunteur sont visibles (catalogue, mes prêts) ; aucune entrée de gestion ni d'administration | | |
| 4 | Se déconnecter | Retour à l'état anonyme ; les pages protégées redirigent vers la connexion | | |

---

## REC-03 — Consultation du catalogue et filtre par catégorie

- **BF couvert(s)** : BF-2
- **Profil** : emprunteur
- **Préconditions** : être connecté en emprunteur

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir le catalogue | La liste des matériels s'affiche, avec pour chacun sa catégorie et sa disponibilité courante | | |
| 2 | Appliquer un filtre par catégorie | Seuls les matériels de la catégorie retenue restent affichés ; le filtre actif est visible | | |
| 3 | Réinitialiser le filtre | La liste complète réapparaît | | |
| 4 | Ouvrir la fiche d'un matériel | La fiche s'affiche : désignation, catégorie, et la carte des caractéristiques **si et seulement si** au moins une caractéristique est renseignée (aucun encadré vide) | | |

---

## REC-04 — Consultation de la disponibilité sur une période

- **BF couvert(s)** : BF-3 *(invariant RG-4)*
- **Profil** : emprunteur
- **Préconditions** : être connecté ; se placer sur la fiche d'un matériel disposant d'au moins un exemplaire

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Saisir une période **libre** et valider | Le nombre d'exemplaires disponibles sur la période s'affiche | | |
| 2 | Saisir une période **chevauchant un prêt validé** du jeu de données | Le décompte est **diminué** des exemplaires déjà engagés sur cette période | | |
| 3 | Saisir une date de fin **antérieure** à la date de début | La demande est refusée ; un message explicite l'incohérence | | |
| 4 | Saisir une date **malformée** | La demande est refusée ; un message d'erreur s'affiche, sans page d'erreur technique | | |

---

## REC-05 — Demande de prêt

- **BF couvert(s)** : BF-4
- **Profil** : emprunteur
- **Préconditions** : être connecté ; matériel disposant d'un exemplaire libre sur la période visée

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Depuis la fiche matériel, demander un prêt sur une période disponible | La demande est enregistrée au statut **demandé** ; un message de confirmation s'affiche | | |
| 2 | Ouvrir « Mes prêts » | La demande apparaît, au statut **demandé**, avec ses dates | | |
| 3 | Demander un prêt sur une période **dans le passé** | La demande est **refusée** ; un message explique la contrainte | | |
| 4 | Vérifier la réception du courriel de service | Un courriel relatif à la demande est reçu à l'adresse du compte *(ou consigné selon la configuration d'envoi de la préproduction)* | | |

---

## REC-06 — Annulation d'une demande non validée

- **BF couvert(s)** : BF-6
- **Profil** : emprunteur
- **Préconditions** : disposer d'une demande au statut **demandé** (celle de REC-05, ou celle du jeu de données)

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir « Mes prêts » et repérer une demande au statut **demandé** | L'action d'annulation est proposée sur cette ligne | | |
| 2 | Annuler la demande et confirmer | Le prêt passe au statut **annulé** ; l'exemplaire n'est plus engagé | | |
| 3 | Repérer un prêt au statut **validé** | **Aucune** action d'annulation n'est proposée sur cette ligne | | |
| 4 | Constater l'effet sur la disponibilité | La période libérée redevient disponible sur la fiche du matériel | | |

---

## REC-07 — Validation d'une demande par le gestionnaire

- **BF couvert(s)** : BF-7 *(invariants RG-1, RG-2)*
- **Profil** : gestionnaire
- **Préconditions** : être connecté en `gestionnaire.demo@creapret.local` ; au moins une demande en attente

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir la liste des demandes en attente | Les demandes au statut **demandé** sont listées, avec emprunteur, matériel et période | | |
| 2 | Valider une demande | Le prêt passe au statut **validé** ; l'exemplaire passe à l'état **prêté** | | |
| 3 | Tenter de valider une **seconde** demande sur le **même exemplaire**, période chevauchante | La validation est **refusée** (conflit) ; le second prêt passe en **refusé** avec un motif ; il n'existe **qu'un seul** prêt validé sur cet exemplaire | | |
| 4 | Vérifier la notification | L'emprunteur reçoit un courriel de confirmation *(ou trace équivalente selon la configuration d'envoi)* | | |
| 5 | Ouvrir le calendrier d'occupation | Le prêt validé apparaît sur la période concernée | | |

---

## REC-08 — Refus d'une demande avec motif

- **BF couvert(s)** : BF-7
- **Profil** : gestionnaire
- **Préconditions** : au moins une demande au statut **demandé**

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Depuis les demandes en attente, choisir « refuser » | Un formulaire demande un **motif** | | |
| 2 | Soumettre **sans motif** | Le refus n'est pas enregistré ; le motif est exigé | | |
| 3 | Saisir un motif et confirmer | Le prêt passe au statut **refusé** ; le motif est conservé et visible | | |
| 4 | Se connecter en emprunteur et ouvrir « Mes prêts » | Le prêt apparaît **refusé**, **avec le motif** communiqué | | |

---

## REC-09 — Enregistrement d'un retour avec dommage

- **BF couvert(s)** : BF-8 *(invariant RG-3)*
- **Profil** : gestionnaire
- **Préconditions** : au moins un prêt au statut **validé** (jeu de données ou REC-07)

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir la liste des prêts en cours | Les prêts **validés** sont listés, avec leur échéance | | |
| 2 | Enregistrer un retour **sans dommage** | Le prêt passe **retourné** ; l'exemplaire redevient **disponible** | | |
| 3 | Sur un autre prêt validé, enregistrer un retour **avec dommage signalé** | Le prêt passe **retourné** ; l'exemplaire passe en **maintenance**, et non disponible | | |
| 4 | Consulter le catalogue | L'exemplaire en maintenance **n'est pas proposé** au prêt | | |
| 5 | Consulter l'état du parc | Le décompte par état reflète le passage en maintenance | | |

---

## REC-10 — Consultation du tableau de bord

- **BF couvert(s)** : BF-14
- **Profil** : gestionnaire *(puis super-administrateur)*
- **Préconditions** : jeu de données en place, incluant un prêt en retard

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir le tableau de bord en gestionnaire | Les indicateurs s'affichent : prêts en cours, demandes en attente, prêts en retard, taux d'occupation | | |
| 2 | Vérifier l'indicateur « prêts en retard » | Le prêt en retard du jeu de données est bien décompté et mis en évidence | | |
| 3 | Consulter le classement des matériels les plus empruntés | Le classement s'affiche, cohérent avec l'historique du jeu de données | | |
| 4 | Se connecter en super-administrateur et rouvrir le tableau de bord | L'accès est accordé **par cumul de rôles** ; les mêmes indicateurs s'affichent | | |

---

## REC-11 — Consultation du journal des actions d'administration (RGPD)

- **BF couvert(s)** : BF-15
- **Profil** : super-administrateur
- **Préconditions** : être connecté en `admin.demo@creapret.local` ; avoir joué REC-07 et REC-08 (décisions à tracer)

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Ouvrir le journal d'administration | Les entrées s'affichent **de la plus récente à la plus ancienne** : date, acteur, action, cible, détails | | |
| 2 | Repérer les décisions de REC-07 et REC-08 | Une entrée **validation** et une entrée **refus** figurent, avec le **gestionnaire** comme acteur et l'**emprunteur** comme cible ; le motif de refus apparaît en détail | | |
| 3 | Filtrer par type d'action | Seules les entrées du type retenu sont listées ; le total affiché est cohérent | | |
| 4 | Parcourir la pagination *(si plus d'une page)* | La navigation fonctionne et conserve le filtre actif | | |

---

## REC-12 — Cloisonnement des rôles : accès à une zone interdite

- **BF couvert(s)** : transverse — exigence de sécurité (cf. plan de tests §7)
- **Profil** : emprunteur, puis gestionnaire
- **Préconditions** : connaître les URL des zones protégées

| # | Étape | Résultat attendu | Résultat obtenu | Conforme O/N |
|---|---|---|---|---|
| 1 | Connecté en **emprunteur**, saisir directement l'URL de la gestion des prêts | L'accès est **refusé** (403) ; aucune donnée de gestion n'est exposée | | |
| 2 | Connecté en **emprunteur**, saisir l'URL du journal d'administration | L'accès est **refusé** (403) | | |
| 3 | Connecté en **gestionnaire**, saisir l'URL de la gestion des comptes | L'accès est **refusé** (403) : la gestion des comptes est réservée au super-administrateur | | |
| 4 | **Déconnecté**, saisir l'URL du catalogue | Redirection vers la page de connexion, sans fuite de contenu | | |
| 5 | Connecté en **super-administrateur**, tenter de **désactiver son propre compte** | L'action est **refusée** ; le compte reste actif | | |

---

## Anomalies relevées

À compléter pendant l'exécution. Sévérité selon l'échelle du plan de tests §3bis.3
(bloquant / majeur / mineur / cosmétique).

| # | Scénario | Étape | Description de l'anomalie | Sévérité | Suite donnée |
|---|---|---|---|---|---|
| | | | | | |
| | | | | | |
| | | | | | |

---

*Grille de recette d'acceptation — CréaPrêt. Document **vierge**, à remplir manuellement après
exécution sur préproduction. Voir [plan de tests](plan-de-tests.md) §11 pour la démarche et ses
limites.*
