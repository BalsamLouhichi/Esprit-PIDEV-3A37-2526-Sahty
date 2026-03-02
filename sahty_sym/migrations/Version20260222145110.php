<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222145110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commande (id INT AUTO_INCREMENT NOT NULL, numero VARCHAR(50) NOT NULL, quantite INT NOT NULL, prix_unitaire NUMERIC(10, 2) NOT NULL, prix_total NUMERIC(10, 2) NOT NULL, nom_client VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL, telephone VARCHAR(30) NOT NULL, adresse_livraison LONGTEXT NOT NULL, notes LONGTEXT DEFAULT NULL, statut VARCHAR(20) NOT NULL, date_creation DATETIME NOT NULL, date_modification DATETIME DEFAULT NULL, mode_paiement VARCHAR(30) DEFAULT \'cash_on_delivery\' NOT NULL, payment_status VARCHAR(30) DEFAULT \'not_required\' NOT NULL, payment_provider VARCHAR(50) DEFAULT NULL, payment_reference VARCHAR(255) DEFAULT NULL, payment_url LONGTEXT DEFAULT NULL, produit_id INT NOT NULL, parapharmacie_id INT NOT NULL, UNIQUE INDEX UNIQ_6EEAA67DF55AE19E (numero), INDEX IDX_6EEAA67DF347EFB (produit_id), INDEX IDX_6EEAA67DD7C4E100 (parapharmacie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE parapharmacie (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, adresse VARCHAR(255) NOT NULL, telephone VARCHAR(30) NOT NULL, email VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE parapharmacie_produit (parapharmacie_id INT NOT NULL, produit_id INT NOT NULL, INDEX IDX_C9BCCB27D7C4E100 (parapharmacie_id), INDEX IDX_C9BCCB27F347EFB (produit_id), PRIMARY KEY (parapharmacie_id, produit_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE produit (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, prix NUMERIC(10, 2) NOT NULL, stock INT DEFAULT NULL, marque VARCHAR(255) DEFAULT NULL, categorie VARCHAR(100) DEFAULT NULL, promotion INT DEFAULT NULL, est_actif TINYINT DEFAULT 1 NOT NULL, poids DOUBLE PRECISION DEFAULT NULL, code_barre VARCHAR(50) DEFAULT NULL, reference VARCHAR(255) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE produit_parapharmacie (produit_id INT NOT NULL, parapharmacie_id INT NOT NULL, INDEX IDX_98AB859F347EFB (produit_id), INDEX IDX_98AB859D7C4E100 (parapharmacie_id), PRIMARY KEY (produit_id, parapharmacie_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE resultat_analyse (id INT AUTO_INCREMENT NOT NULL, source_pdf VARCHAR(255) DEFAULT NULL, ai_status VARCHAR(20) NOT NULL, anomalies JSON DEFAULT NULL, danger_score INT DEFAULT NULL, danger_level VARCHAR(20) DEFAULT NULL, resume_bilan LONGTEXT DEFAULT NULL, modele_version VARCHAR(100) DEFAULT NULL, ai_raw_response JSON DEFAULT NULL, analyse_le DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, demande_analyse_id INT NOT NULL, UNIQUE INDEX UNIQ_20A9B04BE8320C1E (demande_analyse_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DD7C4E100 FOREIGN KEY (parapharmacie_id) REFERENCES parapharmacie (id)');
        $this->addSql('ALTER TABLE parapharmacie_produit ADD CONSTRAINT FK_C9BCCB27D7C4E100 FOREIGN KEY (parapharmacie_id) REFERENCES parapharmacie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parapharmacie_produit ADD CONSTRAINT FK_C9BCCB27F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE produit_parapharmacie ADD CONSTRAINT FK_98AB859F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE produit_parapharmacie ADD CONSTRAINT FK_98AB859D7C4E100 FOREIGN KEY (parapharmacie_id) REFERENCES parapharmacie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE resultat_analyse ADD CONSTRAINT FK_20A9B04BE8320C1E FOREIGN KEY (demande_analyse_id) REFERENCES demande_analyse (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE demande_analyse CHANGE programme_le programme_le DATETIME DEFAULT NULL, CHANGE envoye_le envoye_le DATETIME DEFAULT NULL, CHANGE notes notes VARCHAR(255) DEFAULT NULL, CHANGE analyses analyses JSON DEFAULT NULL, CHANGE resultat_pdf resultat_pdf VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE evenement CHANGE date_fin date_fin DATETIME DEFAULT NULL, CHANGE lieu lieu VARCHAR(255) DEFAULT NULL, CHANGE tarif tarif NUMERIC(10, 2) DEFAULT NULL, CHANGE devise devise VARCHAR(10) DEFAULT \'DT\' NOT NULL, CHANGE modifie_le modifie_le DATETIME DEFAULT NULL, CHANGE statut_demande statut_demande VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE fiche_medicale CHANGE taille taille NUMERIC(5, 2) DEFAULT NULL, CHANGE poids poids NUMERIC(5, 2) DEFAULT NULL, CHANGE imc imc NUMERIC(5, 2) DEFAULT NULL, CHANGE categorie_imc categorie_imc VARCHAR(50) DEFAULT NULL, CHANGE cree_le cree_le DATETIME DEFAULT NULL, CHANGE modifie_le modifie_le DATETIME DEFAULT NULL, CHANGE statut statut VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE groupe_cible CHANGE critere_optionnel critere_optionnel VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE inscription_evenement CHANGE modifie_le modifie_le DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE laboratoire CHANGE email email VARCHAR(255) DEFAULT NULL, CHANGE numero_agrement numero_agrement VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE laboratoire_type_analyse CHANGE prix prix NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE medecin CHANGE specialite specialite VARCHAR(100) DEFAULT NULL, CHANGE document_pdf document_pdf VARCHAR(255) DEFAULT NULL, CHANGE adresse_cabinet adresse_cabinet VARCHAR(255) DEFAULT NULL, CHANGE grade grade VARCHAR(50) DEFAULT NULL, CHANGE telephone_cabinet telephone_cabinet VARCHAR(20) DEFAULT NULL, CHANGE nom_etablissement nom_etablissement VARCHAR(100) DEFAULT NULL, CHANGE numero_urgence numero_urgence VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE patient CHANGE sexe sexe VARCHAR(10) DEFAULT NULL, CHANGE groupe_sanguin groupe_sanguin VARCHAR(10) DEFAULT NULL, CHANGE contact_urgence contact_urgence VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous CHANGE date_validation date_validation DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE responsable_laboratoire ADD CONSTRAINT FK_C4592A3076E2617B FOREIGN KEY (laboratoire_id) REFERENCES laboratoire (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C4592A3076E2617B ON responsable_laboratoire (laboratoire_id)');
        $this->addSql('ALTER TABLE responsable_parapharmacie ADD premiere_connexion TINYINT DEFAULT 1 NOT NULL, ADD derniere_connexion DATETIME DEFAULT NULL, ADD invitation_token VARCHAR(64) DEFAULT NULL, ADD invitation_expire_le DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE responsable_parapharmacie ADD CONSTRAINT FK_5AF73461D7C4E100 FOREIGN KEY (parapharmacie_id) REFERENCES parapharmacie (id)');
        $this->addSql('CREATE INDEX IDX_5AF73461D7C4E100 ON responsable_parapharmacie (parapharmacie_id)');
        $this->addSql('ALTER TABLE type_analyse CHANGE categorie categorie VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE password password VARCHAR(255) DEFAULT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE date_naissance date_naissance DATE DEFAULT NULL, CHANGE photo_profil photo_profil VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
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
        $this->addSql('ALTER TABLE resultat_analyse DROP FOREIGN KEY FK_20A9B04BE8320C1E');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE parapharmacie');
        $this->addSql('DROP TABLE parapharmacie_produit');
        $this->addSql('DROP TABLE produit');
        $this->addSql('DROP TABLE produit_parapharmacie');
        $this->addSql('DROP TABLE resultat_analyse');
        $this->addSql('ALTER TABLE demande_analyse CHANGE programme_le programme_le DATETIME DEFAULT \'NULL\', CHANGE envoye_le envoye_le DATETIME DEFAULT \'NULL\', CHANGE notes notes VARCHAR(255) DEFAULT \'NULL\', CHANGE analyses analyses LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE resultat_pdf resultat_pdf VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE evenement CHANGE date_fin date_fin DATETIME DEFAULT \'NULL\', CHANGE lieu lieu VARCHAR(255) DEFAULT \'NULL\', CHANGE tarif tarif NUMERIC(10, 2) DEFAULT \'NULL\', CHANGE devise devise VARCHAR(10) DEFAULT \'\'\'DT\'\'\' NOT NULL, CHANGE modifie_le modifie_le DATETIME DEFAULT \'NULL\', CHANGE statut_demande statut_demande VARCHAR(50) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE fiche_medicale CHANGE taille taille NUMERIC(5, 2) DEFAULT \'NULL\', CHANGE poids poids NUMERIC(5, 2) DEFAULT \'NULL\', CHANGE imc imc NUMERIC(5, 2) DEFAULT \'NULL\', CHANGE categorie_imc categorie_imc VARCHAR(50) DEFAULT \'NULL\', CHANGE cree_le cree_le DATETIME DEFAULT \'NULL\', CHANGE modifie_le modifie_le DATETIME DEFAULT \'NULL\', CHANGE statut statut VARCHAR(20) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE groupe_cible CHANGE critere_optionnel critere_optionnel VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE inscription_evenement CHANGE modifie_le modifie_le DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE laboratoire CHANGE email email VARCHAR(255) DEFAULT \'NULL\', CHANGE numero_agrement numero_agrement VARCHAR(50) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE laboratoire_type_analyse CHANGE prix prix NUMERIC(10, 2) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE medecin CHANGE specialite specialite VARCHAR(100) DEFAULT \'NULL\', CHANGE grade grade VARCHAR(50) DEFAULT \'NULL\', CHANGE adresse_cabinet adresse_cabinet VARCHAR(255) DEFAULT \'NULL\', CHANGE telephone_cabinet telephone_cabinet VARCHAR(20) DEFAULT \'NULL\', CHANGE nom_etablissement nom_etablissement VARCHAR(100) DEFAULT \'NULL\', CHANGE numero_urgence numero_urgence VARCHAR(20) DEFAULT \'NULL\', CHANGE document_pdf document_pdf VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE patient CHANGE groupe_sanguin groupe_sanguin VARCHAR(10) DEFAULT \'NULL\', CHANGE contact_urgence contact_urgence VARCHAR(20) DEFAULT \'NULL\', CHANGE sexe sexe VARCHAR(10) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE rendez_vous CHANGE date_validation date_validation DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE responsable_laboratoire DROP FOREIGN KEY FK_C4592A3076E2617B');
        $this->addSql('DROP INDEX UNIQ_C4592A3076E2617B ON responsable_laboratoire');
        $this->addSql('ALTER TABLE responsable_parapharmacie DROP FOREIGN KEY FK_5AF73461D7C4E100');
        $this->addSql('DROP INDEX IDX_5AF73461D7C4E100 ON responsable_parapharmacie');
        $this->addSql('ALTER TABLE responsable_parapharmacie DROP premiere_connexion, DROP derniere_connexion, DROP invitation_token, DROP invitation_expire_le');
        $this->addSql('ALTER TABLE type_analyse CHANGE categorie categorie VARCHAR(100) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE utilisateur CHANGE password password VARCHAR(255) DEFAULT \'NULL\', CHANGE telephone telephone VARCHAR(20) DEFAULT \'NULL\', CHANGE date_naissance date_naissance DATE DEFAULT \'NULL\', CHANGE photo_profil photo_profil VARCHAR(255) DEFAULT \'NULL\'');
    }
}
