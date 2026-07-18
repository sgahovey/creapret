# Conception — Démarche suivie

> Pour un lecteur qui veut comprendre **comment cette conception a été menée** : quels artefacts elle
> produit, dans quel ordre, et pourquoi cet enchaînement. Court par choix.

## Une démarche qui va du besoin vers le moyen

La conception progresse **du problème vers la solution**, jamais l'inverse. Chaque artefact prépare
le suivant :

| Ordre | Artefact | Question à laquelle il répond |
|---|---|---|
| 1 | **Besoins découpés en incréments** | Que doit permettre l'application, livrable par livrable ? |
| 2 | **Analyse fonctionnelle** (acteurs, cas d'utilisation, règles) | Qui poursuit quels objectifs, et sous quelles règles ? |
| 3 | **Diagramme de classes** | De quels concepts le domaine est-il fait ? |
| 4 | **Modèle de données** | Comment ces concepts sont-ils structurés et conservés ? |
| 5 | **Architecture en couches** | Comment l'application est-elle organisée à l'intérieur ? |
| 6 | **Maquettes** | À quoi ressemblent les écrans, en structure ? |
| 7 | **Conception de la mise à disposition, de la surveillance, des traitements** | Comment le service sera-t-il exploité ? |

**Pourquoi cet ordre.** On ne peut pas modéliser des concepts (3) avant d'avoir identifié les
objectifs métier qui les font apparaître (2) ; on ne peut pas décider d'une structure de données (4)
avant de connaître les concepts (3) ; l'organisation interne (5) découle des responsabilités mises au
jour par les étapes précédentes. Chaque étape **contraint** la suivante et **s'appuie** sur la
précédente.

## Le principe des deux niveaux

Toute cette documentation applique une **séparation stricte** :

- La **conception** énonce des **besoins** et des **arbitrages**, **sans préjuger des moyens**. Elle
  dit *ce qu'il faut obtenir* et *pourquoi telle orientation*, jamais *avec quel produit*.
- La **réalisation** décrit **ce qui a été fait**, avec les noms réels, et **signale les écarts** par
  rapport à l'intention initiale.

**Ce que cette séparation apporte.**

- Les **choix restent lisibles** indépendamment des outils : un besoin correctement énoncé survit à un
  changement de technologie.
- Elle rend la **revue** possible à deux niveaux : on peut juger la pertinence d'un besoin sans se
  perdre dans sa mise en œuvre, et vérifier une réalisation à l'aune de son intention.
- Elle **oblige à l'honnêteté** : tout **écart** entre ce qui était visé et ce qui a été fait est
  nommé, pas dissimulé.

**Ce qu'elle coûte.** Un **double effort de rédaction** (chaque sujet est écrit deux fois, sous deux
angles), la **discipline** de ne nommer aucun outil du côté conception, et un **travail de cohérence**
pour que les deux niveaux ne divergent pas. Ce coût est assumé : il achète la clarté et la
traçabilité.

## Un développement itératif

Les besoins n'ont pas été traités d'un bloc mais **découpés en incréments livrables**. Chaque
incrément apporte une **fonctionnalité utilisable** de bout en bout — par exemple « demander un prêt »,
puis « décider d'une demande », puis « enregistrer un retour » — plutôt qu'une couche technique isolée
sans valeur pour l'utilisateur. Cet enchaînement permet de **valider tôt** et **souvent**, et de
n'ajouter la complexité que lorsqu'elle sert un usage réel.
