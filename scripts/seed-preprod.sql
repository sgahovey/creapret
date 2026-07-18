-- =============================================================================
-- CreaPret — Seed SQL de preproduction
-- =============================================================================
-- OBJET
--   Peuple la base de PREPRODUCTION avec un jeu de demonstration. L'image de
--   prod/preprod est construite avec `composer install --no-dev` : le bundle
--   doctrine-fixtures n'y est PAS present, on ne peut donc pas lancer
--   `doctrine:fixtures:load`. Ce fichier est l'equivalent SQL, execute
--   directement sur MySQL :
--     docker compose -f compose.prod.yml --env-file .env.deploy.local \
--       exec -T db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" creapret_preprod < scripts/seed-preprod.sql
--
-- DONNEES FICTIVES
--   Tous les comptes ont le meme mot de passe connu « Motdepasse123! » (hash
--   argon2id ci-dessous), DONT UN SUPER-ADMINISTRATEUR. Applique par erreur en
--   PRODUCTION, ce script ouvrirait un acces complet a quiconque connait le jeu
--   de demonstration -> d'ou le GARDE-FOU ci-dessous qui ECHOUE reellement (SIGNAL)
--   si la base courante n'est pas creapret_preprod.
--
-- IDEMPOTENCE
--   Rejouable sans doublon : on purge les tables (ordre inverse des FK, controles
--   FK desactives) puis on reinsere avec des identifiants explicites deterministes.
--   Le tout dans une transaction (tout ou rien). Dates relatives a NOW() pour que
--   le pret « en cours » et le pret « en retard » le restent a chaque execution.
-- =============================================================================

SET NAMES utf8mb4;

-- --- GARDE-FOU : refuse de s'executer hors de creapret_preprod --------------
-- SIGNAL n'est utilisable que dans un programme stocke : on cree une procedure
-- jetable, on l'appelle (elle leve une erreur 45000 si mauvaise base -> le client
-- mysql en mode batch s'arrete AVANT toute suppression/insertion), puis on la supprime.
-- Comparaison null-safe (<=>) pour couvrir aussi le cas « aucune base selectionnee ».
DELIMITER //
DROP PROCEDURE IF EXISTS _garde_preprod //
CREATE PROCEDURE _garde_preprod()
BEGIN
    IF NOT (DATABASE() <=> 'creapret_preprod') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'REFUS: seed-preprod.sql ne doit s executer QUE sur la base creapret_preprod.';
    END IF;
END //
DELIMITER ;
CALL _garde_preprod();
DROP PROCEDURE _garde_preprod;

-- --- Purge idempotente (ordre inverse des FK) -------------------------------
START TRANSACTION;
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM pret;
DELETE FROM exemplaire;
DELETE FROM materiel;
DELETE FROM categorie;
DELETE FROM historique_utilisateur;
DELETE FROM journal_admin;
DELETE FROM utilisateur;
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- 1. UTILISATEURS — un compte par role. Mot de passe « Motdepasse123! ».
--    L'emprunteur (auto-inscrit) porte une preuve de consentement ; le
--    gestionnaire et le super-admin (crees par un tiers) n'en ont pas (NULL).
-- =============================================================================
INSERT INTO utilisateur
    (id, email, mot_de_passe_hash, nom, prenom, role, est_actif, email_rappel, date_creation, date_consentement, version_cgu)
VALUES
    (1, 'emprunteur.demo@creapret.local',   '$argon2id$v=19$m=65536,t=4,p=1$x31K+h6WE4+OUSLrmuEalg$0gXRednFODGM0gE+kcRFTZRkfyFd2RNuCqjHUxRJzFw', 'Etudiant', 'Emma',    'emprunteur',  1, 1, NOW(), NOW(), 'v1.0'),
    (2, 'gestionnaire.demo@creapret.local', '$argon2id$v=19$m=65536,t=4,p=1$x31K+h6WE4+OUSLrmuEalg$0gXRednFODGM0gE+kcRFTZRkfyFd2RNuCqjHUxRJzFw', 'Gestion',  'Gabriel', 'gestionnaire', 1, 1, NOW(), NULL, NULL),
    (3, 'admin.demo@creapret.local',        '$argon2id$v=19$m=65536,t=4,p=1$x31K+h6WE4+OUSLrmuEalg$0gXRednFODGM0gE+kcRFTZRkfyFd2RNuCqjHUxRJzFw', 'Admin',    'Sacha',   'super_admin',  1, 1, NOW(), NULL, NULL);

-- =============================================================================
-- 2. CATEGORIES
-- =============================================================================
INSERT INTO categorie (id, nom, description) VALUES
    (1, 'Informatique', 'Ordinateurs, peripheriques et accessoires'),
    (2, 'Audiovisuel',  'Video-projecteurs, cameras et micros'),
    (3, 'Mesure',       'Instruments de mesure et de test');

-- =============================================================================
-- 3. MATERIELS (rattaches a une categorie)
-- =============================================================================
INSERT INTO materiel (id, nom, description, marque, modele, reference, id_categorie) VALUES
    (1, 'Ordinateur portable', 'PC portable 14 pouces pour prets etudiants', 'Dell',  'Latitude 5440', 'REF-PC-001', 1),
    (2, 'Video-projecteur',    'Projecteur Full HD pour salles de cours',    'Epson', 'EB-2250U',      'REF-VP-001', 2),
    (3, 'Multimetre',          'Multimetre numerique de laboratoire',        'Fluke', '117',           'REF-MM-001', 3);

-- =============================================================================
-- 4. EXEMPLAIRES — etats varies (dont maintenance, hors service, perdu).
--    Les exemplaires 1 et 4 sont « prete » : ils portent les prets actifs.
-- =============================================================================
INSERT INTO exemplaire (id, numero_inventaire, etat, id_materiel) VALUES
    (1, 'INV-PC-001', 'prete',          1),
    (2, 'INV-PC-002', 'disponible',     1),
    (3, 'INV-PC-003', 'en_maintenance', 1),
    (4, 'INV-VP-001', 'prete',          2),
    (5, 'INV-VP-002', 'disponible',     2),
    (6, 'INV-VP-003', 'hors_service',   2),
    (7, 'INV-MM-001', 'disponible',     3),
    (8, 'INV-MM-002', 'perdu',          3);

-- =============================================================================
-- 5. PRETS — couvrent tous les statuts, dont un EN COURS et un EN RETARD, pour
--    donner de la matiere au tableau de bord et a la page des retours.
--    Colonnes : date_debut, date_fin, statut, motif_refus, date_demande,
--    date_validation, date_retour, rappel_echeance_envoye_at, retard_notifie_at,
--    id_exemplaire, id_emprunteur, id_validateur.
-- =============================================================================
INSERT INTO pret
    (id, date_debut, date_fin, statut, motif_refus, date_demande, date_validation, date_retour, rappel_echeance_envoye_at, retard_notifie_at, id_exemplaire, id_emprunteur, id_validateur)
VALUES
    -- P1 : EN COURS (valide, echeance future, non rendu) -> page retours, pas en retard.
    (1, DATE_SUB(NOW(), INTERVAL 2 DAY),  DATE_ADD(NOW(), INTERVAL 5 DAY),  'valide',  NULL,
        DATE_SUB(NOW(), INTERVAL 3 DAY),  DATE_SUB(NOW(), INTERVAL 2 DAY),  NULL, NULL, NULL, 1, 1, 2),
    -- P2 : EN RETARD (valide, echeance DEPASSEE, non rendu) -> badge « en retard » + KPI.
    (2, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY),  'valide',  NULL,
        DATE_SUB(NOW(), INTERVAL 11 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), NULL, NULL, NULL, 4, 1, 2),
    -- P3 : RETOURNE (rendu) -> exemplaire redevenu disponible (id 2).
    (3, DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 13 DAY), 'retourne', NULL,
        DATE_SUB(NOW(), INTERVAL 21 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY), NULL, NULL, 2, 1, 2),
    -- P4 : REFUSE (motif renseigne).
    (4, DATE_ADD(NOW(), INTERVAL 3 DAY),  DATE_ADD(NOW(), INTERVAL 7 DAY),  'refuse',  'Exemplaire indisponible sur la periode demandee.',
        DATE_SUB(NOW(), INTERVAL 1 DAY),  NOW(),                            NULL, NULL, NULL, 5, 1, 2),
    -- P5 : DEMANDE (en attente de validation) -> page « demandes en attente ».
    (5, DATE_ADD(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 14 DAY), 'demande', NULL,
        NOW(),                            NULL,                             NULL, NULL, NULL, 7, 1, NULL),
    -- P6 : ANNULE (annulation en self-service par l'emprunteur).
    (6, DATE_ADD(NOW(), INTERVAL 20 DAY), DATE_ADD(NOW(), INTERVAL 24 DAY), 'annule',  NULL,
        DATE_SUB(NOW(), INTERVAL 5 DAY),  NULL,                             NULL, NULL, NULL, 7, 1, NULL);

COMMIT;

-- =============================================================================
-- Fin du seed. Comptes de demo (mot de passe « Motdepasse123! ») :
--   Emprunteur          : emprunteur.demo@creapret.local
--   Gestionnaire        : gestionnaire.demo@creapret.local
--   Super-administrateur : admin.demo@creapret.local
-- =============================================================================
