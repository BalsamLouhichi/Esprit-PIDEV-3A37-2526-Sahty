<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211223325 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fiche_medicale (id INT AUTO_INCREMENT NOT NULL, antecedents LONGTEXT DEFAULT NULL, allergies LONGTEXT DEFAULT NULL, traitement_en_cours LONGTEXT DEFAULT NULL, taille NUMERIC(5, 2) DEFAULT NULL, poids NUMERIC(5, 2) DEFAULT NULL, imc NUMERIC(5, 2) DEFAULT NULL, categorie_imc VARCHAR(50) DEFAULT NULL, diagnostic LONGTEXT DEFAULT NULL, traitement_prescrit LONGTEXT DEFAULT NULL, observations LONGTEXT DEFAULT NULL, cree_le DATETIME DEFAULT NULL, modifie_le DATETIME DEFAULT NULL, statut VARCHAR(20) DEFAULT NULL, patient_id INT DEFAULT NULL, rendez_vous_id INT DEFAULT NULL, INDEX IDX_20D23266B899279 (patient_id), UNIQUE INDEX UNIQ_20D232691EF7EAA (rendez_vous_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE rendez_vous (id INT AUTO_INCREMENT NOT NULL, date_rdv DATE NOT NULL, heure_rdv TIME NOT NULL, raison LONGTEXT DEFAULT NULL, statut VARCHAR(20) NOT NULL, cree_le DATETIME NOT NULL, date_validation DATETIME DEFAULT NULL, patient_id INT DEFAULT NULL, medecin_id INT NOT NULL, fiche_medicale_id INT DEFAULT NULL, INDEX IDX_65E8AA0A6B899279 (patient_id), INDEX IDX_65E8AA0A4F31A84 (medecin_id), UNIQUE INDEX UNIQ_65E8AA0A9A99F4BC (fiche_medicale_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE fiche_medicale ADD CONSTRAINT FK_20D23266B899279 FOREIGN KEY (patient_id) REFERENCES patient (id)');
        $this->addSql('ALTER TABLE fiche_medicale ADD CONSTRAINT FK_20D232691EF7EAA FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A6B899279 FOREIGN KEY (patient_id) REFERENCES patient (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A4F31A84 FOREIGN KEY (medecin_id) REFERENCES medecin (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A9A99F4BC FOREIGN KEY (fiche_medicale_id) REFERENCES fiche_medicale (id)');
        $this->addSql('ALTER TABLE medecin CHANGE specialite specialite VARCHAR(100) DEFAULT NULL, CHANGE document_pdf document_pdf VARCHAR(255) DEFAULT NULL, CHANGE adresse_cabinet adresse_cabinet VARCHAR(255) DEFAULT NULL, CHANGE grade grade VARCHAR(50) DEFAULT NULL, CHANGE telephone_cabinet telephone_cabinet VARCHAR(20) DEFAULT NULL, CHANGE nom_etablissement nom_etablissement VARCHAR(100) DEFAULT NULL, CHANGE numero_urgence numero_urgence VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE patient CHANGE sexe sexe VARCHAR(10) DEFAULT NULL, CHANGE groupe_sanguin groupe_sanguin VARCHAR(10) DEFAULT NULL, CHANGE contact_urgence contact_urgence VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE date_naissance date_naissance DATE DEFAULT NULL, CHANGE photo_profil photo_profil VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fiche_medicale DROP FOREIGN KEY FK_20D23266B899279');
        $this->addSql('ALTER TABLE fiche_medicale DROP FOREIGN KEY FK_20D232691EF7EAA');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A6B899279');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A4F31A84');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A9A99F4BC');
        $this->addSql('DROP TABLE fiche_medicale');
        $this->addSql('DROP TABLE rendez_vous');
        $this->addSql('ALTER TABLE medecin CHANGE specialite specialite VARCHAR(100) DEFAULT \'NULL\', CHANGE grade grade VARCHAR(50) DEFAULT \'NULL\', CHANGE adresse_cabinet adresse_cabinet VARCHAR(255) DEFAULT \'NULL\', CHANGE telephone_cabinet telephone_cabinet VARCHAR(20) DEFAULT \'NULL\', CHANGE nom_etablissement nom_etablissement VARCHAR(100) DEFAULT \'NULL\', CHANGE numero_urgence numero_urgence VARCHAR(20) DEFAULT \'NULL\', CHANGE document_pdf document_pdf VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE patient CHANGE groupe_sanguin groupe_sanguin VARCHAR(10) DEFAULT \'NULL\', CHANGE contact_urgence contact_urgence VARCHAR(20) DEFAULT \'NULL\', CHANGE sexe sexe VARCHAR(10) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE utilisateur CHANGE telephone telephone VARCHAR(20) DEFAULT \'NULL\', CHANGE date_naissance date_naissance DATE DEFAULT \'NULL\', CHANGE photo_profil photo_profil VARCHAR(255) DEFAULT \'NULL\'');
    }
}
