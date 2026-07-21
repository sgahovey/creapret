-- ---------------------------------------------------------------------------
-- Base de test locale (environnement de developpement)
--
-- Pourquoi ce fichier : l'image MySQL n'accorde de privileges qu'a la base
-- nommee dans MYSQL_DATABASE (ici `creapret`). Or sous APP_ENV=test, Doctrine
-- applique dbname_suffix '_test%env(default::TEST_TOKEN)%'
-- (config/packages/doctrine.yaml) et cible donc `creapret_test`, qui n'existe
-- pas et sur laquelle l'utilisateur applicatif n'a aucun droit.
-- Ce script est la SOURCE DE VERITE UNIQUE : il est joue automatiquement a
-- l'initialisation d'un volume vierge (montage sur /docker-entrypoint-initdb.d)
-- et rejoue a la demande par scripts/init-test-db.sh sur un conteneur deja
-- demarre. En CI, l'equivalent est obtenu par MYSQL_DATABASE: creapret_test
-- declare dans le service MySQL de .github/workflows/ci.yml.
-- ---------------------------------------------------------------------------

-- 1. Creation de la base de test. IF NOT EXISTS rend le script rejouable sans
--    effet de bord. Jeu de caracteres et collation identiques a la base de
--    developpement, pour que les tests reproduisent le comportement reel
--    (tris, comparaisons, accents).
CREATE DATABASE IF NOT EXISTS `creapret_test`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Privileges de l'utilisateur applicatif sur la base de test.
--    L'underscore est ECHAPPE (`creapret\_test%`) : dans un motif de privilege,
--    « _ » est un joker d'un caractere, il faut donc le neutraliser pour ne pas
--    accorder de droits sur des bases non voulues (creapretXtest...).
--    Le « % » final couvre les bases suffixees par TEST_TOKEN lors d'une
--    execution parallelisee (creapret_test1, creapret_test2, ...).
GRANT ALL PRIVILEGES ON `creapret\_test%`.* TO 'creapret'@'%';

-- 3. Autorise la creation de declencheurs et de procedures par un utilisateur
--    non-SUPER lorsque la journalisation binaire est active. Requis par le
--    declencheur d'audit de l'US-5.2 (sinon erreur MySQL 1419) lorsque les
--    migrations sont jouees sur la base de test.
SET GLOBAL log_bin_trust_function_creators = 1;

-- 4. Prise en compte immediate des privileges accordes ci-dessus.
FLUSH PRIVILEGES;
