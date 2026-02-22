<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creation table resultat_analyse pour les analyses IA de resultats PDF';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE resultat_analyse (id INT AUTO_INCREMENT NOT NULL, demande_analyse_id INT NOT NULL, source_pdf VARCHAR(255) DEFAULT NULL, ai_status VARCHAR(20) NOT NULL, anomalies JSON DEFAULT NULL, danger_score INT DEFAULT NULL, danger_level VARCHAR(20) DEFAULT NULL, resume_bilan LONGTEXT DEFAULT NULL, modele_version VARCHAR(100) DEFAULT NULL, ai_raw_response JSON DEFAULT NULL, analyse_le DATETIME DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_RESULTAT_ANALYSE_DEMANDE (demande_analyse_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE resultat_analyse ADD CONSTRAINT FK_RESULTAT_ANALYSE_DEMANDE FOREIGN KEY (demande_analyse_id) REFERENCES demande_analyse (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE resultat_analyse DROP FOREIGN KEY FK_RESULTAT_ANALYSE_DEMANDE');
        $this->addSql('DROP TABLE resultat_analyse');
    }
}
