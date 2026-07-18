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

![Diagramme de cas d'utilisation de CréaPrêt : les quatre acteurs cumulatifs (Visiteur, Emprunteur, Gestionnaire, Super-administrateur), les dix objectifs métier et les trois cas transverses (notifier la personne concernée, tracer l'action, mettre en maintenance) reliés par des relations d'inclusion et d'extension.](analyse-fonctionnelle.png)

*Image rendue depuis la source normalisée [`analyse-fonctionnelle.puml`](analyse-fonctionnelle.puml) versionnée à côté. Régénération : `plantuml docs/conception/analyse-fonctionnelle.puml`.*

**Structure représentée :**

- **Quatre acteurs**, en **généralisation cumulative** : Visiteur ◁ Emprunteur ◁ Gestionnaire ◁
  Super-administrateur (chacun hérite des cas du précédent).
- **Dix objectifs métier**, associés à l'acteur qui les introduit :
  - *Visiteur* — Se connecter à son espace.
  - *Emprunteur* — Rechercher du matériel disponible ; Emprunter du matériel ; Suivre ses prêts.
  - *Gestionnaire* — Décider d'une demande de prêt ; Enregistrer un retour ; Tenir l'inventaire à
    jour ; Suivre l'activité de prêt.
  - *Super-administrateur* — Administrer les comptes ; Consulter le journal d'administration.
- **Trois cas transverses**, cibles de relations :
  - *Notifier la personne concernée* — **inclus** par « Emprunter du matériel », « Décider d'une
    demande » et « Enregistrer un retour » (toujours déclenché).
  - *Tracer l'action dans le journal* — **inclus** par « Décider d'une demande », « Enregistrer un
    retour » et « Administrer les comptes ».
  - *Mettre l'exemplaire en maintenance* — **étend** « Enregistrer un retour » **sous condition** (si
    l'exemplaire est rendu endommagé).

**Légende (obligatoire) :**

| Symbole / trait | Signification |
|---|---|
| Silhouette (acteur) | Un profil qui poursuit des objectifs |
| Ellipse (cas d'utilisation) | Un **objectif métier** rendu par le système |
| Flèche à pointe creuse (généralisation) | L'acteur **hérite** des cas du précédent (cumul) |
| Trait simple (acteur — cas) | **Association** : l'acteur poursuit ce cas |
| Pointillé « include » | Le cas source **déclenche toujours** le cas cible |
| Pointillé « extend » | Le cas source **étend sous condition** le cas cible |

**Sur la granularité.** On vise ici **une dizaine d'objectifs métier**, pas une bulle par écran.
« Rechercher du matériel disponible » est un objectif ; « afficher la liste des exemplaires » n'en
serait qu'une étape. Le **découpage par acteur en diagrammes séparés** — pertinent pour un système à
trente cas — **n'est pas nécessaire ici** : à cette granularité, le diagramme d'ensemble reste
lisible. Les relations **« include »** et **« extend »** sont employées **avec parcimonie**, seulement
là où un comportement est **réellement partagé** (notifier, tracer) ou **conditionnel** (mise en
maintenance).

## c) Description des cas les plus significatifs

### C1 — Emprunter du matériel

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

### C2 — Décider d'une demande de prêt

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

### C3 — Enregistrer un retour

- **Acteur** : gestionnaire.
- **Déclencheur** : un exemplaire prêté est restitué.
- **Préconditions** : le prêt est **en cours** (« validé »).
- **Déroulement nominal** : le prêt passe « retourné », daté ; l'exemplaire **redevient disponible**
  (RG-3) ; la personne est **notifiée** et le retour est **tracé**.
- **Cas alternatif** (« extend ») : si l'exemplaire est **rendu endommagé**, il passe non pas
  « disponible » mais **« en maintenance »** — il sort temporairement du parc prêtable.
- **Postconditions** : le prêt est clos ; l'exemplaire est de nouveau disponible **ou** en maintenance.

### C4 — Administrer un compte

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
| **RG-1** — non-chevauchement de deux prêts actifs sur un exemplaire, même en concurrence | **C2** (au moment de décider, de façon protégée contre les décisions simultanées) | La demande est **automatiquement refusée** (conflit) et la personne notifiée. |
| **RG-2** — un exemplaire indisponible (maintenance, hors service, perdu) ne peut être prêté | **C1** et **C2** (seuls les exemplaires prêtables sont comptés) | L'exemplaire est **ignoré** dans la disponibilité ; il ne peut faire l'objet d'un prêt. |
| **RG-3** — au retour, l'exemplaire redevient disponible (ou passe en maintenance si dommage) | **C3** | Sans enregistrement du retour, l'exemplaire **reste indisponible** : il ne peut être reprêté. |
| **RG-4** — la disponibilité ne compte que les exemplaires libres sur la période demandée | **C1** (et la recherche de matériel) | La demande **n'est pas créée** ; l'emprunteur est averti qu'aucun exemplaire n'est libre. |

*Source normalisée (notation UML) versionnée à côté : [`analyse-fonctionnelle.puml`](analyse-fonctionnelle.puml).*
