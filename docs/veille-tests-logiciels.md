# Veille sur les tests logiciels — CréaPrêt

Ce document répond au critère d'évaluation « *le plan de tests tient compte des évolutions
technologiques et des problèmes de sécurité liés aux tests logiciels* ». Il décrit le **dispositif de
veille réellement en place**, les **sujets suivis** et, pour chacun, la **décision prise sur CréaPrêt**
— avec le fichier du dépôt qui en porte la trace.

Principe de rédaction : **aucune source n'est citée qui ne soit vérifiable dans le dispositif**, et
tout fait manquant est signalé « **à compléter** » plutôt que comblé par une approximation.

---

## 1. Dispositif de veille

La veille n'est pas manuelle : elle est **outillée par un bot dédié** (`veille-bot`, projet Python
distinct, architecture hexagonale), qui automatise la collecte et la restitution.

| Élément | Réalité constatée |
|---|---|
| **Collecte** | Agrégation de **15 flux RSS/Atom** (`src/sources.py`), techniques et sécurité |
| **Sélection** | Filtrage et hiérarchisation automatisés des articles pertinents pour un développeur en alternance |
| **Restitution quotidienne** | **Digest structuré publié chaque matin à 7 h (heure de La Réunion, UTC+4)** sur un canal **Discord**, via webhook |
| **Restitution hebdomadaire** | **Récapitulatif** publié le dimanche soir (`src/recap_main.py`) |
| **Persistance** | Base **SQLite** (`src/infrastructure/sqlite_repository.py`), utilisée pour la **déduplication** des articles déjà vus d'un jour sur l'autre |
| **Archive consultable des digests** | *À compléter* — la persistance SQLite sert la déduplication ; l'existence d'une archive **navigable** des digests passés n'est pas établie |

**Cadence de consultation** : quotidienne (digest du matin), avec une relecture de synthèse
hebdomadaire (récapitulatif du dimanche).

### Sources pertinentes pour les tests et leur sécurité

Extraites des 15 flux réellement configurés :

| Source | Flux | Apport pour les tests |
|---|---|---|
| **Symfony (blog)** | `feeds.feedburner.com/symfony/blog` | Évolutions du framework et de ses outils de test (`WebTestCase`, `KernelTestCase`) |
| **PHP (releases)** | `www.php.net/feed.atom` | Versions du langage, dépréciations affectant le code de test |
| **PHP.Watch** | `php.watch/feed.atom` | Analyse détaillée des changements de PHP, dépréciations et RFC |
| **OWASP** | `owasp.org/feed.xml` | Référentiel des risques applicatifs — alimente les tests de sécurité (§7 du plan) |
| **CERT-FR (ANSSI)** | `www.cert.ssi.gouv.fr/feed/` | Alertes et avis de sécurité officiels |
| **NVD (CVE)** | `nvd.nist.gov/feeds/xml/cve/misc/nvd-rss.xml` | Vulnérabilités publiées, dont celles des dépendances |
| **Snyk** | `snyk.io/blog/feed/` | Sécurité des dépendances et de la chaîne de build |

> **Précision de périmètre, pour ne pas surestimer le dispositif** : les flux **« releases PHPUnit »**
> et **« GitHub Security Advisories »** ne figurent **pas** parmi les 15 sources configurées. Le suivi
> des versions de PHPUnit passe aujourd'hui par les canaux PHP/Symfony et par `composer outdated` ; la
> base d'avis de sécurité GitHub/Packagist est, elle, interrogée par `composer audit` (§2.2) — pas par
> un flux RSS.

---

## 2. Sujets suivis et impact sur CréaPrêt

### 2.1 PHPUnit 13 — format de configuration et politique de dépréciation

**Ce qui a évolué.** PHPUnit a poursuivi le durcissement engagé avec ses versions récentes : le
**schéma de configuration** a évolué (bloc `<source>` remplaçant l'ancienne déclaration de couverture,
déclaration explicite des déclencheurs de dépréciation) et la **politique de dépréciation** est devenue
opposable — une dépréciation n'est plus un message informatif que l'on peut ignorer durablement, elle
annonce une rupture programmée.

**Source.** Flux PHP / PHP.Watch / Symfony du dispositif (§1).

**Impact concret sur CréaPrêt.** Le projet tourne sur **PHPUnit 13.2.2** (version relevée dans
`composer.lock` le 20/07/2026). Laisser passer les dépréciations aurait signifié accumuler une dette
invisible, découverte en bloc lors d'une montée de version majeure.

**Décision prise.** Traiter toute alerte comme un **échec**, et non comme un avertissement. C'est
effectivement en place dans `phpunit.dist.xml` :

```xml
failOnDeprecation="true"
failOnNotice="true"
failOnWarning="true"
```

complété par `ignoreSuppressionOfDeprecations="true"`, `restrictNotices="true"`,
`restrictWarnings="true"` et une déclaration explicite des `<deprecationTrigger>` (dont
`Doctrine\Deprecations\Deprecation::trigger` et `trigger_deprecation`). **Conséquence opérationnelle :
une simple *notice* fait échouer la suite**, et donc bloque la fusion. La règle est reprise comme
critère de sortie au §3bis.2 du plan de tests, et l'exigence de franchise (« *OK, but there were
issues* » = échec) au §8.

---

### 2.2 Sécurité des dépendances — `composer audit`

**Ce qui a évolué.** L'audit des dépendances est devenu une commande **native de Composer**
(`composer audit`), adossée à la base d'avis de sécurité Packagist/GitHub : plus besoin d'outil tiers
pour confronter `composer.lock` aux vulnérabilités publiées.

**Source.** Flux sécurité du dispositif (CERT-FR, NVD, Snyk) pour les vulnérabilités elles-mêmes ;
la commande, elle, est fournie par Composer.

**Disponibilité et résultat — exécution réelle.** La commande **est disponible** dans le conteneur
applicatif. Exécutée le **20/07/2026** :

```
$ composer audit
No security vulnerability advisories found.
```

Code de sortie **0**. Aucune vulnérabilité connue sur les dépendances verrouillées à cette date.

**Impact concret sur CréaPrêt.** Une dépendance vulnérable est un risque qui ne se manifeste par
**aucun test fonctionnel** : la suite reste verte pendant que la faille existe. L'audit est donc le
seul contrôle qui couvre ce vecteur.

**Décision prise, et limite assumée.** L'audit est **exécuté manuellement** et son résultat consigné
ici. En revanche — fait vérifié — **`composer audit` n'est présent dans aucun des quatre workflows**
de `.github/workflows/` : il n'est **pas automatisé en intégration continue**. La conséquence est
directe : entre deux exécutions manuelles, une vulnérabilité publiée sur une dépendance existante
**ne serait pas signalée**. *Recommandation posée* : ajouter une étape `composer audit` au job
`phpunit` (ou un job dédié) de `ci.yml`, de sorte que la publication d'un avis fasse rougir la CI sans
attendre une vérification manuelle. **Non implémentée à ce jour.**

---

### 2.3 PHPStan niveau 8 sans *baseline* — typage strict et écriture des tests

**Ce qui a évolué.** L'analyse statique du PHP s'est déplacée du confort vers l'exigence : les niveaux
élevés de PHPStan traitent `null` comme un type à part entière et refusent les types approximatifs
(`mixed`, tableaux non décrits). La pratique de la *baseline* — geler les erreurs existantes pour ne
contrôler que le code neuf — s'est répandue.

**Source.** Flux PHP / PHP.Watch / Symfony du dispositif (§1).

**Impact concret sur CréaPrêt.** Le projet est en **PHPStan 2.2.4**, configuré en **niveau 8** et
**sans *baseline*** — fait vérifiable dans `phpstan.dist.neon` (`level: 8`, aucune directive
`baseline`), avec `paths: [src, tests]` : **les tests sont analysés au même niveau que le code de
production**. Ce n'est pas neutre sur l'écriture des tests :

- les valeurs susceptibles d'être `null` doivent être **explicitement gardées** avant usage — d'où les
  `self::assertNotNull($id)` systématiques avant de passer un identifiant à un worker, dans
  `ConcurrenceValidationTest` comme dans `ValidationConcurrenteChargeTest` ;
- les tableaux doivent être **décrits** (`@return array{resource, array<int, resource>}`, `list<Pret>`),
  ce qui documente le test autant que cela le type ;
- l'intégration Doctrine (`objectManagerLoader`) et Symfony (`containerXmlPath`) permet à PHPStan de
  typer finement les *repositories* et les services récupérés du conteneur de test.

**Décision prise.** **Conserver le niveau 8 sans *baseline***, y compris sur `tests/`. Le coût est réel
(chaque test doit être typé correctement) ; le bénéfice l'est aussi : un test mal typé est souvent un
test qui teste mal.

---

## 3. Traçabilité — d'une information de veille à une action

Une information de veille ne vaut que si elle atterrit quelque part. Le circuit appliqué :

1. **Réception** — digest quotidien Discord (§1).
2. **Qualification** — l'information concerne-t-elle une dépendance du projet, un outil de la chaîne
   de test, ou une pratique de sécurité applicable ?
3. **Aiguillage**, selon la nature :
   - **action immédiate** → mise à jour de configuration ou de dépendance, validée par la suite
     complète rejouée (cf. §3bis.1 du plan de tests, stratégie de non-régression) ;
   - **travail à planifier** → **carte Trello** de l'itération courante (itération, catégorie,
     difficulté Fibonacci, *definition of done*) ;
   - **compromis assumé** → entrée **DT-n** dans [`DETTE_TECHNIQUE.md`](DETTE_TECHNIQUE.md), avec
     constat, conséquence et **condition de levée**.
4. **Vérification** — lorsque le sujet est automatisable, un **test de non-régression** est ajouté à la
   suite, qui protège désormais contre la réapparition du problème.

**Exemple réel de bout en bout** : le durcissement de la politique de dépréciation (§2.1) s'est traduit
par une **action immédiate** — activation de `failOnDeprecation`/`failOnNotice`/`failOnWarning` dans
`phpunit.dist.xml` — dont l'effet est **vérifié en continu** par la CI, puisqu'une simple notice suffit
désormais à faire échouer la suite.

**Contre-exemple assumé** : l'absence de `composer audit` en intégration continue (§2.2) est un sujet
**identifié par la veille mais non encore traité** ; il est consigné comme tel dans le présent document
plutôt que présenté comme résolu.

---

*Veille sur les tests logiciels — CréaPrêt. Faits relevés le 20/07/2026 (versions issues de
`composer.lock`, résultat de `composer audit` issu d'une exécution réelle). Complète le
[plan de tests](plan-de-tests.md) §10.*
