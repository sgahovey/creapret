# Conception — Analyse fonctionnelle

> Pour un lecteur non technicien qui veut comprendre **ce que fait le système** : qui l'utilise, quels
> objectifs chacun poursuit, et quelles règles encadrent ces objectifs. On raisonne en **objectifs
> métier**, pas en écrans ni en opérations techniques.

## a) Les acteurs et leur périmètre

Trois profils, dont les habilitations **se cumulent** — chacun dispose de **toutes celles du
précédent, plus les siennes** :

- **Emprunteur** — consulte le catalogue, vérifie une disponibilité, demande et suit ses prêts.
- **Gestionnaire** — *tout ce que fait l'emprunteur*, **plus** : décider des demandes, enregistrer les
  retours, tenir l'inventaire, suivre l'activité.
- **Super-administrateur** — *tout ce que fait le gestionnaire*, **plus** : administrer les comptes et
  consulter le journal d'administration.

*(Le **visiteur** non authentifié ne peut que se connecter : c'est le socle commun.)*

**Pourquoi un cumul plutôt que des rôles disjoints.** Le cumul **reflète la réalité** : un
gestionnaire reste une personne qui peut elle aussi emprunter ; un super-administrateur reste un
gestionnaire. Modéliser des rôles **disjoints** obligerait à **redéclarer** les mêmes objectifs à
chaque niveau, multipliant les risques d'**oubli** ou d'**incohérence**. Le cumul exprime une
**hiérarchie de responsabilité** : on **ajoute** des pouvoirs, on n'en retire jamais. C'est plus
simple à raisonner et plus sûr (aucun « trou » d'habilitation).

## b) Diagramme de cas d'utilisation d'ensemble

Le diagramme de cas d'utilisation possède une **notation propre** — acteurs en silhouette, cas en
ellipses, généralisation entre acteurs, inclusion et extension en pointillés stéréotypés — que porte
la **source normalisée** versionnée à côté : [`analyse-fonctionnelle.puml`](analyse-fonctionnelle.puml).
Elle n'est **pas reproduite ici**, afin de ne pas exposer, dans un document de conception, la notation
propre à un outil de rendu.

![Diagramme de cas d'utilisation de CréaPrêt : les trois acteurs cumulatifs (Emprunteur, Gestionnaire, Super-administrateur), les quinze cas d'utilisation CU-01 à CU-15 (un par besoin fonctionnel) et les trois cas transverses CU-T1 notifier, CU-T2 tracer, CU-T3 mettre en maintenance, reliés par des relations d'inclusion et d'extension.](analyse-fonctionnelle.png)

*Image rendue depuis la source normalisée [`analyse-fonctionnelle.puml`](analyse-fonctionnelle.puml) versionnée à côté. Régénération : `plantuml docs/conception/analyse-fonctionnelle.puml`.*

**Structure représentée :**

- **Trois acteurs**, en **généralisation cumulative** : Emprunteur ◁ Gestionnaire ◁
  Super-administrateur (chacun hérite des cas du précédent).
- **Quinze cas d'utilisation**, un par besoin fonctionnel (`CU-nn = BF-nn`), associés à l'acteur qui
  les introduit :
  - *Emprunteur* — CU-01 S'inscrire et se connecter ; CU-02 Consulter le catalogue ; CU-03 Consulter
    la disponibilité ; CU-04 Demander un prêt ; CU-05 Suivre ses prêts ; CU-06 Annuler une demande
    non validée.
  - *Gestionnaire* — CU-07 Valider / refuser une demande ; CU-08 Enregistrer un retour ; CU-09 Gérer
    le catalogue ; CU-10 Gérer l'inventaire ; CU-11 Consulter l'état du parc ; CU-12 Consulter le
    calendrier d'occupation ; CU-14 Consulter le tableau de bord *(porté par le rôle gestionnaire —
    DC-11 ; le super-administrateur y accède par cumul)*.
  - *Super-administrateur* — CU-13 Gérer les comptes ; CU-15 Consulter le journal d'administration.
- **Trois cas transverses**, cibles de relations :
  - **CU-T1 · Notifier la personne concernée** — *«include»* par CU-04, CU-07 et CU-08 (toujours
    déclenché).
  - **CU-T2 · Tracer l'action dans le journal** — *«include»* par CU-07, CU-08 et CU-13.
  - **CU-T3 · Mettre l'exemplaire en maintenance** — *«extend»* de CU-08, **sous condition** (exemplaire
    rendu endommagé).

**Légende (obligatoire) :**

| Symbole / trait | Signification |
|---|---|
| Silhouette (acteur) | Un profil qui poursuit des objectifs |
| Ellipse (cas d'utilisation) | Un **objectif métier** rendu par le système |
| Flèche à pointe creuse (généralisation) | L'acteur **hérite** des cas du précédent (cumul) |
| Trait simple (acteur — cas) | **Association** : l'acteur poursuit ce cas |
| Pointillé « include » | Le cas source **déclenche toujours** le cas cible |
| Pointillé « extend » | Le cas source **étend sous condition** le cas cible |

**Sur la granularité et la traçabilité.** Les cas d'utilisation sont **alignés 1:1 sur les besoins
fonctionnels** : à `BF-n` correspond `CU-n` (par exemple `BF-4 → CU-04`, « demander un prêt »). Cet
alignement rend la **couverture vérifiable d'un simple examen des numéros** — un besoin sans cas
d'utilisation homologue se repère immédiatement. Les relations **«include»** et **«extend»** restent
employées **avec parcimonie**, seulement là où un comportement est **réellement partagé** (notifier,
tracer) ou **conditionnel** (mise en maintenance).

**Matrice de couverture BF ↔ CU.** Alignement 1:1 — **15 BF / 15 CU, sans trou ni orphelin**.

| BF | CU | Acteur | BF | CU | Acteur |
|:--:|:--:|---|:--:|:--:|---|
| BF-1 | CU-01 | Emprunteur | BF-9 | CU-09 | Gestionnaire |
| BF-2 | CU-02 | Emprunteur | BF-10 | CU-10 | Gestionnaire |
| BF-3 | CU-03 | Emprunteur | BF-11 | CU-11 | Gestionnaire |
| BF-4 | CU-04 | Emprunteur | BF-12 | CU-12 | Gestionnaire |
| BF-5 | CU-05 | Emprunteur | BF-13 | CU-13 | Super-admin. |
| BF-6 | CU-06 | Emprunteur | BF-14 | CU-14 | Gestionnaire *(DC-11)* |
| BF-7 | CU-07 | Gestionnaire | BF-15 | CU-15 | Super-admin. |
| BF-8 | CU-08 | Gestionnaire | | | |

Les trois cas transverses **CU-T1 / CU-T2 / CU-T3** ne correspondent à **aucun BF** (comportements
*«include»* / *«extend»*).

## c) Description des cas les plus significatifs

### CU-04 · Emprunter du matériel (BF-4)

- **Acteur** : emprunteur.
- **Déclencheur** : depuis la fiche d'un matériel, il demande un prêt sur une période.
- **Préconditions** : être connecté ; avoir choisi une période cohérente (début avant fin).
- **Déroulement nominal** : le système vérifie qu'**au moins un exemplaire est disponible** sur la
  période (RG-4) ; il enregistre la demande à l'état « en attente » ; il **notifie** la personne
  concernée.
- **Cas alternatifs / erreurs** : période incohérente → la demande est refusée avec explication ;
  **aucun exemplaire disponible** sur la période → la demande n'est pas créée, l'emprunteur est averti.
- **Postconditions** : une demande de prêt existe, **en attente de décision** ; rien n'est encore
  réservé de manière définitive.

### CU-07 · Décider d'une demande de prêt (BF-7)

- **Acteur** : gestionnaire.
- **Déclencheur** : il traite une demande en attente (validation ou refus motivé).
- **Préconditions** : la demande est **encore en attente**.
- **Déroulement nominal (validation)** : le système **s'assure, au moment de décider et de façon
  protégée contre les décisions concurrentes**, qu'aucun prêt déjà validé ne chevauche la même période
  sur le même exemplaire (RG-1) ; si tout est libre, le prêt devient « validé » et l'exemplaire passe
  « prêté » ; la personne est **notifiée** et la décision est **tracée** au journal.
- **Cas alternatifs / erreurs** : la demande a **déjà été traitée** entre-temps → aucune action, le
  gestionnaire en est informé ; **un prêt concurrent a été validé** pour la même période (conflit
  détecté au moment de décider) → la demande est **automatiquement refusée** et la personne notifiée ;
  **refus** volontaire → un **motif** est exigé, la demande passe « refusée », notification et trace.
- **Postconditions** : la demande est « validée » (exemplaire « prêté ») ou « refusée » (motif
  conservé) ; une trace figure au journal.

### CU-08 · Enregistrer un retour (BF-8)

- **Acteur** : gestionnaire.
- **Déclencheur** : un exemplaire prêté est restitué.
- **Préconditions** : le prêt est **en cours** (« validé »).
- **Déroulement nominal** : le prêt passe « retourné », daté ; l'exemplaire **redevient disponible**
  (RG-3) ; la personne est **notifiée** et le retour est **tracé**.
- **Cas alternatif** (« extend ») : si l'exemplaire est **rendu endommagé**, il passe non pas
  « disponible » mais **« en maintenance »** — il sort temporairement du parc prêtable.
- **Postconditions** : le prêt est clos ; l'exemplaire est de nouveau disponible **ou** en maintenance.

### CU-13 · Administrer un compte (BF-13)

- **Acteur** : super-administrateur.
- **Déclencheur** : il crée un compte, en modifie le rôle, ou l'active / le désactive.
- **Préconditions** : être super-administrateur ; pour une désactivation, **ne pas viser son propre
  compte**.
- **Déroulement nominal** : l'opération est appliquée ; elle est **tracée** au journal
  d'administration ; les changements sensibles (rôle, activation) sont en outre **historisés** de
  façon inviolable.
- **Cas alternatifs / erreurs** : tentative de **se désactiver soi-même** → refusée ; adresse déjà
  utilisée à la création → refusée avec explication.
- **Postconditions** : le compte reflète le changement ; une trace **immuable** en subsiste, même si le
  compte est supprimé plus tard.

## d) Rattachement des règles de gestion aux cas

| Règle | Où elle s'applique | Si elle n'est pas satisfaite |
|---|---|---|
| **RG-1** — non-chevauchement de deux prêts actifs sur un exemplaire, même en concurrence | **CU-07** (au moment de décider, de façon protégée contre les décisions simultanées) | La demande est **automatiquement refusée** (conflit) et la personne notifiée. |
| **RG-2** — un exemplaire indisponible (maintenance, hors service, perdu) ne peut être prêté | **CU-04** et **CU-07** (seuls les exemplaires prêtables sont comptés) | L'exemplaire est **ignoré** dans la disponibilité ; il ne peut faire l'objet d'un prêt. |
| **RG-3** — au retour, l'exemplaire redevient disponible (ou passe en maintenance si dommage) | **CU-08** | Sans enregistrement du retour, l'exemplaire **reste indisponible** : il ne peut être reprêté. |
| **RG-4** — la disponibilité ne compte que les exemplaires libres sur la période demandée | **CU-04** (et la recherche de matériel) | La demande **n'est pas créée** ; l'emprunteur est averti qu'aucun exemplaire n'est libre. |

*Source normalisée (notation UML) versionnée à côté : [`analyse-fonctionnelle.puml`](analyse-fonctionnelle.puml).*
