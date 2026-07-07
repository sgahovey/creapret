<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * US-5.2 : audit des comptes au niveau base de donnees.
 *
 * Mecanisme complementaire a l'ORM : un trigger SQL trace les modifications sensibles d'un compte
 * (role, activation) meme si elles sont faites directement en SQL, hors application. La table
 * historique_utilisateur n'est pas mappee en entite (ecriture par la base, lecture par SQL natif /
 * procedure stockee). Demontre la maitrise du SQL avance (trigger + procedure) en complement de Doctrine.
 */
final class Version20260707182041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'US-5.2 : audit des comptes via trigger AFTER UPDATE (historique_utilisateur) + procedure stockee.';
    }

    public function up(Schema $schema): void
    {
        // Table d'audit alimentee par le trigger (non mappee en entite Doctrine).
        $this->addSql(<<<'SQL'
            CREATE TABLE historique_utilisateur (
                id INT AUTO_INCREMENT NOT NULL,
                utilisateur_id INT NOT NULL,
                champ_modifie VARCHAR(50) NOT NULL,
                ancienne_valeur VARCHAR(255) DEFAULT NULL,
                nouvelle_valeur VARCHAR(255) DEFAULT NULL,
                date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_historique_utilisateur (utilisateur_id, date_modification),
                PRIMARY KEY(id),
                CONSTRAINT fk_historique_utilisateur_utilisateur
                    FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);

        // Trigger : une ligne d'historique par champ sensible reellement modifie (role, activation).
        // Envoye en une seule instruction : pas de DELIMITER (directive du client mysql, inutile via
        // DBAL ; le corps BEGIN...END est transmis entier au serveur).
        $this->addSql(<<<'SQL'
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
            END
        SQL);

        // Procedure de consultation de l'historique d'un compte (trace RGPD, plus recent d'abord).
        $this->addSql(<<<'SQL'
            CREATE PROCEDURE consulter_historique_utilisateur(IN p_utilisateur_id INT)
            BEGIN
                SELECT champ_modifie, ancienne_valeur, nouvelle_valeur, date_modification
                FROM historique_utilisateur
                WHERE utilisateur_id = p_utilisateur_id
                ORDER BY date_modification DESC;
            END
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP PROCEDURE IF EXISTS consulter_historique_utilisateur');
        $this->addSql('DROP TRIGGER IF EXISTS trg_historique_utilisateur');
        $this->addSql('DROP TABLE IF EXISTS historique_utilisateur');
    }
}
