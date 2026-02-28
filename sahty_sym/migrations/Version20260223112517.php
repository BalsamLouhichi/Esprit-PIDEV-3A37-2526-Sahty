<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223112517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ligne_commande (id INT AUTO_INCREMENT NOT NULL, quantite INT NOT NULL, prix_unitaire NUMERIC(10, 2) NOT NULL, sous_total NUMERIC(10, 2) NOT NULL, commande_id INT NOT NULL, produit_id INT NOT NULL, INDEX IDX_3170B74B82EA2E54 (commande_id), INDEX IDX_3170B74BF347EFB (produit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74B82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id)');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74BF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)');
        $this->addSql('ALTER TABLE parapharmacie_produit DROP FOREIGN KEY `FK_C9BCCB27D7C4E100`');
        $this->addSql('ALTER TABLE parapharmacie_produit DROP FOREIGN KEY `FK_C9BCCB27F347EFB`');
        $this->addSql('DROP TABLE parapharmacie_produit');
        $this->addSql('ALTER TABLE commande DROP mode_paiement, DROP payment_status, DROP payment_provider, DROP payment_reference, DROP payment_url');
        $this->addSql('ALTER TABLE rendez_vous ADD type_consultation VARCHAR(20) NOT NULL, ADD meeting_url VARCHAR(500) DEFAULT NULL, ADD meeting_provider VARCHAR(50) DEFAULT NULL, ADD meeting_created_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE parapharmacie_produit (parapharmacie_id INT NOT NULL, produit_id INT NOT NULL, INDEX IDX_C9BCCB27D7C4E100 (parapharmacie_id), INDEX IDX_C9BCCB27F347EFB (produit_id), PRIMARY KEY (parapharmacie_id, produit_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE parapharmacie_produit ADD CONSTRAINT `FK_C9BCCB27D7C4E100` FOREIGN KEY (parapharmacie_id) REFERENCES parapharmacie (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parapharmacie_produit ADD CONSTRAINT `FK_C9BCCB27F347EFB` FOREIGN KEY (produit_id) REFERENCES produit (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74B82EA2E54');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74BF347EFB');
        $this->addSql('DROP TABLE ligne_commande');
        $this->addSql('ALTER TABLE commande ADD mode_paiement VARCHAR(30) DEFAULT \'cash_on_delivery\' NOT NULL, ADD payment_status VARCHAR(30) DEFAULT \'not_required\' NOT NULL, ADD payment_provider VARCHAR(50) DEFAULT NULL, ADD payment_reference VARCHAR(255) DEFAULT NULL, ADD payment_url LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous DROP type_consultation, DROP meeting_url, DROP meeting_provider, DROP meeting_created_at');
    }
}
