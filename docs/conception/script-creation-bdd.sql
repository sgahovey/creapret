-- =====================================================================
-- CréaPrêt — Script de création de la base de données
--
-- 7 tables métier : utilisateur, categorie, materiel, exemplaire, pret,
--                   journal_admin, historique_utilisateur
-- + déclencheur d'audit (trg_historique_utilisateur)
-- + procédure de consultation (consulter_historique_utilisateur)
--
-- État FINAL du schéma, reconstitué à partir des migrations successives
-- appliquées cumulativement, puis consolidé en une création unique et
-- lisible. Fidèle aux migrations réelles (types et contraintes inclus).
--
-- Cible  : MySQL 8 / InnoDB / utf8mb4.
--          (Le niveau physique et ce script relèvent nécessairement d'un
--           système de gestion de base de données : exception admise à la
--           règle « conception sans nommer de produit ».)
-- Ordre  : tables référencées d'abord (dépendances de clés étrangères),
--          afin d'exécuter le script d'un seul bloc.
-- Exclu  : messenger_messages — table technique de file d'attente des
--          traitements différés, hors modélisation métier.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;


-- ---------------------------------------------------------------------
-- 1. utilisateur — comptes et rôles. Aucune dépendance sortante.
--    Unicité de l'email (identifiant de connexion).
--    role : énuméré applicatif stocké en texte. est_actif/email_rappel :
--    booléens. Minimisation RGPD : ni téléphone ni adresse.
-- ---------------------------------------------------------------------
CREATE TABLE utilisateur (
    id                INT          NOT NULL AUTO_INCREMENT,
    email             VARCHAR(180) NOT NULL,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    nom               VARCHAR(100) NOT NULL,
    prenom            VARCHAR(100) NOT NULL,
    role              VARCHAR(30)  NOT NULL,
    est_actif         TINYINT      NOT NULL,
    email_rappel      TINYINT      NOT NULL,
    date_creation     DATETIME     NOT NULL,
    date_consentement DATETIME     DEFAULT NULL,
    version_cgu       VARCHAR(10)  DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX uniq_utilisateur_email (email)
) DEFAULT CHARACTER SET utf8mb4;


-- ---------------------------------------------------------------------
-- 2. categorie — classe les matériels. Aucune dépendance.
-- ---------------------------------------------------------------------
CREATE TABLE categorie (
    id          INT          NOT NULL AUTO_INCREMENT,
    nom         VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4;


-- ---------------------------------------------------------------------
-- 3. materiel — modèle de matériel. Dépend de : categorie.
--    Suppression d'une catégorie REFUSÉE tant qu'un matériel la référence
--    (ON DELETE RESTRICT) : on préserve l'intégrité plutôt que propager.
-- ---------------------------------------------------------------------
CREATE TABLE materiel (
    id           INT          NOT NULL AUTO_INCREMENT,
    nom          VARCHAR(150) NOT NULL,
    description  LONGTEXT     DEFAULT NULL,
    marque       VARCHAR(100) DEFAULT NULL,
    modele       VARCHAR(100) DEFAULT NULL,
    reference    VARCHAR(100) DEFAULT NULL,
    id_categorie INT          NOT NULL,
    PRIMARY KEY (id),
    INDEX IDX_18D2B091C9486A13 (id_categorie),
    CONSTRAINT FK_18D2B091C9486A13
        FOREIGN KEY (id_categorie) REFERENCES categorie (id) ON DELETE RESTRICT
) DEFAULT CHARACTER SET utf8mb4;


-- ---------------------------------------------------------------------
-- 4. exemplaire — l'unité réellement prêtée. Dépend de : materiel.
--    Unicité du numéro d'inventaire. etat : énuméré stocké en texte.
--    Index composite (id_materiel, etat) : disponibilité par matériel.
--    Suppression d'un matériel REFUSÉE tant qu'un exemplaire le référence.
-- ---------------------------------------------------------------------
CREATE TABLE exemplaire (
    id                INT         NOT NULL AUTO_INCREMENT,
    numero_inventaire VARCHAR(50) NOT NULL,
    etat              VARCHAR(20) NOT NULL,
    id_materiel       INT         NOT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX uniq_exemplaire_numero (numero_inventaire),
    INDEX IDX_5EF83C9248095C04 (id_materiel),
    INDEX idx_exemplaire_materiel_etat (id_materiel, etat),
    CONSTRAINT FK_5EF83C9248095C04
        FOREIGN KEY (id_materiel) REFERENCES materiel (id) ON DELETE RESTRICT
) DEFAULT CHARACTER SET utf8mb4;


-- ---------------------------------------------------------------------
-- 5. pret — événement de prêt. Dépend de : exemplaire, utilisateur (x2).
--    statut : énuméré stocké en texte. Emprunteur obligatoire (suppression
--    REFUSÉE) ; validateur facultatif (ON DELETE SET NULL : le prêt
--    survit à la suppression du gestionnaire, le lien se dénoue).
--    Index composite (id_exemplaire, statut, date_debut, date_fin) :
--    contrôle de non-chevauchement et de disponibilité (RG-1 / RG-4).
-- ---------------------------------------------------------------------
CREATE TABLE pret (
    id                        INT          NOT NULL AUTO_INCREMENT,
    date_debut                DATETIME     NOT NULL,
    date_fin                  DATETIME     NOT NULL,
    statut                    VARCHAR(20)  NOT NULL,
    motif_refus               VARCHAR(255) DEFAULT NULL,
    date_demande              DATETIME     NOT NULL,
    date_validation           DATETIME     DEFAULT NULL,
    date_retour               DATETIME     DEFAULT NULL,
    rappel_echeance_envoye_at DATETIME     DEFAULT NULL,
    retard_notifie_at         DATETIME     DEFAULT NULL,
    id_exemplaire             INT          NOT NULL,
    id_emprunteur             INT          NOT NULL,
    id_validateur             INT          DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX IDX_52ECE979FB72BC45 (id_exemplaire),
    INDEX IDX_52ECE9791E108449 (id_validateur),
    INDEX idx_pret_dispo (id_exemplaire, statut, date_debut, date_fin),
    INDEX idx_pret_emprunteur (id_emprunteur),
    CONSTRAINT FK_52ECE979FB72BC45
        FOREIGN KEY (id_exemplaire) REFERENCES exemplaire (id) ON DELETE RESTRICT,
    CONSTRAINT FK_52ECE97930AAE709
        FOREIGN KEY (id_emprunteur) REFERENCES utilisateur (id) ON DELETE RESTRICT,
    CONSTRAINT FK_52ECE9791E108449
        FOREIGN KEY (id_validateur) REFERENCES utilisateur (id) ON DELETE SET NULL
) DEFAULT CHARACTER SET utf8mb4;


-- ---------------------------------------------------------------------
-- 6. journal_admin — trace des décisions de gestion (append-only).
--    AUCUNE clé étrangère volontairement : acteur et cible sont figés en
--    libellés pour survivre à la suppression des comptes (RGPD).
-- ---------------------------------------------------------------------
CREATE TABLE journal_admin (
    id             INT          NOT NULL AUTO_INCREMENT,
    date_action    DATETIME     NOT NULL,
    type_action    VARCHAR(40)  NOT NULL,
    acteur_id      INT          NOT NULL,
    acteur_libelle VARCHAR(201) NOT NULL,
    cible_id       INT          DEFAULT NULL,
    cible_libelle  VARCHAR(201) DEFAULT NULL,
    details        LONGTEXT     DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_journal_admin_date (date_action)
) DEFAULT CHARACTER SET utf8mb4;


-- ---------------------------------------------------------------------
-- 7. historique_utilisateur — audit alimenté par déclencheur (voir infra).
--    Dépend de : utilisateur. ON DELETE CASCADE : l'audit d'un compte
--    disparaît avec le compte (c'est l'historique DE ce compte).
-- ---------------------------------------------------------------------
CREATE TABLE historique_utilisateur (
    id                INT          NOT NULL AUTO_INCREMENT,
    utilisateur_id    INT          NOT NULL,
    champ_modifie     VARCHAR(50)  NOT NULL,
    ancienne_valeur   VARCHAR(255) DEFAULT NULL,
    nouvelle_valeur   VARCHAR(255) DEFAULT NULL,
    date_modification DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_historique_utilisateur (utilisateur_id, date_modification),
    CONSTRAINT fk_historique_utilisateur_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;


-- ---------------------------------------------------------------------
-- Déclencheur d'audit : une ligne d'historique par champ sensible
-- réellement modifié (rôle, activation). Placé DANS la base : l'audit
-- est indépendant du chemin d'écriture applicatif, donc inviolable.
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_historique_utilisateur
AFTER UPDATE ON utilisateur
FOR EACH ROW
BEGIN
    IF NOT (OLD.role <=> NEW.role) THEN
        INSERT INTO historique_utilisateur (utilisateur_id, champ_modifie, ancienne_valeur, nouvelle_valeur)
        VALUES (NEW.id, 'role', OLD.role, NEW.role);
    END IF;
    IF NOT (OLD.est_actif <=> NEW.est_actif) THEN
        INSERT INTO historique_utilisateur (utilisateur_id, champ_modifie, ancienne_valeur, nouvelle_valeur)
        VALUES (NEW.id, 'est_actif', CAST(OLD.est_actif AS CHAR), CAST(NEW.est_actif AS CHAR));
    END IF;
END;


-- ---------------------------------------------------------------------
-- Procédure de consultation de l'historique d'un compte (RGPD, CP8).
-- ---------------------------------------------------------------------
CREATE PROCEDURE consulter_historique_utilisateur(IN p_utilisateur_id INT)
BEGIN
    SELECT champ_modifie, ancienne_valeur, nouvelle_valeur, date_modification
    FROM historique_utilisateur
    WHERE utilisateur_id = p_utilisateur_id
    ORDER BY date_modification DESC;
END;
