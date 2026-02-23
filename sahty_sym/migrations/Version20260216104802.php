<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216104802 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE administrateur (id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE demande_analyse (id INT AUTO_INCREMENT NOT NULL, type_bilan VARCHAR(255) NOT NULL, statut VARCHAR(50) NOT NULL, date_demande DATETIME NOT NULL, programme_le DATETIME DEFAULT NULL, envoye_le DATETIME DEFAULT NULL, notes VARCHAR(255) DEFAULT NULL, priorite VARCHAR(20) NOT NULL, analyses JSON DEFAULT NULL, resultat_pdf VARCHAR(255) DEFAULT NULL, patient_id INT NOT NULL, medecin_id INT DEFAULT NULL, laboratoire_id INT NOT NULL, INDEX IDX_5ECB7D16B899279 (patient_id), INDEX IDX_5ECB7D14F31A84 (medecin_id), INDEX IDX_5ECB7D176E2617B (laboratoire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE evenement (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(50) NOT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME DEFAULT NULL, mode VARCHAR(50) NOT NULL, lieu VARCHAR(255) DEFAULT NULL, places_max INT DEFAULT NULL, statut VARCHAR(50) NOT NULL, tarif NUMERIC(10, 2) DEFAULT NULL, devise VARCHAR(10) DEFAULT \'DT\' NOT NULL, cree_le DATETIME NOT NULL, modifie_le DATETIME DEFAULT NULL, statut_demande VARCHAR(50) DEFAULT NULL, createur_id INT NOT NULL, INDEX IDX_B26681E73A201E5 (createur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE evenement_groupe_cible (evenement_id INT NOT NULL, groupe_cible_id INT NOT NULL, INDEX IDX_18DD4E3DFD02F13 (evenement_id), INDEX IDX_18DD4E3D38CC5579 (groupe_cible_id), PRIMARY KEY (evenement_id, groupe_cible_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE fiche_medicale (id INT AUTO_INCREMENT NOT NULL, antecedents LONGTEXT DEFAULT NULL, allergies LONGTEXT DEFAULT NULL, traitement_en_cours LONGTEXT DEFAULT NULL, taille NUMERIC(5, 2) DEFAULT NULL, poids NUMERIC(5, 2) DEFAULT NULL, imc NUMERIC(5, 2) DEFAULT NULL, categorie_imc VARCHAR(50) DEFAULT NULL, diagnostic LONGTEXT DEFAULT NULL, traitement_prescrit LONGTEXT DEFAULT NULL, observations LONGTEXT DEFAULT NULL, cree_le DATETIME DEFAULT NULL, modifie_le DATETIME DEFAULT NULL, statut VARCHAR(20) DEFAULT NULL, patient_id INT DEFAULT NULL, rendez_vous_id INT DEFAULT NULL, INDEX IDX_20D23266B899279 (patient_id), UNIQUE INDEX UNIQ_20D232691EF7EAA (rendez_vous_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE groupe_cible (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, type VARCHAR(50) NOT NULL, critere_optionnel VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE inscription_evenement (id INT AUTO_INCREMENT NOT NULL, date_inscription DATETIME NOT NULL, statut VARCHAR(50) NOT NULL, present TINYINT NOT NULL, cree_le DATETIME NOT NULL, modifie_le DATETIME DEFAULT NULL, evenement_id INT NOT NULL, utilisateur_id INT NOT NULL, groupe_cible_id INT DEFAULT NULL, INDEX IDX_AD33AA06FD02F13 (evenement_id), INDEX IDX_AD33AA06FB88E14F (utilisateur_id), INDEX IDX_AD33AA0638CC5579 (groupe_cible_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE laboratoire (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, ville VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, disponible TINYINT NOT NULL, cree_le DATETIME NOT NULL, email VARCHAR(255) DEFAULT NULL, numero_agrement VARCHAR(50) DEFAULT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE laboratoire_type_analyse (id INT AUTO_INCREMENT NOT NULL, disponible TINYINT NOT NULL, prix NUMERIC(10, 2) DEFAULT NULL, delai_resultat_heures INT DEFAULT NULL, conditions LONGTEXT DEFAULT NULL, laboratoire_id INT NOT NULL, type_analyse_id INT NOT NULL, INDEX IDX_DB6F523476E2617B (laboratoire_id), INDEX IDX_DB6F52343FDD09A (type_analyse_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE medecin (specialite VARCHAR(100) DEFAULT NULL, annee_experience INT DEFAULT NULL, grade VARCHAR(50) DEFAULT NULL, adresse_cabinet VARCHAR(255) DEFAULT NULL, telephone_cabinet VARCHAR(20) DEFAULT NULL, nom_etablissement VARCHAR(100) DEFAULT NULL, numero_urgence VARCHAR(20) DEFAULT NULL, document_pdf VARCHAR(255) DEFAULT NULL, disponibilite LONGTEXT DEFAULT NULL, id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE patient (groupe_sanguin VARCHAR(10) DEFAULT NULL, contact_urgence VARCHAR(20) DEFAULT NULL, sexe VARCHAR(10) DEFAULT NULL, id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE rendez_vous (id INT AUTO_INCREMENT NOT NULL, date_rdv DATE NOT NULL, heure_rdv TIME NOT NULL, raison LONGTEXT DEFAULT NULL, statut VARCHAR(20) NOT NULL, cree_le DATETIME NOT NULL, date_validation DATETIME DEFAULT NULL, patient_id INT DEFAULT NULL, medecin_id INT NOT NULL, fiche_medicale_id INT DEFAULT NULL, INDEX IDX_65E8AA0A6B899279 (patient_id), INDEX IDX_65E8AA0A4F31A84 (medecin_id), UNIQUE INDEX UNIQ_65E8AA0A9A99F4BC (fiche_medicale_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE responsable_laboratoire (laboratoire_id INT DEFAULT NULL, id INT NOT NULL, UNIQUE INDEX UNIQ_C4592A3076E2617B (laboratoire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE responsable_parapharmacie (parapharmacie_id INT DEFAULT NULL, id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE type_analyse (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(50) NOT NULL, description LONGTEXT NOT NULL, actif TINYINT NOT NULL, cree_le DATETIME NOT NULL, categorie VARCHAR(100) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(30) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, date_naissance DATE DEFAULT NULL, est_actif TINYINT DEFAULT 1 NOT NULL, photo_profil VARCHAR(255) DEFAULT NULL, cree_le DATETIME NOT NULL, discr VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE administrateur ADD CONSTRAINT FK_32EB52E8BF396750 FOREIGN KEY (id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE demande_analyse ADD CONSTRAINT FK_5ECB7D16B899279 FOREIGN KEY (patient_id) REFERENCES patient (id)');
        $this->addSql('ALTER TABLE demande_analyse ADD CONSTRAINT FK_5ECB7D14F31A84 FOREIGN KEY (medecin_id) REFERENCES medecin (id)');
        $this->addSql('ALTER TABLE demande_analyse ADD CONSTRAINT FK_5ECB7D176E2617B FOREIGN KEY (laboratoire_id) REFERENCES laboratoire (id)');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681E73A201E5 FOREIGN KEY (createur_id) REFERENCES utilisateur (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE evenement_groupe_cible ADD CONSTRAINT FK_18DD4E3DFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evenement_groupe_cible ADD CONSTRAINT FK_18DD4E3D38CC5579 FOREIGN KEY (groupe_cible_id) REFERENCES groupe_cible (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fiche_medicale ADD CONSTRAINT FK_20D23266B899279 FOREIGN KEY (patient_id) REFERENCES patient (id)');
        $this->addSql('ALTER TABLE fiche_medicale ADD CONSTRAINT FK_20D232691EF7EAA FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inscription_evenement ADD CONSTRAINT FK_AD33AA06FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id)');
        $this->addSql('ALTER TABLE inscription_evenement ADD CONSTRAINT FK_AD33AA06FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE inscription_evenement ADD CONSTRAINT FK_AD33AA0638CC5579 FOREIGN KEY (groupe_cible_id) REFERENCES groupe_cible (id)');
        $this->addSql('ALTER TABLE laboratoire_type_analyse ADD CONSTRAINT FK_DB6F523476E2617B FOREIGN KEY (laboratoire_id) REFERENCES laboratoire (id)');
        $this->addSql('ALTER TABLE laboratoire_type_analyse ADD CONSTRAINT FK_DB6F52343FDD09A FOREIGN KEY (type_analyse_id) REFERENCES type_analyse (id)');
        $this->addSql('ALTER TABLE medecin ADD CONSTRAINT FK_1BDA53C6BF396750 FOREIGN KEY (id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE patient ADD CONSTRAINT FK_1ADAD7EBBF396750 FOREIGN KEY (id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A6B899279 FOREIGN KEY (patient_id) REFERENCES patient (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A4F31A84 FOREIGN KEY (medecin_id) REFERENCES medecin (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A9A99F4BC FOREIGN KEY (fiche_medicale_id) REFERENCES fiche_medicale (id)');
        $this->addSql('ALTER TABLE responsable_laboratoire ADD CONSTRAINT FK_C4592A3076E2617B FOREIGN KEY (laboratoire_id) REFERENCES laboratoire (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE responsable_laboratoire ADD CONSTRAINT FK_C4592A30BF396750 FOREIGN KEY (id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE responsable_parapharmacie ADD CONSTRAINT FK_5AF73461BF396750 FOREIGN KEY (id) REFERENCES utilisateur (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE administrateur DROP FOREIGN KEY FK_32EB52E8BF396750');
        $this->addSql('ALTER TABLE demande_analyse DROP FOREIGN KEY FK_5ECB7D16B899279');
        $this->addSql('ALTER TABLE demande_analyse DROP FOREIGN KEY FK_5ECB7D14F31A84');
        $this->addSql('ALTER TABLE demande_analyse DROP FOREIGN KEY FK_5ECB7D176E2617B');
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681E73A201E5');
        $this->addSql('ALTER TABLE evenement_groupe_cible DROP FOREIGN KEY FK_18DD4E3DFD02F13');
        $this->addSql('ALTER TABLE evenement_groupe_cible DROP FOREIGN KEY FK_18DD4E3D38CC5579');
        $this->addSql('ALTER TABLE fiche_medicale DROP FOREIGN KEY FK_20D23266B899279');
        $this->addSql('ALTER TABLE fiche_medicale DROP FOREIGN KEY FK_20D232691EF7EAA');
        $this->addSql('ALTER TABLE inscription_evenement DROP FOREIGN KEY FK_AD33AA06FD02F13');
        $this->addSql('ALTER TABLE inscription_evenement DROP FOREIGN KEY FK_AD33AA06FB88E14F');
        $this->addSql('ALTER TABLE inscription_evenement DROP FOREIGN KEY FK_AD33AA0638CC5579');
        $this->addSql('ALTER TABLE laboratoire_type_analyse DROP FOREIGN KEY FK_DB6F523476E2617B');
        $this->addSql('ALTER TABLE laboratoire_type_analyse DROP FOREIGN KEY FK_DB6F52343FDD09A');
        $this->addSql('ALTER TABLE medecin DROP FOREIGN KEY FK_1BDA53C6BF396750');
        $this->addSql('ALTER TABLE patient DROP FOREIGN KEY FK_1ADAD7EBBF396750');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A6B899279');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A4F31A84');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A9A99F4BC');
        $this->addSql('ALTER TABLE responsable_laboratoire DROP FOREIGN KEY FK_C4592A3076E2617B');
        $this->addSql('ALTER TABLE responsable_laboratoire DROP FOREIGN KEY FK_C4592A30BF396750');
        $this->addSql('ALTER TABLE responsable_parapharmacie DROP FOREIGN KEY FK_5AF73461BF396750');
        $this->addSql('DROP TABLE administrateur');
        $this->addSql('DROP TABLE demande_analyse');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('DROP TABLE evenement_groupe_cible');
        $this->addSql('DROP TABLE fiche_medicale');
        $this->addSql('DROP TABLE groupe_cible');
        $this->addSql('DROP TABLE inscription_evenement');
        $this->addSql('DROP TABLE laboratoire');
        $this->addSql('DROP TABLE laboratoire_type_analyse');
        $this->addSql('DROP TABLE medecin');
        $this->addSql('DROP TABLE patient');
        $this->addSql('DROP TABLE rendez_vous');
        $this->addSql('DROP TABLE responsable_laboratoire');
        $this->addSql('DROP TABLE responsable_parapharmacie');
        $this->addSql('DROP TABLE type_analyse');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
