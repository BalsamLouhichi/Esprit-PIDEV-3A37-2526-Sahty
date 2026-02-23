<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222125324 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE resultat_analyse (id INT AUTO_INCREMENT NOT NULL, source_pdf VARCHAR(255) DEFAULT NULL, ai_status VARCHAR(20) NOT NULL, anomalies JSON DEFAULT NULL, danger_score INT DEFAULT NULL, danger_level VARCHAR(20) DEFAULT NULL, resume_bilan LONGTEXT DEFAULT NULL, modele_version VARCHAR(100) DEFAULT NULL, ai_raw_response JSON DEFAULT NULL, analyse_le DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, demande_analyse_id INT NOT NULL, UNIQUE INDEX UNIQ_20A9B04BE8320C1E (demande_analyse_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE resultat_analyse ADD CONSTRAINT FK_20A9B04BE8320C1E FOREIGN KEY (demande_analyse_id) REFERENCES demande_analyse (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commande ADD mode_paiement VARCHAR(30) DEFAULT \'cash_on_delivery\' NOT NULL, ADD payment_status VARCHAR(30) DEFAULT \'not_required\' NOT NULL, ADD payment_provider VARCHAR(50) DEFAULT NULL, ADD payment_reference VARCHAR(255) DEFAULT NULL, ADD payment_url LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD ville VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE resultat_analyse DROP FOREIGN KEY FK_20A9B04BE8320C1E');
        $this->addSql('DROP TABLE resultat_analyse');
        $this->addSql('ALTER TABLE commande DROP mode_paiement, DROP payment_status, DROP payment_provider, DROP payment_reference, DROP payment_url');
        $this->addSql('ALTER TABLE utilisateur DROP ville');
    }
}
