<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223185958 extends AbstractMigration
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
        $this->addSql('ALTER TABLE commande DROP mode_paiement, DROP payment_status, DROP payment_provider, DROP payment_reference, DROP payment_url, CHANGE date_modification date_modification DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE demande_analyse CHANGE programme_le programme_le DATETIME DEFAULT NULL, CHANGE envoye_le envoye_le DATETIME DEFAULT NULL, CHANGE notes notes VARCHAR(255) DEFAULT NULL, CHANGE analyses analyses JSON DEFAULT NULL, CHANGE resultat_pdf resultat_pdf VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE evenement ADD meeting_platform VARCHAR(30) DEFAULT NULL, ADD meeting_link VARCHAR(500) DEFAULT NULL, CHANGE date_fin date_fin DATETIME DEFAULT NULL, CHANGE lieu lieu VARCHAR(255) DEFAULT NULL, CHANGE tarif tarif NUMERIC(10, 2) DEFAULT NULL, CHANGE devise devise VARCHAR(10) DEFAULT \'DT\' NOT NULL, CHANGE modifie_le modifie_le DATETIME DEFAULT NULL, CHANGE statut_demande statut_demande VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE fiche_medicale CHANGE taille taille NUMERIC(5, 2) DEFAULT NULL, CHANGE poids poids NUMERIC(5, 2) DEFAULT NULL, CHANGE imc imc NUMERIC(5, 2) DEFAULT NULL, CHANGE categorie_imc categorie_imc VARCHAR(50) DEFAULT NULL, CHANGE cree_le cree_le DATETIME DEFAULT NULL, CHANGE modifie_le modifie_le DATETIME DEFAULT NULL, CHANGE statut statut VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE groupe_cible CHANGE critere_optionnel critere_optionnel VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE inscription_evenement CHANGE modifie_le modifie_le DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE laboratoire CHANGE email email VARCHAR(255) DEFAULT NULL, CHANGE numero_agrement numero_agrement VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE laboratoire_type_analyse CHANGE prix prix NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE medecin CHANGE specialite specialite VARCHAR(100) DEFAULT NULL, CHANGE document_pdf document_pdf VARCHAR(255) DEFAULT NULL, CHANGE adresse_cabinet adresse_cabinet VARCHAR(255) DEFAULT NULL, CHANGE grade grade VARCHAR(50) DEFAULT NULL, CHANGE telephone_cabinet telephone_cabinet VARCHAR(20) DEFAULT NULL, CHANGE nom_etablissement nom_etablissement VARCHAR(100) DEFAULT NULL, CHANGE numero_urgence numero_urgence VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE parapharmacie CHANGE email email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE patient CHANGE sexe sexe VARCHAR(10) DEFAULT NULL, CHANGE groupe_sanguin groupe_sanguin VARCHAR(10) DEFAULT NULL, CHANGE contact_urgence contact_urgence VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit CHANGE marque marque VARCHAR(255) DEFAULT NULL, CHANGE categorie categorie VARCHAR(100) DEFAULT NULL, CHANGE poids poids DOUBLE PRECISION DEFAULT NULL, CHANGE code_barre code_barre VARCHAR(50) DEFAULT NULL, CHANGE reference reference VARCHAR(255) DEFAULT NULL, CHANGE image image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD type_consultation VARCHAR(20) NOT NULL, ADD meeting_url VARCHAR(500) DEFAULT NULL, ADD meeting_provider VARCHAR(50) DEFAULT NULL, ADD meeting_created_at DATETIME DEFAULT NULL, CHANGE date_validation date_validation DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE responsable_laboratoire ADD CONSTRAINT FK_C4592A3076E2617B FOREIGN KEY (laboratoire_id) REFERENCES laboratoire (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C4592A3076E2617B ON responsable_laboratoire (laboratoire_id)');
        $this->addSql('ALTER TABLE responsable_parapharmacie CHANGE derniere_connexion derniere_connexion DATETIME DEFAULT NULL, CHANGE invitation_token invitation_token VARCHAR(64) DEFAULT NULL, CHANGE invitation_expire_le invitation_expire_le DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE responsable_parapharmacie ADD CONSTRAINT FK_5AF73461D7C4E100 FOREIGN KEY (parapharmacie_id) REFERENCES parapharmacie (id)');
        $this->addSql('CREATE INDEX IDX_5AF73461D7C4E100 ON responsable_parapharmacie (parapharmacie_id)');
        $this->addSql('ALTER TABLE resultat_analyse CHANGE source_pdf source_pdf VARCHAR(255) DEFAULT NULL, CHANGE anomalies anomalies JSON DEFAULT NULL, CHANGE danger_level danger_level VARCHAR(20) DEFAULT NULL, CHANGE modele_version modele_version VARCHAR(100) DEFAULT NULL, CHANGE ai_raw_response ai_raw_response JSON DEFAULT NULL, CHANGE analyse_le analyse_le DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE type_analyse CHANGE categorie categorie VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE password password VARCHAR(255) NOT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE date_naissance date_naissance DATE DEFAULT NULL, CHANGE photo_profil photo_profil VARCHAR(255) DEFAULT NULL, CHANGE ville ville VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE parapharmacie_produit (parapharmacie_id INT NOT NULL, produit_id INT NOT NULL, INDEX IDX_C9BCCB27D7C4E100 (parapharmacie_id), INDEX IDX_C9BCCB27F347EFB (produit_id), PRIMARY KEY (parapharmacie_id, produit_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE parapharmacie_produit ADD CONSTRAINT `FK_C9BCCB27D7C4E100` FOREIGN KEY (parapharmacie_id) REFERENCES parapharmacie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parapharmacie_produit ADD CONSTRAINT `FK_C9BCCB27F347EFB` FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74B82EA2E54');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74BF347EFB');
        $this->addSql('DROP TABLE ligne_commande');
        $this->addSql('ALTER TABLE commande ADD mode_paiement VARCHAR(30) DEFAULT \'\'\'cash_on_delivery\'\'\' NOT NULL, ADD payment_status VARCHAR(30) DEFAULT \'\'\'not_required\'\'\' NOT NULL, ADD payment_provider VARCHAR(50) DEFAULT \'NULL\', ADD payment_reference VARCHAR(255) DEFAULT \'NULL\', ADD payment_url LONGTEXT DEFAULT NULL, CHANGE date_modification date_modification DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE demande_analyse CHANGE programme_le programme_le DATETIME DEFAULT \'NULL\', CHANGE envoye_le envoye_le DATETIME DEFAULT \'NULL\', CHANGE notes notes VARCHAR(255) DEFAULT \'NULL\', CHANGE analyses analyses LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE resultat_pdf resultat_pdf VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE evenement DROP meeting_platform, DROP meeting_link, CHANGE date_fin date_fin DATETIME DEFAULT \'NULL\', CHANGE lieu lieu VARCHAR(255) DEFAULT \'NULL\', CHANGE tarif tarif NUMERIC(10, 2) DEFAULT \'NULL\', CHANGE devise devise VARCHAR(10) DEFAULT \'\'\'DT\'\'\' NOT NULL, CHANGE modifie_le modifie_le DATETIME DEFAULT \'NULL\', CHANGE statut_demande statut_demande VARCHAR(50) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE fiche_medicale CHANGE taille taille NUMERIC(5, 2) DEFAULT \'NULL\', CHANGE poids poids NUMERIC(5, 2) DEFAULT \'NULL\', CHANGE imc imc NUMERIC(5, 2) DEFAULT \'NULL\', CHANGE categorie_imc categorie_imc VARCHAR(50) DEFAULT \'NULL\', CHANGE cree_le cree_le DATETIME DEFAULT \'NULL\', CHANGE modifie_le modifie_le DATETIME DEFAULT \'NULL\', CHANGE statut statut VARCHAR(20) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE groupe_cible CHANGE critere_optionnel critere_optionnel VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE inscription_evenement CHANGE modifie_le modifie_le DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE laboratoire CHANGE email email VARCHAR(255) DEFAULT \'NULL\', CHANGE numero_agrement numero_agrement VARCHAR(50) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE laboratoire_type_analyse CHANGE prix prix NUMERIC(10, 2) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE medecin CHANGE specialite specialite VARCHAR(100) DEFAULT \'NULL\', CHANGE grade grade VARCHAR(50) DEFAULT \'NULL\', CHANGE adresse_cabinet adresse_cabinet VARCHAR(255) DEFAULT \'NULL\', CHANGE telephone_cabinet telephone_cabinet VARCHAR(20) DEFAULT \'NULL\', CHANGE nom_etablissement nom_etablissement VARCHAR(100) DEFAULT \'NULL\', CHANGE numero_urgence numero_urgence VARCHAR(20) DEFAULT \'NULL\', CHANGE document_pdf document_pdf VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE parapharmacie CHANGE email email VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE patient CHANGE groupe_sanguin groupe_sanguin VARCHAR(10) DEFAULT \'NULL\', CHANGE contact_urgence contact_urgence VARCHAR(20) DEFAULT \'NULL\', CHANGE sexe sexe VARCHAR(10) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE produit CHANGE marque marque VARCHAR(255) DEFAULT \'NULL\', CHANGE categorie categorie VARCHAR(100) DEFAULT \'NULL\', CHANGE poids poids DOUBLE PRECISION DEFAULT \'NULL\', CHANGE code_barre code_barre VARCHAR(50) DEFAULT \'NULL\', CHANGE reference reference VARCHAR(255) DEFAULT \'NULL\', CHANGE image image VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE rendez_vous DROP type_consultation, DROP meeting_url, DROP meeting_provider, DROP meeting_created_at, CHANGE date_validation date_validation DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE responsable_laboratoire DROP FOREIGN KEY FK_C4592A3076E2617B');
        $this->addSql('DROP INDEX UNIQ_C4592A3076E2617B ON responsable_laboratoire');
        $this->addSql('ALTER TABLE responsable_parapharmacie DROP FOREIGN KEY FK_5AF73461D7C4E100');
        $this->addSql('DROP INDEX IDX_5AF73461D7C4E100 ON responsable_parapharmacie');
        $this->addSql('ALTER TABLE responsable_parapharmacie CHANGE derniere_connexion derniere_connexion DATETIME DEFAULT \'NULL\', CHANGE invitation_token invitation_token VARCHAR(64) DEFAULT \'NULL\', CHANGE invitation_expire_le invitation_expire_le DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE resultat_analyse CHANGE source_pdf source_pdf VARCHAR(255) DEFAULT \'NULL\', CHANGE anomalies anomalies LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE danger_level danger_level VARCHAR(20) DEFAULT \'NULL\', CHANGE modele_version modele_version VARCHAR(100) DEFAULT \'NULL\', CHANGE ai_raw_response ai_raw_response LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE analyse_le analyse_le DATETIME DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE type_analyse CHANGE categorie categorie VARCHAR(100) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE utilisateur CHANGE password password VARCHAR(255) DEFAULT \'NULL\', CHANGE telephone telephone VARCHAR(20) DEFAULT \'NULL\', CHANGE ville ville VARCHAR(100) DEFAULT \'NULL\', CHANGE date_naissance date_naissance DATE DEFAULT \'NULL\', CHANGE photo_profil photo_profil VARCHAR(255) DEFAULT \'NULL\'');
    }
}
