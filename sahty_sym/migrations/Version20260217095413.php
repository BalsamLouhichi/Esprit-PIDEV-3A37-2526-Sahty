<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217095413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commande (id INT AUTO_INCREMENT NOT NULL, numero VARCHAR(50) NOT NULL, quantite INT NOT NULL, prix_unitaire NUMERIC(10, 2) NOT NULL, prix_total NUMERIC(10, 2) NOT NULL, nom_client VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL, telephone VARCHAR(30) NOT NULL, adresse_livraison LONGTEXT NOT NULL, notes LONGTEXT DEFAULT NULL, statut VARCHAR(20) NOT NULL, date_creation DATETIME NOT NULL, date_modification DATETIME DEFAULT NULL, produit_id INT NOT NULL, parapharmacie_id INT NOT NULL, UNIQUE INDEX UNIQ_6EEAA67DF55AE19E (numero), INDEX IDX_6EEAA67DF347EFB (produit_id), INDEX IDX_6EEAA67DD7C4E100 (parapharmacie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE parapharmacie (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, adresse VARCHAR(255) NOT NULL, telephone VARCHAR(30) NOT NULL, email VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE parapharmacie_produit (parapharmacie_id INT NOT NULL, produit_id INT NOT NULL, INDEX IDX_C9BCCB27D7C4E100 (parapharmacie_id), INDEX IDX_C9BCCB27F347EFB (produit_id), PRIMARY KEY (parapharmacie_id, produit_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE produit (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, prix NUMERIC(10, 2) NOT NULL, stock INT DEFAULT NULL, marque VARCHAR(255) DEFAULT NULL, categorie VARCHAR(100) DEFAULT NULL, promotion INT DEFAULT NULL, est_actif TINYINT DEFAULT 1 NOT NULL, poids DOUBLE PRECISION DEFAULT NULL, code_barre VARCHAR(50) DEFAULT NULL, reference VARCHAR(255) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE produit_parapharmacie (produit_id INT NOT NULL, parapharmacie_id INT NOT NULL, INDEX IDX_98AB859F347EFB (produit_id), INDEX IDX_98AB859D7C4E100 (parapharmacie_id), PRIMARY KEY (produit_id, parapharmacie_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DD7C4E100 FOREIGN KEY (parapharmacie_id) REFERENCES parapharmacie (id)');
        $this->addSql('ALTER TABLE parapharmacie_produit ADD CONSTRAINT FK_C9BCCB27D7C4E100 FOREIGN KEY (parapharmacie_id) REFERENCES parapharmacie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parapharmacie_produit ADD CONSTRAINT FK_C9BCCB27F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE produit_parapharmacie ADD CONSTRAINT FK_98AB859F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE produit_parapharmacie ADD CONSTRAINT FK_98AB859D7C4E100 FOREIGN KEY (parapharmacie_id) REFERENCES parapharmacie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE responsable_parapharmacie ADD premiere_connexion TINYINT DEFAULT 1 NOT NULL, ADD derniere_connexion DATETIME DEFAULT NULL, ADD invitation_token VARCHAR(64) DEFAULT NULL, ADD invitation_expire_le DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE responsable_parapharmacie ADD CONSTRAINT FK_5AF73461D7C4E100 FOREIGN KEY (parapharmacie_id) REFERENCES parapharmacie (id)');
        $this->addSql('CREATE INDEX IDX_5AF73461D7C4E100 ON responsable_parapharmacie (parapharmacie_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DF347EFB');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DD7C4E100');
        $this->addSql('ALTER TABLE parapharmacie_produit DROP FOREIGN KEY FK_C9BCCB27D7C4E100');
        $this->addSql('ALTER TABLE parapharmacie_produit DROP FOREIGN KEY FK_C9BCCB27F347EFB');
        $this->addSql('ALTER TABLE produit_parapharmacie DROP FOREIGN KEY FK_98AB859F347EFB');
        $this->addSql('ALTER TABLE produit_parapharmacie DROP FOREIGN KEY FK_98AB859D7C4E100');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE parapharmacie');
        $this->addSql('DROP TABLE parapharmacie_produit');
        $this->addSql('DROP TABLE produit');
        $this->addSql('DROP TABLE produit_parapharmacie');
        $this->addSql('ALTER TABLE responsable_parapharmacie DROP FOREIGN KEY FK_5AF73461D7C4E100');
        $this->addSql('DROP INDEX IDX_5AF73461D7C4E100 ON responsable_parapharmacie');
        $this->addSql('ALTER TABLE responsable_parapharmacie DROP premiere_connexion, DROP derniere_connexion, DROP invitation_token, DROP invitation_expire_le');
    }
}
