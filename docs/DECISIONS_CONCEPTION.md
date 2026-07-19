# Décisions de conception — CréaPrêt

Ce document est le **registre canonique** des décisions de conception structurantes : pour chaque
arbitrage, l'option retenue, sa justification et l'alternative écartée — de sorte qu'une décision
puisse être contestée sur son **raisonnement**, non sur son seul résultat. Il complète le registre de
dette technique ([`DETTE_TECHNIQUE.md`](DETTE_TECHNIQUE.md)) : ici les choix *délibérés*, là les
compromis *subis*. Les entrées DC-1 à DC-10 sont reprises du §6 du dossier de conception ; DC-8 et
DC-10 sont référencées dans le code.

| Statut | Signification |
|---|---|
| Actée | Décision prise et appliquée |
| Révisée | Remplacée par une décision ultérieure (conservée pour l'historique) |
| — | À compléter |

*(Colonnes « Statut » et « Section du dossier » laissées à compléter.)*

---

| # | Décision | Justification | Alternative écartée | Statut | Section du dossier |
|---|---|---|---|:--:|:--:|
| DC-1 | **Exclure la réservation de salles** | Une salle est occupée par le planning pédagogique ; sans intégration à ce planning, les disponibilités seraient fausses (défaut d'intégrité). | Gérer les salles sans le planning (produirait des données incorrectes). | — | — |
| DC-2 | **Modèle plat sans héritage** | Conséquence de DC-1 : un seul type de ressource. Schéma pleinement normalisé (3NF), aucune colonne nullable de sous-type. | Héritage `Ressource → Matériel/Salle` (sans salle, artificiel). | — | — |
| DC-3 | **Distinguer Matériel / Exemplaire / Prêt** | L'inventaire et le verrou portent sur l'**unité physique** (exemplaire), pas sur le modèle. Permet « 2 exemplaires libres sur 3 ». | Prêter le « matériel » directement (impossible de suivre l'inventaire). | — | — |
| DC-4 | **Verrou pessimiste (`PESSIMISTIC_WRITE`) sur l'exemplaire** | Conflits de validation concurrente traités de façon déterministe, sans retry ni échec renvoyé à l'utilisateur. | Verrou optimiste (version + retry) : adapté aux conflits rares, moins sûr ici. | — | — |
| DC-5 | **Période en `datetime` unifié** | Un seul type de donnée et un seul algorithme de chevauchement pour tous les prêts. | Deux granularités (horaire vs journalier) : complexité sans valeur. | — | — |
| DC-6 | **Minimisation RGPD sur `Utilisateur`** | Pas de téléphone ni d'adresse (aucune finalité : retrait/retour sur place, notifications par email). `dateCreation` pour la durée de conservation. | Collecter téléphone/adresse « au cas où » (sur-collecte). | — | — |
| DC-7 | **`emailRappel` booléen unique** | Pilote le seul email de confort (rappel J-1) ; les emails de service (confirmation, retard) restent envoyés. Suffisant au périmètre. | Table de préférences multi-canaux (sur-ingénierie). | — | — |
| DC-8 | **Enums PHP *backed* stockés en `VARCHAR`** | Sûreté de typage dans le code + souplesse en base (ajout de valeur sans `ALTER`). | Type `ENUM` SQL (rigide, migration à chaque valeur). | — | — |
| DC-9 | **Trigger d'audit + procédure stockée** | Traçabilité RGPD des modifications de comptes ; démontre le savoir-faire SQL avancé (CP8). Prérequis : `log_bin_trust_function_creators=1`. | Audit applicatif seul (moins démonstratif pour CP8). | — | — |
| DC-10 | **Suppression logique (`est_actif`) + FK `RESTRICT`** | Préservation de l'historique des prêts et de l'intégrité référentielle. | Suppression physique (perte de traçabilité). | — | — |
| DC-11 | **Tableau de bord porté par `ROLE_GESTIONNAIRE`** | Le pilotage du parc (matériel le plus emprunté, taux d'utilisation, prêts en retard) est un besoin opérationnel du gestionnaire, premier concerné par les décisions qu'il alimente. Rattacher la fonction au rôle le plus bas qui en a l'usage élargit l'accès sans le restreindre : le super-administrateur le conserve par cumul, ce qui satisfait BF-14. Contrôleur en `Gestion/`, route `/gestion/tableau-de-bord`. | Réserver le tableau de bord au super-administrateur — l'aurait rendu inaccessible au premier concerné, pour un gain de confidentialité nul (données agrégées, non nominatives). | — | — |
| DC-12 | **Deux mécanismes d'audit distincts** | Le journal d'administration applicatif (`journal_admin`, entité mappée, append-only) trace les décisions métier riches (motifs de refus, actions sur comptes) ; l'audit par déclencheur SQL (`trg_historique_utilisateur` → `historique_utilisateur`, non mappé) garantit l'inviolabilité de la trace des champs les plus sensibles (rôle, activation), car indépendant du chemin d'écriture. | Un journal unique — tout applicatif (contournable, sans inviolabilité) ou tout par déclencheur (inadapté à des décisions métier motivées). | — | — |
