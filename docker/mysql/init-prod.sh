#!/bin/bash
# Init MySQL prod : 1 instance, 2 bases isolees (preprod + prod) + 2 users aux privileges limites a
# LEUR base. Execute UNE FOIS par l'entrypoint MySQL au premier demarrage (monte dans
# /docker-entrypoint-initdb.d/). Script VERSIONNE : AUCUN secret en dur -- les mots de passe viennent
# de l'environnement du conteneur db (cf. .env.deploy.local).
set -e

mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<-SQL
	CREATE DATABASE IF NOT EXISTS creapret_preprod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
	CREATE DATABASE IF NOT EXISTS creapret_prod     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
	CREATE USER IF NOT EXISTS 'creapret_preprod'@'%' IDENTIFIED BY '${MYSQL_PREPROD_PASSWORD}';
	CREATE USER IF NOT EXISTS 'creapret_prod'@'%'     IDENTIFIED BY '${MYSQL_PROD_PASSWORD}';
	GRANT ALL PRIVILEGES ON creapret_preprod.* TO 'creapret_preprod'@'%';
	GRANT ALL PRIVILEGES ON creapret_prod.*     TO 'creapret_prod'@'%';
	FLUSH PRIVILEGES;
SQL
