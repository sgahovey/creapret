<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706091124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree la table pret + index idx_pret_dispo (id_exemplaire, statut, date_debut, date_fin ; RG-1/RG-4) et idx_pret_emprunteur (US-3.1).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pret (id INT AUTO_INCREMENT NOT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME NOT NULL, statut VARCHAR(20) NOT NULL, motif_refus VARCHAR(255) DEFAULT NULL, date_demande DATETIME NOT NULL, date_validation DATETIME DEFAULT NULL, date_retour DATETIME DEFAULT NULL, id_exemplaire INT NOT NULL, id_emprunteur INT NOT NULL, id_validateur INT DEFAULT NULL, INDEX IDX_52ECE979FB72BC45 (id_exemplaire), INDEX IDX_52ECE9791E108449 (id_validateur), INDEX idx_pret_dispo (id_exemplaire, statut, date_debut, date_fin), INDEX idx_pret_emprunteur (id_emprunteur), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT FK_52ECE979FB72BC45 FOREIGN KEY (id_exemplaire) REFERENCES exemplaire (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT FK_52ECE97930AAE709 FOREIGN KEY (id_emprunteur) REFERENCES utilisateur (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT FK_52ECE9791E108449 FOREIGN KEY (id_validateur) REFERENCES utilisateur (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pret DROP FOREIGN KEY FK_52ECE979FB72BC45');
        $this->addSql('ALTER TABLE pret DROP FOREIGN KEY FK_52ECE97930AAE709');
        $this->addSql('ALTER TABLE pret DROP FOREIGN KEY FK_52ECE9791E108449');
        $this->addSql('DROP TABLE pret');
    }
}
