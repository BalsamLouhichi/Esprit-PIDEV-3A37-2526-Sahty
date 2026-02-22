<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220021515 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
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
        $this->addSql('ALTER TABLE type_analyse CHANGE categorie categorie VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE password password VARCHAR(255) DEFAULT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE date_naissance date_naissance DATE DEFAULT NULL, CHANGE photo_profil photo_profil VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
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
        $this->addSql('ALTER TABLE type_analyse CHANGE categorie categorie VARCHAR(100) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE utilisateur CHANGE password password VARCHAR(255) DEFAULT \'NULL\', CHANGE telephone telephone VARCHAR(20) DEFAULT \'NULL\', CHANGE date_naissance date_naissance DATE DEFAULT \'NULL\', CHANGE photo_profil photo_profil VARCHAR(255) DEFAULT \'NULL\'');
    }
}
