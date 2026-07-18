# docs/realisation-technique/ — Ce qui a été fait

> Pour un lecteur technique.

Ce répertoire décrit, sujet par sujet, **ce qui a été mis en place** : **noms réels**, fichiers,
commandes. Chaque document **ouvre** en renvoyant à sa **conception** (`../conception/`) et **se
termine** par les **écarts** éventuels entre la réalisation et l'intention initiale.

**Principe de séparation** : la conception énonce le **besoin** et les **choix** (sans nommer d'outil) ;
la réalisation en donne la **mise en œuvre** concrète.

| Sujet | Réalisation | Conception |
|---|---|---|
| Mise à disposition | [`deploiement.md`](deploiement.md) | [`../conception/deploiement.md`](../conception/deploiement.md) |
| Surveillance | [`surveillance.md`](surveillance.md) | [`../conception/surveillance.md`](../conception/surveillance.md) |
| Consultation des journaux | [`journaux.md`](journaux.md) | [`../conception/journaux.md`](../conception/journaux.md) |
| Traitements automatiques | [`traitements-automatiques.md`](traitements-automatiques.md) | [`../conception/traitements-automatiques.md`](../conception/traitements-automatiques.md) |
| Gestion des versions | [`versionnement.md`](versionnement.md) | [`../conception/versionnement.md`](../conception/versionnement.md) |
| Analyse fonctionnelle | `../../src/Controller/` · `../../src/Service/` | [`../conception/analyse-fonctionnelle.md`](../conception/analyse-fonctionnelle.md) |
| Modèle métier (classes) | `../../src/Entity/` · `../../src/Enum/` | [`../conception/diagramme-classes.md`](../conception/diagramme-classes.md) |
| Modèle de données | `../../migrations/` | [`../conception/modele-donnees.md`](../conception/modele-donnees.md) |
| Architecture en couches | `../../src/` (Controller · Service · Repository · Entity) | [`../conception/architecture-couches.md`](../conception/architecture-couches.md) |
