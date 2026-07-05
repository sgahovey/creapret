<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260705031028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la preuve de consentement RGPD (date_consentement, version_cgu) a utilisateur (US-1.2).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur ADD date_consentement DATETIME DEFAULT NULL, ADD version_cgu VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur DROP date_consentement, DROP version_cgu');
    }
}
