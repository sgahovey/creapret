<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706043521 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree les tables categorie et materiel (catalogue, US-2.1).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE categorie (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE materiel (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, marque VARCHAR(100) DEFAULT NULL, modele VARCHAR(100) DEFAULT NULL, reference VARCHAR(100) DEFAULT NULL, id_categorie INT NOT NULL, INDEX IDX_18D2B091C9486A13 (id_categorie), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE materiel ADD CONSTRAINT FK_18D2B091C9486A13 FOREIGN KEY (id_categorie) REFERENCES categorie (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE materiel DROP FOREIGN KEY FK_18D2B091C9486A13');
        $this->addSql('DROP TABLE categorie');
        $this->addSql('DROP TABLE materiel');
    }
}
