# Maquettes lo-fi — CréaPrêt

Maquettes **basse fidélité** (fil de fer) des écrans significatifs de l'application, pour le
dossier de conception. Elles décrivent la **structure** de chaque écran — zones, blocs,
hiérarchie de l'information, actions disponibles — **et rien d'autre**.

> ⚠️ Ces représentations sont **volontairement schématiques** et **antérieures à tout choix
> graphique**. Elles n'engagent ni couleur, ni police, ni icône, ni style visuel : un lecteur
> ne doit pas pouvoir en déduire la charte graphique. Niveaux de gris uniquement ; le texte
> réel est figuré par des barres, les zones par des rectangles.

## Convention de lecture

- **Rectangle bordé** : une zone (carte, champ, tableau, encart).
- **Barre grise** : du texte (une barre foncée = un titre, une barre claire = une ligne).
- **Rectangle plein grisé** : un bouton ou une action.
- **Petit carré** : l'emplacement d'un repère visuel (non dessiné à ce stade).
- **Étiquette courte** : nomme un élément quand c'est utile à la compréhension.
- Chaque maquette porte un **numéro**, un **titre** et une **légende d'une ligne** indiquant
  le **profil** concerné et ce qu'elle **permet**.

Deux maquettes sont explicitement **représentatives** d'une famille d'écrans répétitifs :
la **liste d'inventaire** (n° 10) vaut pour les listes catégories / matériels / exemplaires /
parc, et le **formulaire de création** (n° 11) vaut pour les formulaires de création et
d'édition. Elles ne sont pas dupliquées pour chaque collection.

## Liste des maquettes

Sommaire interactif : [`index.html`](index.html).

| N° | Écran |
|---|---|
| 01 | [Accueil](01-accueil.html) |
| 02 | [Connexion](02-connexion.html) |
| 03 | [Catalogue du matériel](03-emprunteur-catalogue.html) |
| 04 | [Fiche matériel — disponibilité et demande](04-emprunteur-fiche-materiel.html) |
| 05 | [Mes prêts](05-emprunteur-mes-prets.html) |
| 06 | [Tableau de bord](06-gestionnaire-tableau-de-bord.html) |
| 07 | [Demandes de prêt en attente](07-gestionnaire-demandes.html) |
| 08 | [Enregistrement des retours](08-gestionnaire-retours.html) |
| 09 | [Calendrier d'occupation](09-gestionnaire-calendrier.html) |
| 10 | [Liste d'inventaire (représentative)](10-gestionnaire-inventaire-liste.html) |
| 11 | [Formulaire de création (représentatif)](11-gestionnaire-formulaire-creation.html) |
| 12 | [Gestion des comptes](12-admin-comptes.html) |
| 13 | [Journal d'administration](13-admin-journal.html) |

## Enchaînement des écrans

Le parcours de navigation entre ces écrans — points d'entrée, transitions libres et actions
déclenchant un traitement, dépendances au profil — est décrit dans
[`enchainement.md`](enchainement.md) (source : [`enchainement.mermaid`](enchainement.mermaid)).

---

*Aucun nom de technologie ne figure dans ces maquettes ni dans leurs commentaires : elles
appartiennent à la conception.*
