# Conception — Modèle de données

> Pour un lecteur qui veut comprendre **comment les données sont structurées**, du concept jusqu'à la
> base. On progresse en **trois niveaux**, du plus abstrait au plus concret. Le niveau physique et le
> script de création relèvent **nécessairement d'un système de gestion de base de données** : c'est
> l'**exception admise** à la règle « conception sans nommer de produit », signalée comme telle.

## Niveau 1 — Schéma conceptuel

Les concepts et leurs associations, avec cardinalités, **sans aucune considération d'implémentation** :

- Une **Catégorie** classe *zéro à plusieurs* **Matériels** ; un Matériel appartient à *exactement une*
  Catégorie.
- Un **Matériel** se décline en *zéro à plusieurs* **Exemplaires** ; un Exemplaire est une unité
  d'*exactement un* Matériel (**dépendance d'existence**).
- Un **Exemplaire** fait l'objet de *zéro à plusieurs* **Prêts** ; un Prêt porte sur *exactement un*
  Exemplaire.
- Un **Utilisateur** *emprunte* *zéro à plusieurs* **Prêts** ; un Prêt a *exactement un* emprunteur.
- Un **Utilisateur** *valide* *zéro à plusieurs* **Prêts** ; un Prêt a *au plus un* validateur.
- Le **Journal d'administration** et l'**Historique de compte** enregistrent des faits ; le premier
  est **autonome** (aucune association), le second est rattaché au compte qu'il trace.

## Niveau 2 — Schéma logique

Clés primaires **soulignées**, clés étrangères préfixées `#`. L'association **Prêt**, porteuse
d'attributs propres (dates, statut), est **résolue en une relation à part entière** :

- **categorie**(<u>id</u>, nom, description)
- **materiel**(<u>id</u>, nom, description, marque, modele, reference, #id_categorie)
- **exemplaire**(<u>id</u>, numero_inventaire, etat, #id_materiel) — *numero_inventaire* unique
- **pret**(<u>id</u>, date_debut, date_fin, statut, motif_refus, date_demande, date_validation,
  date_retour, #id_exemplaire, #id_emprunteur, #id_validateur)
- **utilisateur**(<u>id</u>, email, mot_de_passe_hash, nom, prenom, role, est_actif, email_rappel,
  date_creation, date_consentement, version_cgu) — *email* unique
- **journal_admin**(<u>id</u>, date_action, type_action, acteur_id, acteur_libelle, cible_id,
  cible_libelle, details) — *aucune clé étrangère*
- **historique_utilisateur**(<u>id</u>, #utilisateur_id, champ_modifie, ancienne_valeur,
  nouvelle_valeur, date_modification)

## Niveau 3 — Schéma physique *(exception SGBD : MySQL 8)*

Tables, colonnes, types, contraintes et index, **reconstitués depuis les migrations réelles**. Le
détail exécutable figure dans le **script de création** joint :
[`script-creation-bdd.sql`](script-creation-bdd.sql).

Points saillants du niveau physique :

- **Types** : `VARCHAR(n)` pour les textes courts (email 180, nom/prénom 100, numéro d'inventaire 50,
  libellés de journal 201) ; `LONGTEXT` pour les textes libres (description de matériel, détails de
  journal) ; `DATETIME` pour les dates ; `TINYINT` pour les booléens.
- **Énumérés stockés en texte** : `role`, `etat`, `statut`, `type_action` sont des `VARCHAR` (pas de
  type énuméré natif) — sûreté de typage assurée côté application, lisibilité en base.
- **Index de performance** : `idx_pret_dispo (id_exemplaire, statut, date_debut, date_fin)` pour le
  contrôle de non-chevauchement et de disponibilité ; `idx_exemplaire_materiel_etat (id_materiel,
  etat)` pour la disponibilité par matériel.

## Règles de nommage appliquées

- **Underscore, minuscules** : tables et colonnes en `snake_case` (`date_debut`, `numero_inventaire`).
- **Clés étrangères préfixées `id_`** : `id_categorie`, `id_materiel`, `id_exemplaire`,
  `id_emprunteur`, `id_validateur`.
- **Index d'unicité** préfixés `uniq_`, **index de performance** préfixés `idx_`.
- **Exception assumée** : la table d'audit `historique_utilisateur` utilise `utilisateur_id` (suffixe)
  et non `id_utilisateur` — elle suit le **nom par défaut engendré par la couche de correspondance
  objet-relationnel**, l'entité n'étant pas mappée comme les autres.

## Intégrité référentielle et justifications

Le choix central : **refuser une suppression plutôt que la propager**, sauf là où la propagation a un
sens métier.

| Lien | Politique | Pourquoi |
|---|---|---|
| materiel → categorie | **RESTRICT** | On refuse de supprimer une catégorie encore utilisée : évite les matériels orphelins. |
| exemplaire → materiel | **RESTRICT** | Un matériel ayant des exemplaires ne se supprime pas : préserve l'inventaire. |
| pret → exemplaire | **RESTRICT** | Un exemplaire ayant des prêts (même passés) est conservé : préserve l'historique. |
| pret → emprunteur | **RESTRICT** | Un compte ayant emprunté n'est pas effaçable tel quel : la responsabilité reste traçable. |
| pret → validateur | **SET NULL** | La suppression d'un gestionnaire **ne détruit pas** les prêts qu'il a validés ; le lien se **dénoue** simplement. |
| historique_utilisateur → utilisateur | **CASCADE** | L'audit *d'un* compte disparaît **avec** ce compte : c'est son historique propre. |

## Contraintes d'unicité

- **utilisateur.email** — identifiant de connexion, nécessairement unique.
- **exemplaire.numero_inventaire** — identifiant physique d'inventaire, unique.

## Place du déclencheur d'audit

Un **déclencheur** `AFTER UPDATE` sur `utilisateur` (`trg_historique_utilisateur`) écrit une ligne
dans `historique_utilisateur` à **chaque** changement de **rôle** ou d'**activation**. Une
**procédure** (`consulter_historique_utilisateur`) restitue cet historique, du plus récent au plus
ancien. Le fait de placer l'audit **dans la base** — et non dans l'application — le rend
**indépendant du chemin d'écriture** : quel que soit le moyen par lequel un compte est modifié, la
trace est écrite. C'est une garantie d'inviolabilité (CP8).

## Minimisation des données personnelles

Le modèle **ne collecte que l'identité strictement nécessaire** au prêt de matériel interne :
**email, nom, prénom**, complétés du **rôle**, de l'**état** du compte, d'une **préférence de rappel**
et des **métadonnées de consentement** (`date_consentement`, `version_cgu`).

**Volontairement non collectés** : **numéro de téléphone** et **adresse postale**. Justification —
**principe de minimisation** (RGPD, art. 5.1.c) : le service fonctionne par **courriel** et **retrait
sur place**, aucune de ces données n'y est utile. Ne pas les collecter **réduit la surface de risque**
(moins de données sensibles à protéger, à exporter, à effacer) sans dégrader le service.
