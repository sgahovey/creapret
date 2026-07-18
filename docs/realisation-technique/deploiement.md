# Réalisation — Mise à disposition de l'application

> Pour un lecteur technique. **Conception correspondante** : `../conception/deploiement.md`. Ce document
> synthétise **ce qui a été mis en place** ; il ne recopie pas les documents détaillés.

## Ce qui a été mis en place

- **Pile séparée** (`compose.prod.yml`, projet `creapret_prod`) qui **rejoint le réseau du proxy
  existant** (déclaré *external* : `creaslot_prod_creaslot-prod-net`). Le proxy **Caddy** appartient à
  l'autre application (CreaSlot) et reste l'**unique point d'entrée**.
- **Deux environnements** : services applicatifs `creapret-app-preprod` (accès restreint par
  `basic_auth`) et `creapret-app-prod` (public), chacun avec son **consommateur** de messages
  (`creapret-worker-*`).
- **Données séparées** : une base dédiée `db` hébergeant `creapret_preprod` et `creapret_prod`, sur le
  **réseau interne seul** (jamais exposée).
- **Livraison sans coupure et retour arrière** : image *build-once* étiquetée par l'empreinte du commit
  (`ghcr.io/sgahovey/creapret:<sha>`), déployée par un pipeline GitHub Actions (préproduction
  automatique, production sur décision explicite avec approbation). Un retour arrière consiste à
  **redéployer une empreinte antérieure**.

## Renvois (détail non recopié ici)

- **Choix d'architecture et justifications** : `../architecture-deploiement.md`.
- **Procédures d'exploitation** (premier déploiement, mise à jour, secours, rollback) :
  `../runbook-deploiement.md`.

## Schéma

Le même agencement que la conception, cette fois avec les composants nommés.

```mermaid
%% Schema de realisation -- Deploiement (composants nommes).
flowchart TB
    U["Navigateur"]
    CADDY["Caddy (pile CreaSlot) -- reverse-proxy<br/>TLS, en-tetes, basic_auth"]
    subgraph CP["Pile CreaPret (projet creapret_prod)"]
        APRE["creapret-app-preprod"]
        APRO["creapret-app-prod"]
        WPRE["creapret-worker-preprod"]
        WPRO["creapret-worker-prod"]
        DB[("MySQL -- creapret_preprod / creapret_prod")]
    end
    U -->|"HTTPS"| CADDY
    CADDY -->|"preprod.domaine (basic_auth)"| APRE
    CADDY -->|"prod.domaine (public)"| APRO
    APRE --- DB
    APRO --- DB
    WPRE --- DB
    WPRO --- DB
```


## Diagramme de déploiement (UML)

Vue **normalisée** (notation UML de déploiement), cette fois avec les **composants nommés** : nœuds,
artefacts et relations orientées. Source versionnée : [`deploiement-uml.puml`](deploiement-uml.puml).

```plantuml
@startuml deploiement-realisation
' =====================================================================
' CreaPret — Diagramme de deploiement (REALISATION)
' Noms reels des composants. Notation UML de deploiement.
' =====================================================================
skinparam shadowing false
title Diagramme de deploiement (realisation) — composants nommes

node "Poste de l'utilisateur" as USER {
  artifact "Navigateur" as NAV
}

node "Serveur hote (VPS, mutualise avec CreaSlot)" as HOST {

  node "Caddy — reverse-proxy\n(pile CreaSlot, unique point d'entree)" as CADDY

  node "Environnement de preproduction" as VAL {
    artifact "creapret-app-preprod" as APPVAL
    artifact "creapret-worker-preprod" as WVAL
  }

  node "Environnement de production" as SVC {
    artifact "creapret-app-prod" as APPSVC
    artifact "creapret-worker-prod" as WSVC
  }

  database "MySQL 8\ncreapret_preprod / creapret_prod" as DATA

  node "Uptime Kuma — supervision" as MON
}

NAV --> CADDY : <<HTTPS>>
CADDY --> APPVAL : <<preprod, basic_auth>>
CADDY --> APPSVC : <<prod, public>>
APPVAL --> DATA : <<SQL>>
APPSVC --> DATA : <<SQL>>
WVAL --> DATA : <<SQL>>
WSVC --> DATA : <<SQL>>
MON ..> APPSVC : <<healthcheck>>
MON ..> APPVAL : <<healthcheck>>

legend right
  | **Symbole** | **Signification** |
  | Noeud (node) | Conteneur / hote d'execution |
  | Artefact (artifact) | Service applicatif deploye |
  | Base (database) | Base MySQL (deux schemas cloisonnes) |
  | Noeud imbrique | Environnement (preprod / prod) |
  | Fleche pleine (-->) | Flux applicatif / dependance |
  | Fleche pointillee (..>) | Surveillance (sans couplage) |
  | <<stereotype>> | Protocole / nature du flux |
endlegend

@enduml
```

**Légende** (également portée par le diagramme) :

| Symbole | Signification |
|---|---|
| Nœud (boîte) | Conteneur / hôte d'exécution |
| Artefact | Service applicatif déployé |
| Base (cylindre) | Base MySQL (deux schémas cloisonnés) |
| Nœud imbriqué | Environnement (préprod / prod) |
| Flèche pleine (→) | Flux applicatif / dépendance |
| Flèche pointillée (⇢) | Surveillance (sans couplage) |
| «stéréotype» | Protocole / nature du flux |

## Écarts par rapport à la conception

- L'option « seconde porte devant les deux » (proxy « chapeau ») a été **écartée** au profit de la
  porte commune existante — le point d'entrée est donc **le proxy de l'autre application**.
- Conséquence concrète : les **règles d'aiguillage** vers CréaPrêt (blocs de site) **vivent dans le
  dépôt de l'autre application**, et non ici.
