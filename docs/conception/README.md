# docs/conception/ — Le besoin et les choix

> Pour toute personne découvrant le dossier, y compris **non technicienne**.

Ce répertoire décrit, sujet par sujet, **ce que l'on cherche à obtenir** et **pourquoi telle
orientation a été retenue** — **sans jamais nommer** un produit, un logiciel ni une technique. On y
parle de **fonctions** et de **propriétés attendues**.

**Principe de séparation** : à chaque document de conception correspond un document de
**réalisation technique** (`../realisation-technique/`) qui décrit **ce qui a été fait**, avec les noms
réels et les commandes. La conception énonce l'**exigence** ; la réalisation en donne la **mise en
œuvre**.

| Sujet | Document |
|---|---|
| Mise à disposition de l'application | [`deploiement.md`](deploiement.md) |
| Surveillance de la disponibilité | [`surveillance.md`](surveillance.md) |
| Consultation des traces d'activité | [`journaux.md`](journaux.md) |
| Traitements automatiques | [`traitements-automatiques.md`](traitements-automatiques.md) |
| Gestion des versions et déploiement | [`versionnement.md`](versionnement.md) |
| Modèle métier — diagramme de classes | [`diagramme-classes.md`](diagramme-classes.md) |
| Modèle de données (3 niveaux) | [`modele-donnees.md`](modele-donnees.md) |
| Architecture en couches | [`architecture-couches.md`](architecture-couches.md) |

> Les trois derniers documents (classes, données, couches) sont des **artefacts de modélisation** : leur pendant en réalisation est **le code lui-même** (`../../src/`, `../../migrations/`).
