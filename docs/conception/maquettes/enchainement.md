# Schéma d'enchaînement des écrans

> Pour un lecteur de conception. Ce schéma montre **comment on navigue** d'un écran à l'autre,
> par quelle action, et quelles transitions dépendent du profil. Il complète les maquettes
> (fichiers `01` à `13`) sans en reprendre le détail.

## Convention de lecture

- Les écrans sont **regroupés par profil**.
- **Flèche pleine** : navigation libre (simple consultation, aucun effet).
- **Flèche en pointillés avec libellé** : une **action qui déclenche un traitement**
  (par exemple demander un prêt, valider, enregistrer un retour).
- Les **numéros** renvoient aux maquettes correspondantes.
- Les profils sont **cumulatifs** : le gestionnaire accède aussi au catalogue ; le
  super-administrateur cumule les accès du gestionnaire.

## Diagramme

```mermaid
%% Schema d'enchainement des ecrans de CreaPret.
%% Ecrans regroupes par profil. Les fleches pleines = navigation libre (consultation).
%% Les fleches en pointilles avec libelle = une action qui declenche un traitement.
%% Les numeros renvoient aux maquettes (fichiers 01 a 13).
flowchart TB
    START(["Point d'entree"]) --> ACC["01 - Accueil"]
    ACC --> CON["02 - Connexion"]
    %% L'authentification est un traitement ; l'ecran d'arrivee depend du profil.
    CON -. "s'authentifier (traitement)" .-> AIG{"Selon le profil"}

    subgraph EMP["Profil emprunteur"]
        direction TB
        CAT["03 - Catalogue"]
        FIC["04 - Fiche materiel"]
        MES["05 - Mes prets"]
        CAT --> FIC
        CAT --- MES
        %% Demander un pret depuis la fiche : traitement, puis suivi dans Mes prets.
        FIC -. "demander un pret (traitement)" .-> MES
        %% Annuler une demande en attente : traitement, on reste sur Mes prets.
        MES -. "annuler une demande (traitement)" .-> MES
    end

    subgraph GES["Profil gestionnaire"]
        direction TB
        TDB["06 - Tableau de bord"]
        DEM["07 - Demandes en attente"]
        RET["08 - Retours"]
        CAL["09 - Calendrier"]
        INV["10 - Liste d'inventaire"]
        FRM["11 - Formulaire de creation"]
        TDB --- DEM
        DEM --- RET
        RET --- CAL
        CAL --- INV
        %% Valider ou refuser : traitement, retour a la liste des demandes.
        DEM -. "valider / refuser (traitement)" .-> DEM
        %% Enregistrer un retour : traitement, retour a la liste des prets en cours.
        RET -. "enregistrer le retour (traitement)" .-> RET
        INV --> FRM
        %% Enregistrer la creation : traitement, retour a la liste d'inventaire.
        FRM -. "enregistrer (traitement)" .-> INV
    end

    subgraph ADM["Profil super-administrateur"]
        direction TB
        CPT["12 - Gestion des comptes"]
        JRN["13 - Journal d'administration"]
        CPT --- JRN
        %% Creer, modifier le role, activer ou desactiver : traitement, retour a la liste.
        CPT -. "creer / modifier / (des)activer (traitement)" .-> CPT
    end

    %% Points d'arrivee apres authentification, selon le profil.
    AIG --> CAT
    AIG --> TDB
    AIG --> CPT

    %% Note : les profils sont cumulatifs (le gestionnaire accede aussi au catalogue ;
    %% le super-administrateur cumule les acces du gestionnaire).
```

*Source versionnée à côté : [`enchainement.mermaid`](enchainement.mermaid).*
