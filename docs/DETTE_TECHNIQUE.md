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

## DT-5 — make:migration menace les tables non mappees en entite

- **Statut** : Resolue (schema_filter configure).
- **Constat** : les tables alimentees hors ORM (historique_utilisateur, creee par la migration trigger
  US-5.2, et messenger_messages du transport Doctrine) ne sont pas mappees en entite. `make:migration`
  les voit comme orphelines et genere un DROP TABLE, ce qui detruirait la table d'audit et son trigger.
- **Impact** : toute generation de migration apres l'ajout d'une nouvelle entite proposait de
  supprimer historique_utilisateur (constate lors d'US-5.3).
- **Resolution** : ajout d'un schema_filter dans doctrine.yaml
  ('~^(?!historique_utilisateur|messenger_messages)~') pour que l'outil de diff ignore ces tables.
  Toute nouvelle table non-ORM devra etre ajoutee a ce filtre. A reevaluer si l'audit est un jour
  mappe en entite en lecture seule (cf. DT-2).

## DT-6 — Accentuation incoherente du texte visible du front

- **Statut** : Resolue (accents des templates HTML termines ; chantier complet : enums, landmarks, tous les templates HTML, emails, Form Types, validateur, messages PHP (flash/erreurs) et marque desormais accentues et coherents).
- **Constat** : le front a ete construit par iterations ; les templates et libelles anciens portent du
  texte non accentue (Materiels, Categories, Etat du parc, Prete...) tandis que les pages recentes
  (tableau de bord, journal, pages legales) et les emails sont accentues. Etat mixte.
- **Impact** : cosmetique uniquement (aucun defaut fonctionnel) ; nuit a la coherence visuelle et a la
  qualite percue du francais.
- **Resolution** : re-accentuation ciblee, lot par lot (edition chaine exacte par fichier, jamais de
  regex de substitution ; les segments Twig, attributs et valeurs backed d'enum sont preserves), avec
  resynchronisation des assertions de tests couplees dans le meme lot.


## DT-7 — Casse des valeurs backed de TypeActionJournal

- **Statut** : Ouverte (ecart de style assume).
- **Constat** : l'enumeration TypeActionJournal utilise des valeurs backed en MAJUSCULES
  (PRET_VALIDATION, COMPTE_CREATION...) persistees dans journal_admin.type_action, alors que les
  autres enumerations du projet (Role, StatutPret, EtatExemplaire) utilisent des valeurs en
  minuscules. Les COMPTE_* (US-6.2) ont ete alignes sur les PRET_* (US-5.3) pour la coherence
  INTERNE de l'enum, au prix de l'incoherence avec les autres enums.
- **Impact** : purement stylistique ; aucun defaut fonctionnel (les valeurs sont un contrat DB opaque,
  jamais affichees — l'affichage passe par libelle()).
- **Resolution ecartee** : aligner TypeActionJournal sur la convention minuscule imposerait une
  migration de donnees sur la colonne journal_admin.type_action (UPDATE des lignes deja ecrites) pour
  un gain purement cosmetique. Ecart assume ; le contrat DB reste stable.


## DT-8 — Mot de passe des comptes crees par un administrateur

- **Statut** : Ouverte (limite assumee).
- **Constat** : lors de la creation d'un compte par le super-administrateur (US-6.2), le mot de passe
  initial est saisi par l'administrateur puis transmis a l'utilisateur hors application (oral, courriel
  manuel...). Il n'existe ni obligation de changement a la premiere connexion, ni reinitialisation en
  autonomie par l'utilisateur.
- **Impact** : un secret transite hors du systeme et peut rester inchange ; pas de defaut bloquant pour
  le demonstrateur, mais ecart avec une gestion d'identite complete.
- **Resolution differee** : levee des qu'une reinitialisation de mot de passe par courriel (lien a usage
  unique) sera disponible ; le changement force a la premiere connexion pourra s'y greffer.


## DT-9 — style-src conserve 'unsafe-inline' dans la CSP

- **Statut** : Ouverte (compromis assume).
- **Constat** : la Content-Security-Policy (US-6.3) impose un script-src strict (nonce, sans
  'unsafe-inline'), mais conserve `style-src 'self' 'unsafe-inline'`. De nombreux gabarits portent des
  attributs `style=` inline (pages legales, conteneur de graphe, badge du calendrier, en-tete/pied,
  gabarits d'e-mail) qui ne peuvent porter ni nonce ni hash.
- **Impact** : la protection XSS via styles inline reste theorique et faible ; le vecteur principal
  (script) est, lui, verrouille par le nonce.
- **Resolution ecartee** : eliminer 'unsafe-inline' sur style-src supposerait de reecrire de nombreux
  gabarits (extraction de tous les style= vers app.css) pour un gain faible. Compromis assume, a revoir
  si les styles inline disparaissent d'eux-memes.


## DT-10 — Deploiement non conditionne a une integration continue verte

- **Statut** : Ouverte (ecart assume).
- **Constat** : les workflows de deploiement (deploy-preprod.yml, deploy-prod.yml) construisent et
  mettent en ligne sans dependre du succes du workflow d'integration continue (ci.yml : PHP-CS-Fixer,
  PHPStan, PHPUnit). Sur un push vers `preprod`/`main`, CI et deploiement s'executent en PARALLELE :
  un commit dont la suite echoue peut donc etre mis en ligne.
- **Impact** : risque de deployer une regression que la CI aurait detectee ; le smoke final (code HTTP,
  echec sur 000 ou 5xx) rattrape une panne franche mais pas un bug fonctionnel passe entre les mailles.
- **Resolution differee** : relier les deux chaines -- soit en faisant dependre le job de deploiement
  d'un job CI reussi (needs + reutilisation de ci.yml en workflow_call), soit via une regle de
  protection de branche exigeant les checks CI verts avant merge vers preprod/main. Ecart assume pour
  l'instant.

## DT-11 — Nommage `accepteCgu` / `version_cgu` alors que le consentement porte sur la confidentialité

- **Statut** : Ouverte (écart assumé, interne).
- **Constat** : le consentement recueilli à l'inscription porte désormais sur la **politique de
  confidentialité** (US-1.2 corrigée : le formulaire renvoie à la page de confidentialité, aucune CGU
  n'étant publiée). Les identifiants techniques conservent toutefois un vocabulaire de **conditions
  générales** : champ de formulaire `accepteCgu` (non mappé), propriété/colonne `version_cgu`, méthode
  `Utilisateur::consentir()` documentée « CGU », commentaires de `InscriptionController` et
  `UtilisateurAdminType`.
- **Impact** : **nul pour l'utilisateur** — le texte visible (libellé de la case, message de contrainte,
  lien vers la page) parle bien de politique de confidentialité. L'écart est purement interne
  (lisibilité du code).
- **Résolution écartée** : aligner les identifiants supposerait de **renommer la colonne `version_cgu`**
  et de **migrer les données** existantes (consentements déjà enregistrés), pour un bénéfice strictement
  interne. Compromis assumé ; à traiter si une migration touchant `utilisateur` intervient par ailleurs.

## DT-12 — L'annulation d'une demande par l'emprunteur n'est pas tracée

- **Statut** : Ouverte (écart assumé).
- **Constat** : l'annulation d'une demande de prêt par l'emprunteur (CU-06 / BF-6, self-service —
  `PretController::annuler` → `PretService::annuler`) fait passer le prêt à `ANNULE` **sans** écrire
  dans `journal_admin` ; `TypeActionJournal` ne comporte **aucun** cas d'annulation. L'historique
  d'administration présente donc des **demandes disparues sans cause** visible.
- **Conséquence** : un lecteur du journal ne peut expliquer la disparition d'une demande. Non
  bloquant — l'annulation est une action *self-service* de l'emprunteur, pas une décision
  d'administration — mais lacune de traçabilité.
- **Condition de levée** : ajouter un cas `TypeActionJournal` (p. ex. `PRET_ANNULATION`) et
  journaliser l'annulation (dans `PretController::annuler` ou le service) ; **puis** rétablir la
  relation `CU-06 «include» CU-T2` dans l'analyse fonctionnelle.

## DT-13 — Le livrable mis en production est reconstruit au lieu d'être promu depuis la validation

- **Statut** : Ouverte (écart de conception).
- **Constat** : la mise en production (`.github/workflows/deploy-prod.yml`) exécute son **propre**
  travail de construction (`build:` → `uses: ./.github/workflows/build-push.yml`) au lieu de réutiliser
  l'image déjà construite et vérifiée en préproduction. L'image est **reconstruite** à partir des
  sources du commit (tag = empreinte `GITHUB_SHA`), puis déployée.
- **Conséquence** : **ce qui est vérifié n'est pas ce qui est exposé** — la production reçoit une
  reconstruction, non l'artefact validé ; la garantie « le livrable installé est celui qui a été
  vérifié » (dossier §2.7.2) n'est pas tenue.
- **Condition de levée** : **réutiliser l'artefact validé par son empreinte** — promouvoir l'image de
  préproduction identifiée par son SHA plutôt que la reconstruire en production.
