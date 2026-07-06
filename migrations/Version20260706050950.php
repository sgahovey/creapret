<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706050950 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree la table exemplaire (unite physique, etats) + FK RESTRICT + index (id_materiel, etat), US-2.2.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE exemplaire (id INT AUTO_INCREMENT NOT NULL, numero_inventaire VARCHAR(50) NOT NULL, etat VARCHAR(20) NOT NULL, id_materiel INT NOT NULL, INDEX IDX_5EF83C9248095C04 (id_materiel), INDEX idx_exemplaire_materiel_etat (id_materiel, etat), UNIQUE INDEX uniq_exemplaire_numero (numero_inventaire), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE exemplaire ADD CONSTRAINT FK_5EF83C9248095C04 FOREIGN KEY (id_materiel) REFERENCES materiel (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exemplaire DROP FOREIGN KEY FK_5EF83C9248095C04');
        $this->addSql('DROP TABLE exemplaire');
    }
}
